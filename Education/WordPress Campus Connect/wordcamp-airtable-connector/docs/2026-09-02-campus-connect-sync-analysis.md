# Campus Connect sync — design analysis (2026-09-02)

Produced by a 13-agent workflow: five parallel readers over the plugin, three independent
integration designs (minimal-diff / robustness-first / operator-first), a synthesis, and four
adversarial reviews.

**Status: NOT ready to implement.** The synthesis below is sound in shape, but the adversarial
pass found defects in it that would corrupt or silently skip data. Fold those in first.

---

# Recommended implementation plan: Campus Connect sync for WordCamp Airtable Connector

## Verdict

**Base: `minimal-diff`.** It is the only one of the three whose sync path stays inside the plugin's existing four seams (setting -> source method -> mapper method -> job case), and its one signature change (`WCAC_Source::get()` gains an optional second argument) is unavoidable in all three designs anyway.

Grafted in, deliberately:

- from `operator-first`: the **structurally non-destructive mapper** (emit a key only when there is a value; the merge key is the only always-present key). This is the single best idea in the three designs, it is free, and it removes the entire class of "the sync blanked 142 hand-imported cells" failures by construction rather than by procedure. It also makes most of `robustness-first`'s guard machinery unnecessary.
- from `operator-first`: **default-deny at the write boundary for Status labels**, and a CLI dry run.
- from `robustness-first`: the **401/403 latch** (stop re-queueing a doomed job) with a 12-hour self-heal, `typecast` becoming a per-call option, and `upsert()` reporting partial progress in its `WP_Error` data.
- from `robustness-first`/`operator-first`: a narrow **Airtable read verb** used only by a CLI preflight, to prove the merge key is present and unique across the 142 hand-imported rows before the first write.

Everything else in `robustness-first` and `operator-first` is dropped; see "Deliberately not building".

---

## Facts established during this pass (do not re-derive, do not guess)

**1. The destination table schema is confirmed live** (`appoiPJkMFdnJEmfa` / `tbld8niqsLWyNbcVF`, read 2026-09-02): exactly 13 fields, no `Synced At`, no `Last Modified`. `Anticipated Attendees` is `fldDFq9WcpkXxWkn3`, `singleLineText`. `Actual Attendees` (`fld17v7PdZhuXPCAD`) and `Series Event` (`flde25DxmHupDlqaL`) are `number` precision 0. `Start Date`, `End Date`, `Created` are `date` with ISO `YYYY-MM-DD` formatting. `Status` is `fldosgc5sA7VIfCns`, `singleSelect`, with exactly these 11 choices:

`Closed`, `Scheduled`, `In Pre-Planning`, `Needs Vetting`, `Needs Orientation/Interview`, `Interview/Orientation Scheduled`, `Approved for Pre-Planning Pending Agreement`, `Needs to Fill Out Listing`, `On Hold`, `Cancelled`, `Declined`.

**2. The wcpt status slugs are confirmed from source**, `WordCamp_Loader::get_post_statuses()` in `wordcamp.org/public_html/wp-content/plugins/wcpt/wcpt-wordcamp/wordcamp-loader.php` (production branch, lines 205-229). WordPress caps `post_status` at 20 characters, so **the slugs are truncated and are not what anyone would guess**:

```
wcpt-needs-vetting    Needs Vetting
wcpt-needs-orientati  Needs Orientation/Interview
wcpt-more-info-reque  On Hold
wcpt-interview-sched  Interview/Orientation Scheduled
wcpt-rejected         Declined
wcpt-cancelled        Cancelled
wcpt-approved-pre-pl  Approved for Pre-Planning Pending Agreement
wcpt-needs-email      Needs E-mail Address
wcpt-needs-site       Needs Site
wcpt-needs-pre-plann  Needs to be Added to Pre-Planning Schedule
wcpt-pre-planning     In Pre-Planning
wcpt-needs-budget-re  Needs Budget Review
wcpt-budget-rev-sche  Budget Review Scheduled
wcpt-needs-contract   Needs Contract to be Signed
wcpt-needs-fill-list  Needs to Fill Out WordCamp Listing
wcpt-needs-schedule   Needs to be Added to Official Schedule
wcpt-scheduled        WordCamp Scheduled
wcpt-closed           WordCamp Closed
wcpt-needs-action     Needs Action        (Campus Connect exclusive)
```

Not `wcpt-needs-orientation`, not `wcpt-more-info-reqd`, not `wcpt-needs-budget-review`. All three candidate designs guessed at least one of these wrongly.

**3. The map is not the identity function, and it is not complete.** Three labels differ between wcpt and Airtable (`WordCamp Scheduled` -> `Scheduled`, `WordCamp Closed` -> `Closed`, `Needs to Fill Out WordCamp Listing` -> `Needs to Fill Out Listing`), and **eight live slugs have no choice in the Airtable field at all** (`wcpt-needs-email`, `wcpt-needs-site`, `wcpt-needs-pre-plann`, `wcpt-needs-budget-re`, `wcpt-budget-rev-sche`, `wcpt-needs-contract`, `wcpt-needs-schedule`, `wcpt-needs-action`). Since the report deliberately includes non-public post statuses, rows carrying these **will** occur - the mid-pipeline statuses are exactly what a Campus Connect report is for. This is a decision the human must make once, not something to discover at runtime.

Two more slugs appear elsewhere in that file but not in `get_post_statuses()`: `wcpt-needs-mentor` (line 561) and `wcpt-needs-polldaddy` (line 523). They are legacy and are deliberately left out of the map; if an old post still carries one, it is reported as unmapped.

---

## Status mapping strategy (exact)

Three constants and one resolver, all inside `WCAC_Mapper`, placed after `terms()` closes at `class-wcac-mapper.php:162`.

```php
/**
 * wcpt post-status slug => the Campus Connect Events "Status" label.
 *
 * Slugs verbatim from WordCamp_Loader::get_post_statuses() (wcpt plugin,
 * production, 2026-09-02). WordPress caps post_status at 20 characters, which
 * is why several slugs are truncated mid-word - do not "correct" them.
 *
 * Three labels intentionally differ from wcpt's own: the Airtable field says
 * Scheduled / Closed / Needs to Fill Out Listing where wcpt says WordCamp
 * Scheduled / WordCamp Closed / Needs to Fill Out WordCamp Listing.
 */
const CAMPUS_STATUS = array(
    'wcpt-needs-vetting'   => 'Needs Vetting',
    'wcpt-needs-orientati' => 'Needs Orientation/Interview',
    'wcpt-more-info-reque' => 'On Hold',
    'wcpt-interview-sched' => 'Interview/Orientation Scheduled',
    'wcpt-rejected'        => 'Declined',
    'wcpt-cancelled'       => 'Cancelled',
    'wcpt-approved-pre-pl' => 'Approved for Pre-Planning Pending Agreement',
    'wcpt-pre-planning'    => 'In Pre-Planning',
    'wcpt-needs-fill-list' => 'Needs to Fill Out Listing',
    'wcpt-scheduled'       => 'Scheduled',
    'wcpt-closed'          => 'Closed',
    'wcpt-needs-email'     => 'Needs E-mail Address',
    'wcpt-needs-site'      => 'Needs Site',
    'wcpt-needs-pre-plann' => 'Needs to be Added to Pre-Planning Schedule',
    'wcpt-needs-budget-re' => 'Needs Budget Review',
    'wcpt-budget-rev-sche' => 'Budget Review Scheduled',
    'wcpt-needs-contract'  => 'Needs Contract to be Signed',
    'wcpt-needs-schedule'  => 'Needs to be Added to Official Schedule',
    'wcpt-needs-action'    => 'Needs Action',
);

/**
 * The choices that actually existed on Status (fldosgc5sA7VIfCns,
 * tbld8niqsLWyNbcVF) when this was written, read from the live base on
 * 2026-09-02. A label not in this list is not written unless the operator
 * ticks "Allow new Status options" - Airtable's typecast CREATES an unknown
 * singleSelect option rather than rejecting it.
 */
const CAMPUS_STATUS_PRESENT = array(
    'Closed', 'Scheduled', 'In Pre-Planning', 'Needs Vetting',
    'Needs Orientation/Interview', 'Interview/Orientation Scheduled',
    'Approved for Pre-Planning Pending Agreement', 'Needs to Fill Out Listing',
    'On Hold', 'Cancelled', 'Declined',
);
```

```php
/**
 * Resolve a raw wcpt slug to a writable Status label.
 *
 * @param string $slug        Raw report value.
 * @param bool   $allow_new   Operator has opted in to creating options.
 * @return array array( 'label' => string|null, 'why' => ''|'unmapped'|'absent' )
 */
public static function campus_status( $slug, $allow_new = false )
```

Contract, in order:

1. slug not in `CAMPUS_STATUS` -> `array( null, 'unmapped' )`. Covers a genuinely new wcpt status, the legacy `wcpt-needs-mentor`/`wcpt-needs-polldaddy`, and any core status (`draft`, `pending`, `publish`, `private`) that leaks through.
2. label in `CAMPUS_STATUS_PRESENT`, or `$allow_new` is true -> `array( $label, '' )`.
3. otherwise -> `array( null, 'absent' )`. Known slug, correct label, but the option does not exist in the destination and the operator has not opted in.

In every null case `campus_event()` **omits the `Status` key entirely**. An omitted key in a PATCH-upsert leaves the cell untouched; `null` would clear it; a raw slug would mint an option. So an incomplete map degrades to "the 142 hand-imported Status values survive" and never to corruption.

`typecast` follows the same switch: the Campus Connect upsert passes `'typecast' => (bool) WCAC_Settings::get( 'campus_allow_new_status' )`. With the toggle off, `typecast` is **false** for this table, so a label that drifts out of sync is a hard 422 instead of silent pollution. That is safe here because the mapper emits native types only: `int|null`, ISO `Y-m-d` strings (which Airtable accepts on a date field without typecast), and plain strings. With the toggle on, `typecast` is true, and the only thing it can create is a label taken verbatim from the reviewed const - which is the human label the operator asked for.

Reporting: the job collects distinct `unmapped` and `absent` slugs across the whole run and logs **one** line each, never one per row (`WCAC_Logger` is a 200-entry ring buffer rewritten by a full `get_option`/`update_option` on every call). `wp wcac campus-connect --statuses` prints the full reconciliation table before anything is written.

---

## Exact settings keys

Added to `WCAC_Settings::defaults()` (after `tbl_sponsors`, `class-wcac-settings.php:30`, and after `sync_children`, `:33`):

| Key | Default | Sanitised where |
|---|---|---|
| `tbl_campus_connect` | `'tbld8niqsLWyNbcVF'` | text loop, `save()` :81 |
| `sync_campus_connect` | `0` | checkbox loop, `save()` :91 |
| `campus_allow_new_status` | `0` | checkbox loop, `save()` :91 |
| `central_user` | `''` | text loop, `save()` :81, via `sanitize_user()` |
| `central_app_password` | `''` | **its own preserve-on-empty block**, immediately after the `api_key` block at :79 |

`sync_campus_connect` defaults to **0**, unlike the three existing toggles which default to 1: it cannot work before a Central credential exists, and an on-by-default toggle would start 401ing every five minutes the moment the plugin updated.

The password block, verbatim shape:

```php
if ( isset( $input['central_app_password'] ) && '' !== trim( $input['central_app_password'] ) ) {
    $clean['central_app_password'] = preg_replace(
        '/\s+/', '', sanitize_text_field( $input['central_app_password'] )
    );
}
```

It must **not** join the loop at `:81` - that loop's `isset()` guard would blank the stored password on every form save, because an empty password input is still "set". The whitespace strip matters: WordPress shows an application password as six space-separated four-character groups and core's own verifier removes the spaces before comparing, so space-free is the canonical stored form.

---

## Files, exact symbols, exact edits

### `includes/class-wcac-source.php`

**Edit `get()` at :37** to `public function get( $url, array $overrides = array() )`. Inside the args literal at :40-45:

```php
'timeout'     => isset( $overrides['timeout'] ) ? (int) $overrides['timeout'] : 25,
'redirection' => isset( $overrides['redirection'] ) ? (int) $overrides['redirection'] : 5,
'headers'     => array_merge(
    array( 'Accept' => 'application/json' ),
    isset( $overrides['headers'] ) ? $overrides['headers'] : array()
),
```

All four existing call sites (`:116`, `:139`, `:158`, `:206`) pass nothing and are byte-identical in behaviour. Docblock gains one line: a caller passing an `Authorization` header must also pass `redirection => 0`, because `redirection => 5` would replay that header to whatever host a redirect lands on.

**New method after `meetups()` closes at :140:**

```php
/**
 * Fetch the Campus Connect details report from Central.
 *
 * Not a wp/v2 route, so url() (which hardcodes the namespace at :87) is not
 * used. The route takes no query parameters: no per_page, no page, no
 * modified_after, and it emits no x-wp-totalpages, so total_pages is 0. This
 * is a single-shot full read on every run; there is no delta mechanism.
 *
 * @return array|WP_Error The report's `data` list, or a WP_Error carrying the
 *                        HTTP status (401 credentials rejected, 403 the
 *                        account lacks view_wordcamp_reports).
 */
public function campus_connect()
```

Body: read `WCAC_Settings::central_credential()`; return `new WP_Error( 'wcac_central_no_credential', 'No Central credential configured.', array( 'status' => 401 ) )` when either half is empty; build `untrailingslashit( WCAC_Settings::get( 'source_root' ) ) . '/wp-json/wordcamp-reports/v1/campus-connect-details'` inline; call

```php
$this->get( $url, array(
    'headers'     => array( 'Authorization' => 'Basic ' . base64_encode( $cred['user'] . ':' . $cred['pass'] ) ),
    'redirection' => 0,
    'timeout'     => 45,
) );
```

with `// phpcs:ignore WordPress.PHP.DiscouragedFunctions.obfuscation_base64_encode -- HTTP Basic auth, not obfuscation.` above the encode. Pass a `WP_Error` straight through. Otherwise: if `! is_array( $result['body'] ) || ! isset( $result['body']['data'] ) || ! is_array( $result['body']['data'] )`, return `new WP_Error( 'wcac_central_envelope', 'Campus Connect report returned an unexpected shape.' )` - an HTML error page that happens to decode must not read as zero rows. Otherwise return `$result['body']['data']`.

Timeout is 45, not the inherited 25: the report generates every Campus Connect event server-side and 25 was sized for paginated collection reads. Never add `_fields` (comment at :98-104: `wp_parse_list()` splits on whitespace, and eleven of the thirteen keys contain spaces).

### `includes/class-wcac-settings.php`

New, after `get()` at :62:

```php
/**
 * The Central credential, constants first.
 *
 * The constant override lives HERE and deliberately not in all(): save()
 * starts from $current = self::all() at :74, so an overlay inside all() would
 * copy the wp-config secret into the database on the next form save.
 *
 * @return array array( 'user' => string, 'pass' => string, 'source' => 'constant'|'option'|'none' )
 */
public static function central_credential()

/** @return bool Both halves present. */
public static function central_ready()

/** @return bool central_ready() and the toggle and a table ID. */
public static function campus_connect_ready()

/**
 * Write one secret without touching anything else.
 *
 * save()'s checkbox loop at :91-93 is UNCONDITIONAL, so save( array( one key ) )
 * silently sets every sync toggle to 0. The CLI writes through here instead.
 *
 * @return void
 */
public static function set_secret( $key, $value )
```

`central_credential()` prefers `WCAC_CENTRAL_USER` / `WCAC_CENTRAL_APP_PASSWORD` when `defined()`, applying the same `preg_replace( '/\s+/', '', ... )` on the constant path, and reports `source`. `is_configured()` at :113-117 is **left exactly as it is** - it gates `run_slice()` (`class-wcac-sync.php:292`) and the CLI sync (`class-wcac-cli.php:40`), so tightening it would stop the five working syncs on every site that never configures Campus Connect. `table_for()` needs no change; `'tbl_' . 'campus_connect'` already resolves.

### `includes/class-wcac-mapper.php`

**`any_date()`, after `stamp_to_date()` at :104:**

```php
/**
 * A report date that may arrive as a Unix timestamp OR a Y-m-d string.
 *
 * Neither existing helper is safe here. stamp_to_date() routes through
 * int_or_null() first, so the string '2026-05-14' casts to int 2026 and
 * gmdate('Y-m-d', 2026) returns 1970-01-01 on every row, silently.
 * gmt_to_datetime() only appends 'Z', turning '2024-03-01 12:00:00' into a
 * datetime with a space where ISO needs a T. Nothing in this repo settles
 * which shape the report sends, so accept both.
 *
 * @return string|null
 */
public static function any_date( $value )
```

`ctype_digit( (string) $value )` -> `gmdate( 'Y-m-d', (int) $value )`; a leading `/^\d{4}-\d{2}-\d{2}/` -> `substr( $value, 0, 10 )`; anything else -> `null`. Then a sanity window: return `null` outside 2006-01-01 .. now + 5 years, so a mis-shaped value becomes a countable miss rather than a plausible-looking 1970.

**`CAMPUS_STATUS`, `CAMPUS_STATUS_PRESENT`, `campus_status()`** as specified above, after :162.

**`campus_event()`, after `sponsor()` closes at :332** (it must live inside `WCAC_Mapper`: `pick()` at :341 is protected):

```php
/**
 * Map one Campus Connect report row to the Campus Connect Events table.
 *
 * UNLIKE every other mapper in this file, this one emits a key ONLY when the
 * report carries a value. Airtable's PATCH-upsert leaves omitted fields
 * untouched, so this payload is physically incapable of clearing a cell -
 * which matters because the 142 destination rows were imported by hand and
 * may hold values the report does not. Do not "normalise" it to match
 * wordcamp() at :170.
 *
 * @param array $row  One entry from the report's `data` list.
 * @param bool  $allow_new_status Operator opted in to new singleSelect options.
 * @return array array( 'fields' => array, 'status_note' => ''|'unmapped'|'absent', 'slug' => string )
 */
public static function campus_event( array $row, $allow_new_status = false )
```

Field mapping, with the destination names exactly as the live schema spells them:

| Airtable field | Source key | Coercion |
|---|---|---|
| `WordCamp ID` | `ID` | `(int) self::pick( $row, 'ID' )`; **always emitted**; caller drops the row when it is not >= 1 |
| `Name` | `Name` | `self::text( ..., 255 )` |
| `Status` | `Status` | `campus_status()`, omitted on null |
| `Start Date` | `Start Date (YYYY-mm-dd)` | `any_date()` |
| `End Date` | `End Date (YYYY-mm-dd)` | `any_date()` |
| `Institution Name` | `Venue Name` | `self::text( ..., 255 )` |
| `City` | `_venue_city` | `self::text( ..., 128 )` |
| `Country` | `_venue_country_name` | `self::text( ..., 128 )` |
| `Anticipated Attendees` | `Number of Anticipated Attendees` | **`self::text( ..., 255 )`, never `int_or_null()`** |
| `Actual Attendees` | `Actual Attendees` | `self::int_or_null()` |
| `Series Event` | `Series Event` | `self::int_or_null()`, only under `array_key_exists` |
| `Created` | `Created` | `any_date()` |
| `URL` | `URL` | `esc_url_raw()` |

Every key except `WordCamp ID` is dropped when the coerced value is `null` or `''`. The report's `Organizer Name` has no column here and is discarded. **No `Synced At`, no `Last Modified`** - all five existing mappers append them (`:203`, `:223`, `:263`, `:293`, `:324`) and neither field exists on `tbld8niqsLWyNbcVF`; `typecast` forgives unknown values but never unknown field names, which is a 422 that kills the whole ten-record chunk.

The `Series Event` rule also disposes of the capability difference for free: a `view_wordcamp_reports`-only credential simply never sends the key, so the column is never touched; a `manage_options` credential sends it and it updates.

### `includes/class-wcac-airtable.php`

1. **`upsert()` at :158** becomes `upsert( $table, array $records, $merge_field, array $options = array() )`, with `$options` merged over the payload at :164-173 and `'typecast' => true` kept as the default, so the four existing call sites are unchanged.
2. **Chunk-failure return at :177-179** carries partial progress: `array( 'status' => ..., 'created' => $created, 'updated' => $updated, 'chunk' => $i )`. `WCAC_Sync::error_status()` keeps working because `status` is still there, and a run that wrote 110 of 142 rows before failing can finally say so instead of reporting 0 created, 0 updated, 1 error.
3. **New `list_field()`**, after `upsert()` closes at :200:

```php
/**
 * Read one field from every row of a table, following Airtable's offset
 * cursor. Needs only data.records:read, which the PAT already has.
 *
 * Used by the CLI preflight only - the cron sync path never reads.
 *
 * @return array|WP_Error array( recId => value ) with nulls for blank cells.
 */
public function list_field( $table, $field, $max_pages = 50 )
```

Build the query string by hand and append it after `rawurlencode( $table )`, the trick `ping()` already uses at :209. No other change to this file: the token appears only in the `Authorization` header at :95, nothing logs `$args`, and it stays that way.

### `includes/class-wcac-sync.php`

1. **`state()` defaults at :117-126** gain `'cc_block' => 0` and `'cc_block_why' => ''`.
2. **`enqueue_full()` after :209** and **`enqueue_incremental()` after :245**, identical in both:

```php
if ( WCAC_Settings::campus_connect_ready() && ! $this->cc_blocked() ) {
    $jobs[] = array( 'to' => 'cc' );
}
```

No `page`, no `since`: the route takes no query parameters, so the `$since` computed at :236 is inapplicable and the incremental job is the same job as the full one. Every run re-reads every row.

3. **`run_job()` switch, after the `kid` case's break at :377:** `case 'cc': $this->job_campus_connect(); break;`. There is no default case, so a job type without an arm here is discarded with no log entry.
4. **New `protected function cc_blocked()` and `protected function cc_block( $why )`** near `error_status()` at :351. `cc_block()` stamps `time()` and a reason into state; `cc_blocked()` returns true while the stamp is under 12 hours old, so the block heals itself once the credential is fixed even if nobody presses anything. `WCAC_Settings::save()` also clears it (one line in `handle_save()`, so re-saving the form re-arms the job immediately).
5. **New `protected function job_campus_connect()` at :508**, in the gap between `job_meetups()` closing at :507 and the `job_event_data()` docblock at :509. Shape:

```
$rows = $this->source->campus_connect();          // already unwrapped from the envelope

if ( is_wp_error( $rows ) ) {
    $code = $this->error_status( $rows );
    if ( 401 === $code || 403 === $code ) {
        $this->cc_block( 401 === $code
            ? 'Central rejected the credential (HTTP 401). Check the username and application password.'
            : 'Authenticated, but that Central account lacks view_wordcamp_reports (HTTP 403).' );
    }
    $this->tally( 0, 0, 1 );
    WCAC_Logger::log( 'error', 'Campus Connect fetch failed: ' . ... );   // one line, distinct text per code
    return;
}

if ( empty( $rows ) ) {                            // a 200 with zero rows is never legitimate here
    $this->tally( 0, 0, 1 );
    WCAC_Logger::log( 'error', 'Campus Connect report returned no rows; nothing written.' );
    return;
}

map every row -> collect $records, $skipped (ID < 1), $unmapped[], $absent[]
log ONE warn per category, naming the distinct slugs and the counts
$sent = $this->airtable->upsert(
    WCAC_Settings::table_for( 'campus_connect' ), $records, 'WordCamp ID',
    array( 'typecast' => (bool) WCAC_Settings::get( 'campus_allow_new_status' ) )
);
is_wp_error -> tally( partial created, partial updated, 1 ) + one error line quoting the chunk index
else tally( $sent['created'], $sent['updated'] ) + one info line
```

Three things it must **not** do, each of which a copy-paste would introduce: no `if ( 400 === $this->error_status(...) ) return;` (that is the wp/v2 "past the last page" convention at :396-398 and :470-472 and would swallow a genuine bad request); no page fan-out (`:408-416`, `:482-490` - `total_pages` is read from `x-wp-totalpages` at `class-wcac-source.php:70` and is 0 for a custom route); and no routing through `sync_collection()` at :566, which downgrades every source error to a warn and would report a 401 as a successful run that wrote nothing. It also reads nothing from and writes nothing to the camp cache: the table has no linked-record field, and the job must not depend on `sync_wordcamps` being on.

### `includes/class-wcac-admin.php`

1. `'tbl_campus_connect' => __( 'Campus Connect Events table' )` into the `$tables` map at :302-308; the loop at :310-316 generates the row.
2. After the Airtable token row at :289-296: a `Central username` text input (`name="central_user"`, with a value attribute) and a `Central application password` input cloned verbatim from :292 - `type="password"`, `autocomplete="off"`, **no `value` attribute**, placeholder `Saved - leave blank to keep` only when something is stored. When `WCAC_CENTRAL_APP_PASSWORD` is defined, render both disabled with the note "Set in wp-config.php on this server". Description states plainly that this is an account on central.wordcamp.org holding `view_wordcamp_reports`, and that it has nothing to do with the local administrator viewing this screen (`self::CAP` at :16 authorises the local WordPress user only).
3. Two checkboxes in the `What to sync` fieldset at :320-324: `sync_campus_connect` ("Campus Connect events - needs the Central credential below") and `campus_allow_new_status` ("Allow the sync to add new Status options in Airtable - leave off unless a preflight told you to").
4. Two cases in `handle_action()` at :123-164 plus two entries in the `$messages` whitelist at :179-187 (anything absent from it renders nothing): `central_test` (hit the report, report 200 with row count / 401 / 403, clear `cc_block` on success, write nothing to Airtable) and `cc_now` (queue a single `cc` job). Two matching buttons in the form at :268-276.
5. A persistent `notice-error` banner while `cc_block` is set, quoting the stored reason, plus a small Campus Connect line in the status table: rows written on the last successful run, and any unmapped or absent status slugs.

### `includes/class-wcac-cli.php`

Every public method here becomes a `wp wcac` subcommand automatically (`wordcamp-airtable-connector.php:45` registers the class as a namespace).

```php
/**
 * wp wcac campus-connect [--dry-run] [--statuses] [--preflight]
 */
public function campus_connect( $args, $assoc_args )

/**
 * wp wcac central-credential --user=<login>
 * Reads the password from STDIN, never from argv, so it stays out of ps and
 * shell history. Writes via WCAC_Settings::set_secret(), never save().
 */
public function central_credential( $args, $assoc_args )
```

- `--statuses`: fetch, tally every distinct raw `Status` slug, and print `slug | rows | would write | option exists? | verdict (OK / ABSENT / UNMAPPED)`. Non-zero exit when anything is ABSENT or UNMAPPED, so it is usable from a deploy check.
- `--preflight`: `list_field( tbl_campus_connect, 'WordCamp ID' )`, then report blank cells, duplicate values, and drift (Airtable rows whose ID no longer appears in the report - named, never deleted). Blank or duplicate merge values fail the entire ten-record chunk they land in, and the sync path never reads the table, so this cannot be detected at write time.
- `--dry-run`: fetch, map, print the first ten mapped rows and the whole summary. Writes nothing.
- Extend `test()` at :153 with a Central probe, guarded on `central_ready()` so the command's existing behaviour is unchanged where Campus Connect is not configured.
- Add rows to `status()` at :111-122: `campus connect` on/off, `central credential` constant/option/missing, `campus blocked` yes/no plus reason. Source names and booleans only, never a value.

### `wordcamp-airtable-connector.php`

Version header at :6 and `WCAC_VERSION` at :19 to `1.1.0`. No new `require_once` (no new class file). No new `define()`: `WCAC_CENTRAL_USER` and `WCAC_CENTRAL_APP_PASSWORD` are read with `defined()` and never defined by the plugin.

### `readme.txt`

Lines 30-37 currently assert, in the shipped documentation, that the WordCamp Reports plugin "registers no live REST routes" and that `/wp-json/wordcamp-reports/v1` returns 404 - which is exactly the namespace this feature uses. Rewrite: one authenticated report route now exists (`campus-connect-details`, gated on `view_wordcamp_reports`, 401 to anonymous callers) and is synced; the financial reports remain admin-only CSV. Add `| Campus Connect Events | WordCamp ID | WP post ID on Central |` to the merge-key table at :39-47. Extend Installation (:52-61) with the application-password steps and the preflight commands. New FAQ entries: why the connector never clears a Campus Connect cell, why a Status can be left untouched, and why every run re-reads all rows. Stable tag to 1.1.0 plus a changelog entry.

### `uninstall.php`

**No change, deliberately.** The credential lives inside `wcac_settings`, which line 14 already deletes, and the block state lives inside `wcac_state`, also already deleted. Giving either its own option would leave a secret behind after uninstall.

---

## Credential handling: the assistant's boundary

The assistant writes field names, labels, accessors, constants and documentation. The **value** is typed by the site owner, into their own wp-admin form, their own `wp-config.php`, or their own STDIN prompt. No file in this repository and no tool call ever contains it.

Mechanically enforced by five things: the credential is only ever sent as an `Authorization` header (`WCAC_Source::get()` bakes the full request URL into its error messages at :57 and :65, those are logged verbatim and printed on the admin screen at :386-388, so a credential in a query string or as `user:pass@host` would land in `wp_options` in cleartext and on screen); it is never passed to `WCAC_Logger::log()`'s `$context`, which is written to the database and never rendered; the admin input has no `value` attribute, ever; the constant overlay lives in the accessor, not in `all()`, so `save()` cannot copy a wp-config secret into the database; and the CLI setter writes one key rather than calling `save()` with a partial array.

---

## What the human does, in order

1. On **central.wordcamp.org**, sign in with an account that holds `view_wordcamp_reports`. Go to **Users -> Profile -> Application Passwords**, create one named "WordCamp Airtable Connector", and copy the value it shows once. Note the login name; that is the username half. Do not paste the password into a chat window, a ticket, a commit, or any file in this repository. (If you are not sure the account holds the capability, carry on - step 4 answers that question precisely.)
2. Supply the credential by **one** of three routes, all performed by you:
   - **A, preferred.** Edit `wp-config.php` above the "stop editing" line: `define( 'WCAC_CENTRAL_USER', 'your-login' );` and `define( 'WCAC_CENTRAL_APP_PASSWORD', 'the value from step 1' );`. The secret then never enters the database. It is also not removed by the plugin's uninstall routine, so delete it by hand if you ever uninstall.
   - **B.** In wp-admin, open **WordCamp Sync**, type the username into "Central username", paste the password into "Central application password", press **Save Changes**. Afterwards the field shows only "Saved - leave blank to keep" and the value is never sent back to a browser.
   - **C.** On the server, `wp wcac central-credential --user=your-login` and paste at the prompt. It is read from STDIN, so it stays out of `ps` and shell history.
3. On the same screen, confirm the **Campus Connect Events table** reads `tbld8niqsLWyNbcVF`, tick **Campus Connect events**, leave **Allow new Status options** unticked, and Save.
4. Press **Test Central access**, or run `wp wcac test`. Expect "authenticated, N events". A **401** means the username or password is wrong or the password was revoked - redo step 1. A **403** means the credential is valid but that Central account does not hold `view_wordcamp_reports` - ask a WordCamp.org deputy. Nothing has been written to Airtable at this point.
5. Run `wp wcac campus-connect --statuses`. This is the decision point, and it is the one thing only you can settle. Eight wcpt statuses have no matching option in the Airtable field today: `Needs E-mail Address`, `Needs Site`, `Needs to be Added to Pre-Planning Schedule`, `Needs Budget Review`, `Budget Review Scheduled`, `Needs Contract to be Signed`, `Needs to be Added to Official Schedule`, `Needs Action`. For each one the report actually returns, choose one of:
   - **add the option in Airtable** with that exact label (recommended - these are real pipeline states and WPCC-Tracker will group them properly), then tick **Allow new Status options** so the plugin will write it; or
   - **leave it** - those rows sync everything except Status, and their existing Status cell is left exactly as the manual import set it.
   Anything reported as **UNMAPPED** (a slug not in the const at all) is a genuine gap: send the slug verbatim to whoever maintains the plugin so one line can be added. Deferring is safe - Status is omitted, nothing is corrupted.
6. Run `wp wcac campus-connect --preflight`. It reads back only the `WordCamp ID` column and proves that all 142 hand-imported rows have a value and that no value repeats. Airtable's `performUpsert` fails an entire ten-record chunk on a blank or duplicated merge value and the sync path never reads the table, so **fix any blanks or duplicates in Airtable before going further**. The same output names any Airtable row the report no longer contains; the connector never deletes, so that list is yours to act on or ignore.
7. Run `wp wcac campus-connect --dry-run`. Nothing is written. Check three things in the printed rows: dates are real calendar dates and not `1970-01-01`; `Anticipated Attendees` still reads as the organiser's free text such as `80-100` rather than `80`; Status shows human labels. Confirm zero skipped rows.
8. Run `wp wcac sync` (or press **Sync changes now**, or wait for the five-minute cron). Spot-check a handful of rows in Airtable, then re-read the log for any "unmapped" or "not an option" warning.
9. If the application password is later rotated or revoked, repeat step 2. Nothing else needs touching: the job stops re-queueing after a 401 or 403, and un-blocks itself on the next successful test, on the next settings save, or on its own after twelve hours.

---

## Deliberately not building

- **A plan/apply split, a `WCAC_Campus` class, and a `WCAC_Campus_Guard` validation class.** They were buying "the sync cannot silently write a worse dataset". The omit-when-empty mapper plus the fact that the plugin has no delete verb buys the same guarantee structurally, at roughly a tenth of the surface area. `--dry-run` covers the residual "show me first" need.
- **A row-count high-water mark and a `cc_min_ratio` setting.** With omit-when-empty and no deletes, a truncated report cannot damage anything - it just updates fewer rows. Only the degenerate case is worth guarding, so the job rejects a 200 with zero rows and nothing else.
- **A `--allow-blanking` path.** Clearing a cell is a one-click manual edit in Airtable; a code path for it exists only to be triggered by accident.
- **Resumable offset chunking and job retry.** 142 rows is 15 PATCHes and about 3.3 seconds of throttle inside a 20-second budget. The queue's no-retry semantics (`class-wcac-sync.php:315-318` shifts the job off and persists before running it) are a plugin-wide property; a Campus Connect job that dies is re-created by the next incremental enqueue five minutes later, because the endpoint has no delta and every run is a full read anyway.
- **`schema.bases:read` and a live singleSelect read.** It widens what the Airtable token can do in order to verify one field. The choice list is baked into `CAMPUS_STATUS_PRESENT` from a live read done during design, the default-deny gate means a stale const degrades to "Status omitted" rather than to pollution, and `--statuses` surfaces the drift for a human.
- **A Site Health test.** The admin banner plus `wp wcac status` cover the same ground on a site anyone actually visits; a Site Health integration is a new WordPress surface for a plugin that has none.
- **Per-entity state counters.** `tally()` feeds one global set of created/updated/errors that the admin screen and CLI both read; splitting it changes the shape of the `wcac_state` option for no benefit to this feature.
- **Touching `is_configured()`.** It gates `run_slice()` and the CLI sync; requiring a Central credential there would stop WordCamps and Meetups syncing on every site that never configures Campus Connect.
- **Any delete or archive path.** The plugin only creates and updates. An event that disappears from the report keeps its Airtable row and keeps being rendered by WPCC-Tracker. The preflight names those rows; closing the gap is a deliberate decision for a later version, not something to slip into this one.

## Suggested order of work

`WCAC_Settings` (keys, accessors, `set_secret`) -> `WCAC_Source::get()` widening plus `campus_connect()` -> `WCAC_Mapper` (`any_date`, the two consts, `campus_status`, `campus_event`) -> `WCAC_Airtable` (`$options`, partial counts, `list_field`) -> `WCAC_Sync` (block helpers, job, dispatch, two enqueue lines) -> CLI -> admin -> readme and version bump. The mapper is pure and I/O-free by design, so it is unit-testable against a fixture row before any credential exists; write that fixture from the 13 documented keys and assert on all three status verdicts, both date shapes, and `"80-100"` surviving as a string.

---

# Adversarial review

## Lens: Data loss

I traced the whole write path. Verified against the code, not the plan's description.

## What the plan gets right (stated plainly, not padding)

- **No delete verb exists.** `grep` for `DELETE`/`'PUT'`/`deleteRecords` across the plugin returns nothing. `WCAC_Airtable::request()` is only ever called with `PATCH` (`class-wcac-airtable.php:175`) and `GET` (`:209`). Rows cannot be deleted by this code.
- **PATCH-upsert is genuinely non-destructive for omitted keys**, so the omit-when-empty mapper does buy the guarantee claimed. This is load-bearing and undocumented in the code — add a comment at `class-wcac-airtable.php:175` saying the method must stay `PATCH`, because a future "cleanup" to `PUT` would silently start clearing every unlisted field on all six tables.
- Keeping `Anticipated Attendees` as text and never routing through `int_or_null()` is right; `wordcamp()` at `:196` does the numeric thing and would turn `"80-100"` into `80`.

Below are the places the lens actually bites.

---

## 1. `typecast => false` converts a one-cell problem into a permanent 110-row sync stoppage (highest severity)

`upsert()` chunks by 10 (`:163`) and **returns on the first failing chunk** (`:177-179`), abandoning every later chunk. Today that is survivable because `typecast` is hardcoded `true` (`:166`) so a singleSelect value never 422s. The plan newly sets `typecast => false` for this table only.

Concrete: an operator renames the Airtable option `On Hold` to `Paused`, or deletes `Declined` during a tidy-up. `CAMPUS_STATUS_PRESENT` is a **baked const**, not a live read, so `campus_status()` still returns `'On Hold'`, the default-deny gate passes, and Airtable answers `422 INVALID_MULTIPLE_CHOICE_OPTIONS` for that whole chunk of 10. 142 rows = 15 chunks; if the offending row sits in chunk 4, **rows 31-142 are never sent**. The report order is server-stable, so the same rows lose on every subsequent run, forever. The admin screen shows "1 error" and 30 updated. WPCC-Tracker renders 110 permanently stale rows.

The plan's partial-progress return *reports* this; it does not fix it. Fix: give `upsert()` a `'continue_on_error' => true` option that collects per-chunk errors and keeps going, and use it for Campus Connect. Optionally re-send a failed chunk record-by-record to isolate the poisoned row. Note the plan's stated rationale ("a hard 422 instead of silent pollution") never prices in that the 422 is chunk-atomic and run-terminating.

## 2. The unguarded direction is *inflation*, and there is no defence at all

The plan rejects a row-count guard because "with omit-when-empty and no deletes, a truncated report cannot damage anything." Correct for shrinkage, backwards for growth. `campus-connect-details` shipped to production days ago and is entirely outside this plugin's control. If a query regression there starts returning every WordCamp rather than only Campus Connect ones, the job **creates** ~1,500 new rows in `tbld8niqsLWyNbcVF`. WPCC-Tracker then renders 1,642 "Campus Connect events", and because the plugin has no delete verb the human cannot undo it with this tool — it is a manual 1,500-row cleanup in Airtable.

This is the only true "writes a worse dataset" path with zero mitigation in the design. Cheapest fix: store the last successful row count in `wcac_state`, and refuse (log + `cc_block`) when the incoming count exceeds it by more than ~50% or +50 rows, with an explicit override. One-sided guard, so it costs nothing on the shrink side the plan already argued about.

## 3. `--dry-run` prints computed rows, not a diff — so the one-shot mass overwrite of 142 curated cells is unreviewable

`Name` is the primary field and `Status` drives WPCC-Tracker's grouping. Both were set by a hand import and may have been curated since. The first sync rewrites up to 142 of each, irreversibly, and the specified dry-run shows only "the first ten mapped rows" — what the mapper computed, never what is currently in the cell.

`list_field()` is being built anyway. Widen it to fetch whole records (or just `Name`, `Status`, `Institution Name`) and have `--dry-run` print a real before/after for only the rows that would change. That converts the riskiest moment in the whole rollout from "spot-check ten rows" into "here are the N cells I am about to overwrite." Highest value-per-line change in this review.

## 4. `list_field()` as specified cannot see the blanks it exists to find

Airtable omits a field from `fields` entirely when the cell is empty — it does not return `null`. So a naive `$record['fields']['WordCamp ID']` read yields *nothing* for exactly the blank rows the preflight is for. `list_field()` must explicitly fill absent keys with `null`, and the docblock's "with nulls for blank cells" has to be an implementation instruction, not an assumption.

This matters because a blank merge value on a hand-imported row does not fail — it **creates a duplicate**: the curated row (blank ID) stays, a fresh synced row appears alongside it, and both render. Permanent, since there is no delete.

## 5. The preflight is a checklist item, not a gate

Everything in Finding 4 is caught by step 6 — *if a human runs it*. Nothing in the code path requires it. Make it a gate: have `--preflight` stamp `cc_preflight_ok` (plus a hash of `tbl_campus_connect`) into `wcac_state`, and have `job_campus_connect()` refuse to write until that stamp exists and matches the configured table. Clear it whenever the table ID changes. Turns a documented step into an enforced one for roughly six lines.

## 6. De-duplicate by `WordCamp ID` before chunking

Airtable's `performUpsert` fails a request when two records in it would match the same existing row. If the report ever emits one ID twice (series parent/child join, a `Series Event` artefact), two duplicates in the same chunk of 10 give a 422 → Finding 1's starvation, input-driven and not fixable by the operator. In different chunks, the second silently overwrites the first, order-dependently. De-dupe in the job (keep last, log the collision). Correct either way, independent of Airtable's exact behaviour.

## 7. Use strict emptiness, never `empty()`, when deciding to omit a key

The plan says keys are dropped when the value is `null` or `''`. Written as `if ( $value )` or `if ( ! empty( $value ) )` — the natural way someone types this — `Actual Attendees => 0` and `Series Event => 0` are dropped. Consequence: a genuine zero is never written, and a wrong non-zero count in Airtable can never be corrected *back down* to 0 by the sync. Note that `int_or_null()` at `class-wcac-mapper.php:69-75` correctly returns `0` (not null) for `"0"`, so the bug would be introduced purely by the omit test. Spell out `null !== $v && '' !== $v` in the plan.

## 8. Omit-when-empty protects against *empty*, not against *mangled*

Two non-empty-but-worse writes get through:

- `text()` runs `wp_strip_all_tags()` (`:31`). It exists because the wp/v2 payloads are rendered HTML. The report values are **raw meta**, not HTML. A venue named `Class of <2020> Hall` becomes `Class of Hall` — non-empty, so it is written over the correct hand-imported value. For this mapper use `sanitize_text_field()` plus whitespace collapse, not `text()`.
- `text()` truncates to 255/128. If the report's `Venue Name` is longer than 255, the fuller hand-imported `Institution Name` is replaced by a truncated one. Airtable `singleLineText` has no such limit — the caps in this file are a wp/v2 convention, not a schema constraint. Raise or drop them for this table.

Minor variant: `esc_url_raw()` on a scheme-less `URL` prepends `http://`, downgrading a hand-imported `https://` row.

## 9. One thing worth documenting rather than fixing

`run_slice()` pops the job and persists the queue *before* running it (`:168-169`), so a PHP fatal mid-job loses the job with ~60 of 142 rows written. This self-heals only because `enqueue_incremental()` re-adds `cc` unconditionally and the job is a full idempotent re-read. Say so in the job's docblock — it is the reason the plugin-wide no-retry semantics are acceptable here, and it stops a future reader from adding a `since` parameter that would break the property.

---

**Bottom line:** deletion risk is genuinely zero and the omit-when-empty design does close the blanking class. The residual data-loss surface is (a) chunk-atomic 422 starvation newly created by `typecast => false`, (b) unbounded row *creation* from an upstream report regression, and (c) an unreviewable one-shot overwrite of the 142 curated `Name`/`Status` cells. Findings 1, 2 and 3 should land before the first write.

---

## Lens: Auth and secrets

Read all of `/Users/maciejpilarski/GitHub/wordcamp-airtable-connector`. Findings ranked by severity, single lens: auth and secrets.

---

## What the plan gets right (verified, not flattery)

- **Header, not URL, is the correct call and for the exact reason stated.** `/Users/maciejpilarski/GitHub/wordcamp-airtable-connector/includes/class-wcac-source.php:57` and `:65` bake `$url` verbatim into the `WP_Error` message; that message reaches `WCAC_Logger::log()`, is persisted in `wcac_log`, and is printed to any `manage_options` user by `class-wcac-admin.php:388`. A credential in the query string or as userinfo would land on screen. Confirmed.
- **Constant overlay in the accessor, not in `all()`, is a real trap avoided.** `class-wcac-settings.php:74` is `$current = self::all();` and `:103` writes `$clean` back wholesale, so an overlay inside `all()` would copy the wp-config secret into `wp_options` on the next unrelated form save. Verified.
- **Autoload is currently correct.** Every write in the plugin passes `false` (`settings:103`, `logger:43`, `sync:68,137,186`). Nothing is autoloaded today.
- **The "REST response" half of the lens is a genuine non-issue.** `grep register_rest_route|register_setting|show_in_rest` over the whole plugin returns nothing. The credential cannot surface through `/wp-json/wp/v2/settings` unless someone later adds `show_in_rest` to `wcac_settings`.
- **`$context` really is never rendered.** `render_log()` prints only `time`/`level`/`message`.

Now the problems.

---

## 1. The design's entire credential mechanism is unverified. Check it before writing a line.

The plan's step 1 instructs the human to go to **Users -> Profile -> Application Passwords on central.wordcamp.org**. Nothing in the plan establishes that that screen exists there. WordCamp.org runs on Automattic infrastructure with WordPress.org SSO; `wp_is_application_passwords_available()` is filterable network-wide and is commonly off on SSO-fronted installs, and a user who authenticates by SSO may have no usable local password at all. If it is off, there is no credential to create and the design has no fallback, because cookie+nonce is impossible from cron.

Second, even if application passwords exist, many WordPress hosts do not pass `Authorization` through to PHP (the classic `SetEnvIf Authorization` / `HTTP_AUTHORIZATION` rewrite). That produces a **401 that is indistinguishable from a wrong password**, and the plan's step 4 text ("A 401 means the username or password is wrong or the password was revoked - redo step 1") sends the operator into an unbounded loop re-creating passwords for a fault that is not theirs.

Cost to settle: one anonymous `GET https://central.wordcamp.org/wp-json/` and read the top-level `authentication` object. An empty `{}` means no non-cookie scheme is advertised. Do this first; it can invalidate the whole plan.

Minimum change if it survives: the 401 branch must name three causes, not one, and should do a second probe with the same header against `{source_root}/wp-json/` before it declares the credential wrong.

## 2. Password-manager autofill silently replaces the Central credential with the site's own admin password, then sends it off-site

Concrete sequence, all real behaviour:

1. Operator has `https://example.com/wp-admin` saved in Chrome or Safari.
2. The plan puts `central_user` (text, with a `value` attribute) immediately above `central_app_password` (`type="password"`, `autocomplete="off"`) **inside the single existing settings form** that spans `class-wcac-admin.php:284-353`. `autocomplete="off"` is ignored by both browsers for password fields.
3. Operator opens WordCamp Sync to change `lookback_hours`. Autofill populates the pair with their **wp-admin login and password**.
4. They press Save Changes. The plan's preserve-on-empty block sees a non-empty value, so it overwrites a working Central credential.
5. Five minutes later the cron job base64s the site's own admin password into an `Authorization: Basic` header and sends it to a third-party host, where the failed attempt is logged against that username. The operator sees "HTTP 401" and no indication that their admin password just left the building.

The existing `api_key` field has the same defect, but the consequence there is only a broken sync; here the consequence is transmitting a live credential to another origin.

Fix: put the two credential inputs in **their own `<form>`** with their own nonce and submit, so an unrelated Save cannot carry them; use `autocomplete="new-password"`; and render a non-reversible fingerprint of what is stored (for example the first six characters of `wp_hash()` of the value) so a silent replacement is visible.

## 3. `source_root` is operator-editable and the plan attaches Basic auth to whatever it says

`save()` at `class-wcac-settings.php:87-89` runs `esc_url_raw()` only. That function does **not** reject `http://`, and does **not** strip userinfo. Two live consequences once `campus_connect()` starts hanging an `Authorization` header off that value:

- **Cleartext.** `source_root = http://central.wordcamp.test` (a plausible staging value, and the field's own description invites staging use) sends the base64 credential over the wire in the clear. Because the plan sets `redirection => 0`, Central's http-to-https 301 then comes back as a hard `HTTP 301 from ...` error, so the operator's only signal is a confusing status code, after the secret has already been transmitted.
- **Userinfo in the log.** An operator told "the endpoint needs authentication" may do the obvious thing and set `https://mp:abcd1234efgh@central.wordcamp.org`. `esc_url_raw` preserves it, WP Requests honours it as Basic auth, and from then on every failure logs `HTTP 401 from https://mp:abcd1234efgh@central.wordcamp.org/wp-json/wp/v2/wordcamps?...` into `wcac_log` and prints it at `class-wcac-admin.php:388`. The plan reasons about exactly this trap in its "mechanically enforced" paragraph but adds **no code** to prevent it, while it is already editing `save()`.

Fix, both one-liners in `save()`: strip any authority containing `@`, and reject a non-`https` scheme unless a `WCAC_ALLOW_INSECURE_CENTRAL` constant is defined. Additionally, `campus_connect()` should refuse to attach the header when `wp_parse_url( $url, PHP_URL_SCHEME ) !== 'https'`, so the guard survives a hand-edited option row.

## 4. "Nothing logs `$args`" is true of this plugin and false of the site

The plan calls the credential's confinement "mechanically enforced by five things", one of which is that nothing logs the request args. `WP_Http::request()` fires `pre_http_request` (filter) and `http_api_debug` (action), both receiving the full `$parsed_args` including the `headers` array. Query Monitor's HTTP panel and every "log outbound requests" plugin capture that. On a site running one, `Authorization: Basic bXA6...` ends up persisted in a custom table and rendered in wp-admin, and base64 is not encryption. There is no opt-out from those hooks.

This is an accepted risk, not a solved one. The pre-existing Airtable `Bearer` token has identical exposure, which is exactly why the claim should be softened rather than asserted. Concretely: keep route A (wp-config constants) as the documented default so at least the at-rest copy stays out of the database, and say plainly in `readme.txt` that the in-flight copy is visible to any plugin on the site that hooks the HTTP API.

## 5. Make the `$context` prohibition executable, not editorial

Because `render_log()` deliberately does not print `context`, the next maintainer debugging a 401 will reason "it is not displayed, so it is safe" and drop the request args in. It then sits in `wcac_log` in the database, travels in every support-ticket DB dump and every options export. A design-doc sentence does not survive that.

Fix: five lines at the top of `WCAC_Logger::log()` that walk `$context` and replace any key or string value matching `/authoriz|password|passwd|token|secret|\bBasic \b|\bBearer \b/i` with `[redacted]`. That converts a convention into enforcement and costs nothing.

## 6. `sanitize_text_field()` on a secret is a silent corrupter

The plan wraps the password in `sanitize_text_field()`. That strips tags, removes `%`-encoded octets and drops `<`. A WP application password (`[A-Za-z0-9]{24}` in six groups) happens to survive, but the field accepts whatever the operator pastes, and the FAQ says "the value from step 1". Anything containing `<` or a percent sequence is silently mutated, and every subsequent request 401s with the message "the password is wrong" - which is now true, because the plugin broke it, and the operator has no way to see that.

Nothing ever echoes this value into HTML. `trim()` plus the whitespace strip is the correct sanitiser; `sanitize_text_field` buys nothing here and costs debuggability. Same reasoning applies to the existing `api_key`.

Related, smaller: `sanitize_user( $login, false )` does not strip `:`, and a colon in the username half silently breaks Basic auth parsing. Reject a `:` in `central_user` with a visible error rather than storing it.

## 7. Revocation and expiry: mostly handled, three concrete gaps

Application passwords do not expire, so the real events are revocation (401) and role change (403), and latching both is the right shape. But:

- **The latch is not checked where the job actually runs.** `run_job()` (`class-wcac-sync.php`, the switch the plan extends) dispatches unconditionally, and the plan only tests `cc_blocked()` in `enqueue_full()` / `enqueue_incremental()`. Jobs already sitting in `wcac_queue` when the latch is set still fire. An operator who pressed "Sync changes now" three times gets three more failed Basic auth attempts against Central after the block, and the only escape is Discard queue. Add the check at the top of `job_campus_connect()`.
- **Clearing the latch on any settings save is wrong.** The plan clears `cc_block` inside `handle_save()`. Combined with finding 2, an operator saving the form for an unrelated reason re-arms a 401ing job, potentially with a value they never meant to set. Clear the latch only when the stored credential actually changed (compare `wp_hash()` of old and new), not on every save.
- **Only 401 and 403 latch.** A 3xx (finding 3), a 404 after a route rename, or a WAF 403 with an HTML body all keep the job re-queueing every five minutes and re-sending the credential forever. Add a consecutive-failure counter that latches after, say, five failures of any kind.

## 8. There is no way to remove the stored credential

`save()`'s preserve-on-empty rule (existing for `api_key` at `:77-79`, and the plan copies it for the password) means that once a value is stored, the UI can never clear it. In the incident case this lens exists for - operator suspects the site is compromised, revokes the app password on Central - the stale secret stays in `wcac_settings` indefinitely and any later database export still carries a value that was valid at export time. Add a `what=clear_central` case to `handle_action()` (`class-wcac-admin.php:123`) plus an entry in the `$messages` whitelist at `:179`, and a `wp wcac central-credential --forget`.

## 9. `set_secret()` must pass `autoload = false` explicitly, and the plan does not say so

The plan specifies `WCAC_Settings::set_secret( $key, $value )` without stating the write form. It must be `update_option( self::OPTION, $all, false )`. On current WordPress the omitted third argument defaults to `null` and leaves the existing flag alone, so an omission would probably survive by accident - do not rely on that. If the `wcac_settings` row is ever deleted and recreated by a path that omits the flag, it comes back autoloaded, and the Central password is then loaded into memory on **every front-end request**, visible to anything calling `wp_load_alloptions()` or dumping alloptions in a debug bar.

## 10. Two smaller ones

- **Hidden CLI prompt.** `wp wcac central-credential --user=x` reading STDIN keeps the value out of `ps` and shell history, which is right, but a naive `fgets( STDIN )` echoes the typed characters into the terminal scrollback and into any CI job log. Toggle echo off (`stty -echo` guarded by `function_exists( 'shell_exec' )` and a TTY check) and document `wp wcac central-credential --user=x < secret.txt` as the non-interactive form.
- **Do not put the username in the 401 log line.** Log lines get pasted into GitHub issues. `central_user` is a third party's WordPress.org login; the message needs the status code and the route, not the account.

---

## What I would change in the plan, minimally

Add to `WCAC_Settings::save()`: https-only and userinfo-stripping on `source_root`; `trim()` instead of `sanitize_text_field()` for the secret; reject `:` in `central_user`. Add a redaction filter to `WCAC_Logger::log()`. Split the two credential inputs into their own form with `autocomplete="new-password"` and a stored-value fingerprint, plus a "Remove stored credential" action. Move the `cc_blocked()` check into `job_campus_connect()`, latch on repeated failure of any status, and clear the latch only on an actual credential change. Spell out `false` in `set_secret()`'s `update_option`. Replace the "mechanically enforced" paragraph in the design and the readme with an honest statement that the in-flight header is visible to any plugin hooking `http_api_debug`, and that wp-config constants are preferred for that reason.

And before any of it: confirm that central.wordcamp.org actually issues application passwords and that it accepts an `Authorization` header at the edge. If it does not, findings 2 through 10 are moot and the design needs a different credential entirely.

---

## Lens: Contract drift

## Verdict

The plan is strong on exactly one of the three drift cases in the lens (an added status slug) and structurally weak on the other two (renamed key, changed key count). The weakness is not accidental — it is created by the plan's own headline idea. Omit-when-empty makes every *key-level* contract break silent by construction, and the design contains no key-level assertion anywhere to compensate. Every value-level guard (status default-deny, the date window, the zero-row check) has a counterpart that reports; the key-level path has none.

Two findings below are outright data corruption, not just silence.

---

## 1. CRITICAL — `int_or_null()` writes `0` on non-numeric drift, defeating the non-destructive guarantee

`/Users/maciejpilarski/GitHub/wordcamp-airtable-connector/includes/class-wcac-mapper.php:69-75`:

```php
public static function int_or_null( $value ) {
    if ( is_array( $value ) || null === $value || '' === $value ) {
        return null;
    }
    return (int) $value;
}
```

`(int) 'n/a'` is `0`. `(int) '~300'` is `0`. `(int) 'TBD'` is `0`. The plan routes `Actual Attendees` and `Series Event` through this. Because `0` is neither `null` nor `''`, the plan's omit rule **keeps the key** and Airtable writes `0` into a number field — accepted with or without typecast.

Concrete: a Campus Connect row whose `Actual Attendees` meta holds `"n/a"` (or drifts to `"~120"`, `"1,200"`) overwrites a hand-imported `300` with `0`. WPCC-Tracker renders "0 attendees". No error, no warn, `state['errors']` stays 0, tally reports it as an update.

This directly falsifies the plan's central claim, quoted from its own mapper docblock: "this payload is physically incapable of clearing a cell". It does not clear the cell — it writes a plausible wrong number, which is worse. And the plan already knows the sibling field is organiser free text; `Actual Attendees` comes from the same organiser-entered wcpt meta family and is protected by nothing but an assumption.

**Fix:** a `campus_int()` that returns `null` unless `is_numeric( trim( $value ) )`, and a per-field counter logged once per run ("Actual Attendees: 6 rows held non-numeric values, left untouched").

## 2. CRITICAL — a renamed key is a permanent, completely silent no-op

`pick()` at `class-wcac-mapper.php:341-343` returns `''` for an absent key; `campus_event()` then drops the field. Nothing compares the observed key set against the documented 13.

Concrete: `_venue_city` → `Venue City`. Those two keys (`_venue_city`, `_venue_country_name`) are the only ones in the 13 still leaking raw meta names — they are the most likely thing a report maintainer tidies. After the rename, City and Country stop updating on all 142 rows, permanently. The run logs one info line reading "0 created, 142 updated". The admin screen is green. `--dry-run` would show it, but nothing runs `--dry-run` unattended.

Same silent path for a *shape* change: `text()` at `:27-29` returns `''` for any non-scalar, so `Venue Name` drifting from a string to `{"name":…,"id":…}` also vanishes without a word.

The plan's guards are all value-level. Omit-when-empty converts every key-level break from a loud failure into a silent one, and nothing was added to compensate.

**Fix (~15 lines, and it also solves Findings 3 and 4):** `const CAMPUS_KEYS_REQUIRED` (the 13) and `CAMPUS_KEYS_OPTIONAL` (`Series Event`); in `job_campus_connect()`, diff `array_keys( $rows[0] )` against both, log one warn naming missing and unexpected keys, and persist the observed key set into `wcac_state` so a *change* between runs is reported even when the current set looks plausible.

## 3. CRITICAL — renaming `ID` drops all 142 rows and reports a clean run

`WordCamp ID` is `(int) self::pick( $row, 'ID' )`, and the plan drops the row when it is not `>= 1`. Rename `ID` → `id` or `Event ID`: every row yields `0`, every row is skipped, `$records` is empty. Then `WCAC_Airtable::upsert()` at `class-wcac-airtable.php:163` — `array_chunk( array(), 10 )` is `array()`, the loop never executes, and line 195 returns `created 0, updated 0` with no error.

The plan's job then calls `tally( 0, 0 )` with **no error argument**, and logs the skip count as a *warn* ("log ONE warn per category"). So `state['errors']` stays 0 and both `wp wcac status` and the admin table show a successful sync. The "200 with zero rows" guard does not fire either — the report returned 142 rows; they were merely all unusable.

**Fix:** `$skipped > 0` is an error, not a warn — a Campus Connect row with no post ID is not an ordinary condition. `$skipped === count( $rows )` should latch exactly like a 401.

## 4. HIGH — the 13↔14 capability difference is detected by nothing, in either direction

The plan's `array_key_exists` handling of `Series Event` is the right primitive, and 13→14 is genuinely safe. The failure is 14→13: the application password is recreated on a different Central account, or the account loses `manage_options` while keeping `view_wordcamp_reports`. `Series Event` silently stops syncing, and because omit-when-empty never converges back, the previously written values stay stale forever.

The plan's own sentence — "a `view_wordcamp_reports`-only credential simply never sends the key, so the column is never touched" — describes both the intended behaviour and the undetectable regression; they are byte-identical at the log. `wp wcac status` reports the credential *source* and booleans only, never the observed key count.

**Fix:** Finding 2's state-persisted key set, plus one `status()` row: `campus series-event: syncing | not returned by this credential`.

## 5. HIGH — a raw→display flip on `Status` disables the feature entirely for one warn line

`campus_status()` is an exact lookup on wcpt slugs. The context pins the response shape only "for a caller holding only view_wordcamp_reports", and the WordCamp Reports framework has a raw/display split. If a `manage_options` caller — or a later report version — returns `Status` as `WordCamp Closed` rather than `wcpt-closed`, **every row goes UNMAPPED**, Status is never written for any row again, and the operator sees one warn buried in a 200-entry ring buffer.

**Fix, two lines and free:** before returning `unmapped`, if the raw value is already present in `CAMPUS_STATUS_PRESENT`, return it as the label. That turns a total outage into a no-op. Also `trim()` the raw value before lookup — a trailing space in stored meta currently means UNMAPPED.

## 6. HIGH — `campus_allow_new_status` is a table-wide switch sold to the operator as a per-slug decision

Step 5 of "what the human does" instructs: add the option in Airtable, *then tick the box*. Ticking it (a) sets `typecast: true` for the whole Campus Connect upsert and (b) bypasses `CAMPUS_STATUS_PRESENT` for **all 19** labels, not just the one the operator was thinking about.

Concrete drift, from the Airtable side: an editor later renames the choice `Needs Orientation/Interview` → `Needs Orientation`.
- Toggle **ON** (the recommended path): the next sync silently recreates `Needs Orientation/Interview` as a 20th option; WPCC-Tracker's grouping splits across two near-identical choices; no error anywhere.
- Toggle **OFF**: the same drift is a 422 that fails the entire 10-record chunk.

Both settings of the one switch fail badly, and the plan steers the operator into the silent one.

Also, the plan's claim that with the toggle on "the only thing it can create is a label taken verbatim from the reviewed const" is right about singleSelect options but understates the switch: `typecast` is per-request, not per-field, so it simultaneously loosens coercion on `Actual Attendees`, `Series Event` and the three date fields — compounding Finding 1.

**Fix:** decouple option-creation from `typecast`. Leave `typecast` false unconditionally (the plan already argues the mapper emits native types, and Finding 1's fix makes that actually true), and make the allow-list an explicit array of operator-approved labels rather than a boolean.

## 7. MEDIUM — the zero-row rule is an unlatched error that eats the shared log

`job_campus_connect()` treats an empty `data` as an error. If the report is ever legitimately scoped upstream (future events only, a term rename), that contract change becomes a permanent error. Unlike 401/403 it is **not latched**, so it re-fires on every enqueue. `WCAC_Logger` (`class-wcac-logger.php:16,43`) is a 200-entry ring rewritten wholesale on every call, so a repeating Campus Connect error competes with, and eventually evicts, the log content the five working syncs depend on. Latch it like 401/403, or downgrade to warn after the first occurrence.

## 8. MEDIUM — factual correction: there is no automatic five-minute re-enqueue

The plan states a dead Campus Connect job "is re-created by the next incremental enqueue five minutes later". `wcac_cron_tick()` at `wordcamp-airtable-connector.php:72-76` calls only `run_slice()`. `enqueue_incremental()` is called from exactly two places: the admin button (`class-wcac-admin.php:142`) and `wp wcac sync` (`class-wcac-cli.php:52`). The cron **drains**; it never enqueues.

Under this lens that matters twice: the 401/403 latch's stated purpose ("stop 401ing every five minutes") is largely solving a problem that does not exist, and — more seriously — the silence in Findings 1-4 persists until whatever external schedule runs `wp wcac sync`, not for five minutes. The readme rewrite should also not inherit the existing lines 63-65 phrasing, which already implies a self-driving incremental sync that isn't there.

## 9. MEDIUM — `any_date()` guesses two shapes and freezes silently on a third

The plan concedes "Nothing in this repo settles which shape the report sends" and then resolves the question by accepting two shapes and returning `null` for everything else. Under drift to `d/m/Y` or `m/d/Y`, every date returns `null`, every date key is omitted, and all 142 date cells freeze at their hand-imported values with no signal.

Worse, one drift shape produces a *wrong* answer rather than a null: `Y-m-d\TH:i:sP`. `substr( $value, 0, 10 )` takes the local-time date, so an event starting `2026-05-15T00:30:00+02:00` writes `2026-05-15` where the UTC date is `2026-05-14` — a silent one-day shift on a date field, and the 2006..now+5y window does not catch it.

**Fix:** count rows where a date key was present and non-empty but still coerced to `null`, and log that count. It converts "silently frozen" into "142 dates unparsed — the report's date format changed".

## 10. LOW — `esc_url_raw()` normalises drifted URLs into plausible wrong ones

If `URL` ever drifts to a bare host (`central.wordcamp.org/x`), `esc_url_raw()` prepends `http://` and writes a working-looking, wrong-scheme URL into a url-typed field. Guard with `wp_http_validate_url()` or a required leading `https?://` before emitting.

---

## What the plan genuinely gets right, plainly

- **Additive drift is structurally harmless.** The mapper reads by explicit key, so a 15th, 20th or renamed-in-addition key is never forwarded. Deliberately omitting `Synced At` / `Last Modified` is correct and is the one place additive drift on the *Airtable* side would have caused a 422 that kills a whole ten-record chunk.
- **An added status slug the singleSelect lacks is the best-reasoned part of the design**, and it needs no change: default-deny, key omitted, existing cell preserved, one aggregated warn, `--statuses` as a reconciliation report with a non-zero exit. The observation that `typecast` *creates* an unknown singleSelect option rather than rejecting it is accurate and is the correct thing to have designed around.
- **Skipping `schema.bases:read`** and baking `CAMPUS_STATUS_PRESENT` from a design-time live read is a defensible trade, precisely because the fallback is "Status omitted" rather than "Status polluted".
- **`array_key_exists` for `Series Event`** is the right primitive for the capability question. It just needs to be *observed and reported*, not only branched on.

---

## Priority

1. Finding 1 (`campus_int()`) and Finding 3 (`$skipped` is an error, latch on total skip) — both are silent corruption/false-success, both are a few lines.
2. Finding 2 (`CAMPUS_KEYS_REQUIRED` diff + key set persisted in state) — one mechanism that closes Findings 2, 4 and most of 9.
3. Finding 5 (label pass-through + `trim`) — two lines, converts a total outage into a no-op.
4. Finding 6 (unbind `typecast` from the toggle; make the allow-list explicit).
5. Findings 7, 8, 9, 10 as cleanup, with 8 being a documentation correction the plan must make before its readme text ships.

---

## Lens: Operational

## Operational review: Campus Connect sync plan

Verified against the source at `/Users/maciejpilarski/GitHub/wordcamp-airtable-connector`. Ordered by severity.

---

### 1. BLOCKING: "the next incremental enqueue five minutes later" does not exist

The plan's risk model rests on it. It is false.

`wordcamp-airtable-connector.php:72-76`:

```php
function wcac_cron_tick() {
	( new WCAC_Sync() )->run_slice();
}
add_action( WCAC_CRON_HOOK, 'wcac_cron_tick' );
add_action( WCAC_CONTINUE_HOOK, 'wcac_cron_tick' );
```

The five-minute cron **only drains**. `enqueue_incremental()` has exactly two callers, both human-driven: `includes/class-wcac-admin.php:142` (the "Sync changes now" button) and `includes/class-wcac-cli.php:52` (`wp wcac sync`). Nothing schedules either enqueue. (`readme.txt:63-65` implies otherwise and is wrong about the shipped code.)

Three load-bearing statements in the plan fall with it:

- **"Deliberately not building: job retry ... a Campus Connect job that dies is re-created by the next incremental enqueue five minutes later."** It is not re-created at all. A CC job lost to a fatal, a lock collision or a discarded queue stays lost until a human presses a button.
- **`sync_campus_connect` defaults to 0 because "an on-by-default toggle would start 401ing every five minutes."** With no auto-enqueue there is no queued job to 401. The default may still be right; the stated reason is not.
- **The whole `cc_block` latch.** It stops the job from being *enqueued*, and enqueueing is already a human pressing a button. A latch that gates a manual action against a manual action buys nothing.

**Worse, the latch creates a reachable dead end on the plan's own preferred credential route.** Sequence: 09:55 cron drains a CC job, Central 401s, `cc_block` stamped. 10:00 the operator fixes it via **route A**, the `wp-config.php` constants, which the plan calls preferred. 10:01 they press "Sync changes now". `cc_blocked()` is still true (stamp is 6 minutes old, TTL 12 hours), so the `cc` job is silently **not** queued. The screen says "Sync queued. It will run in the background." The plan clears `cc_block` on `WCAC_Settings::save()` only, and route A never touches that form. The operator's options are to press Save on an unchanged settings form or wait 12 hours, and nothing tells them either.

Fix: clear `cc_block` at the top of both `enqueue_*` when the caller is a human action, or drop the latch and rely on the fact that enqueueing is already manual.

---

### 2. The time-budget arithmetic counts only the sleeps

The plan: *"142 rows is 15 PATCHes and about 3.3 seconds of throttle inside a 20-second budget."* That 3.3s is `15 × MIN_INTERVAL_US`. It omits both the Central report fetch and the 15 HTTP round trips.

`class-wcac-airtable.php:67-75` sets `$this->last_request` *before* `wp_remote_request`, so per-PATCH cost is `max(220ms, actual latency)`, not `220ms + latency`. Realistically 300-500ms each, so 15 PATCHes is 5-8s, not 3.3s. Add the Central fetch, which the plan itself sizes at a 45s timeout because "the report generates every Campus Connect event server-side". Typical CC job: **8-16s. Worst case before any retry: 51s+.**

`class-wcac-sync.php:308` checks the budget *before* dispatching, so a job can start at t=19.9s of a 20s budget. The CC job is indivisible: no `page`, no offset, one job. So on the default `time_budget = 20`, a single CC job routinely consumes an entire tick and every other queued job waits. That is survivable, but the plan should not claim it fits "inside a 20-second budget", and it should say plainly that CC is the only job in the plugin that cannot be split.

---

### 3. The 45s timeout is the only place the plugin can exceed a 30s request limit, and shift-then-run loses the job with no trace

`class-wcac-sync.php:315-318`:

```php
$job = array_shift( $queue );
$this->set_queue( $queue );

$this->run_job( $job );
```

The job is removed from the queue **and persisted** before it runs. Any fatal, OOM or FPM `request_terminate_timeout` mid-job destroys the job: no tally, no log line, no state change. For the five existing syncs that is self-healing on the next enqueue. For CC, per finding 1, nothing re-enqueues.

The admin "Process queue now" button (`class-wcac-admin.php:147`, `run_slice( 20 )`) runs through `admin-post.php`, so it is a web request. A 45s remote timeout under a 30s `request_terminate_timeout` is a hard kill. The plan raised the timeout from the inherited 25 to 45 on the reasoning that "25 was sized for paginated collection reads" and never checked it against the web-request path it will actually be invoked from.

Fix: either keep 45 and make the admin `cc_now` button enqueue-only (never `drain`), or bound it to 25 and accept a retry.

Then, worse: **`run_slice` reports success anyway.** `class-wcac-sync.php:327-333`:

```php
'last_sync' => $remaining ? $this->state()['last_sync'] : WCAC_Mapper::now(),
```

`last_sync` is stamped fresh whenever the queue is empty, regardless of whether the CC job wrote anything or died. A lost or wholly-failed CC job leaves an admin screen reading "last sync: 2 minutes ago" over 142 stale rows.

---

### 4. `typecast => false` turns a one-character typo into a truncated dataset, and the plan's own runbook invites the typo

`upsert()` returns on the **first** failing chunk and abandons every chunk after it (`class-wcac-airtable.php:177-179`).

Concrete: runbook step 5 tells the operator to hand-add options to the `Status` singleSelect in the Airtable UI, including `Approved for Pre-Planning Pending Agreement`, and then tick "Allow new Status options". They add it with a stray double space. The mapper emits the const's exact label, which no longer matches. With `typecast` false that is `INVALID_MULTIPLE_CHOICE_OPTIONS`, HTTP 422. If the offending row lands in chunk 3, **rows 1-30 update and rows 31-142 are silently left stale**, and the next manual sync repeats it identically, forever.

Note the inversion the plan does not acknowledge: with `typecast => true` (what all five existing tables use) that same drift lands all 142 rows and creates one redundant option. The plan chose the configuration in which a hand-edit mistake truncates the dataset at a chunk boundary, in a design whose stated goal is that the sync can never leave a worse dataset. And the toggle only reaches `typecast => true` after the operator has already ticked the box, so the "safe" default is exactly the state where a label typo does the most damage.

Also note `campus_allow_new_status` is doing two unrelated jobs: it gates the mapper's `CAMPUS_STATUS_PRESENT` allowlist *and* flips `typecast` for the whole table. Those should be separable. Keeping `typecast => true` while keeping the allowlist gate gets the plan's stated safety property (only reviewed labels are ever sent) without the chunk-truncation failure mode.

---

### 5. The `cc_block` latch is asymmetric: it covers the failure that self-corrects and not the one that repeats

`error_status()` (`class-wcac-sync.php:351-355`) reads `$data['status']`. The latch arms on 401/403 from Central only. It does **not** arm on:

- `wcac_central_envelope`, the plan's own new error, which carries no `status` at all, so `error_status()` returns `0`. Central serving an HTML maintenance page with HTTP 200 loops indefinitely.
- Any Airtable-side failure. The persistent 422 from finding 4 is the single most likely repeating failure and it is not latched.
- Central 5xx. `WCAC_Source::get()` has no retry at all, unlike `WCAC_Airtable::request()`. One 502 kills the whole run.

If the latch survives at all, it should key on "the last CC attempt failed for a reason that will not change on its own", not on two specific HTTP codes.

---

### 6. Nothing dedupes `cc` jobs, and the report is the most expensive request in the plugin

`enqueue_incremental()` calls `push()`, not `clear_queue()` (contrast `enqueue_full()` at `:199`). Pressing "Sync changes now" three times queues three `cc` jobs. Each one triggers a full server-side report generation on Central plus 15 PATCHes. That is 3 report generations and 45 redundant writes for zero new information, against a base already being written by the other five syncs.

Second ordering trap: the plan puts the CC job third in `enqueue_incremental`, which is correct on an empty queue. But `job_wordcamps` fans ~1500 `kid` jobs onto the tail (`:449-455`), and `enqueue_incremental` appends rather than clearing. Operator presses "Queue full backfill", then five minutes later presses "Sync changes now" because the Campus Connect table looks stale. The `cc` job now sits behind ~1400 `kid` jobs. At roughly 3-6 kid jobs per 20s tick and one tick per 30s from `WCAC_CONTINUE_HOOK`, that is **on the order of two to three hours** before Campus Connect runs. The screen says "Sync queued. It will run in the background." and gives no position or ETA.

Fix: skip the push when a `cc` job is already queued; consider unshifting it rather than appending.

---

### 7. The runbook drives concurrent Airtable clients over the 5 req/s per-base limit

The throttle is instance state (`protected $last_request`, `class-wcac-airtable.php:49`). One `WCAC_Sync` holds one client, so a single slice is safe. Cross-process it is not, and this plan adds new cross-process clients: `list_field()` for `--preflight` builds its own `new WCAC_Airtable()`.

Runbook steps 6-8 all run CLI against the live base and **never say to stop the cron**. Concrete: the operator runs `wp wcac campus-connect --preflight` while a cron tick is mid-upsert. Two independent throttles, each pacing at ~4.5 req/s, against a documented 5 req/s per-base cap. Airtable 429s. `class-wcac-airtable.php:129-131` responds with `sleep( 30 )` and up to three retries, so the **cron slice** absorbs 30-90s of stall it did not cause, blowing its 20s budget.

Add a line to the runbook: press "Discard queue" or accept that the preflight competes with the cron. Or have `list_field` reuse the sync's client.

---

### 8. The 60s lock floor does not cover the job the plan is adding

`class-wcac-sync.php:302`: `set_transient( self::LOCK, 1, max( 60, $budget * 3 ) )`. With the default budget of 20 that is exactly 60s.

A CC job that hits a single 429 spends 30-90s inside one `request()` call. Add the Central fetch and the other 14 PATCHes and the slice runs 100-200s while the lock expires at 60s. The next tick sees no lock and enters `run_slice` concurrently. Both processes then do non-atomic read-modify-write on `wcac_queue`: process A shifts and writes `[kid1, kid2]`; process B, holding a pre-shift read, calls `push()` which does `set_queue( array_merge( $this->queue(), $jobs ) )` and restores the shifted job. Jobs are duplicated or lost, and two clients now write the same base, which reproduces finding 7.

This hazard pre-exists (a `kid` job can crawl 3 collections × up to 20 pages × 25s), but the CC job is the plugin's longest indivisible unit and the only one with a 45s timeout, so the plan should either raise the lock floor to cover it or say why it does not.

---

### 9. The global `Errors` counter is cumulative across incrementals

`enqueue_full()` zeroes `created`/`updated`/`errors` (`:213-222`). `enqueue_incremental()` does **not** (`:268-274`). So one persistent CC failure adds `+1` to a lifetime counter on every manual sync, and the admin "Errors" row (`class-wcac-admin.php` status table) shows an accumulating number with no way to tell that all 47 are the same 422. The plan's per-CC status line helps, but the plan should say the global figure is cumulative-since-last-full so the new line is not read as contradicting it.

---

## What the plan gets right, plainly

These are the hard parts of the lens and they are handled correctly:

- **Omit-when-empty mapper plus no delete verb.** A run killed mid-`upsert()` leaves rows 1-N updated and N+1-142 stale, and re-running is a clean idempotent repair. Given `array_shift`-then-run and no resume state, structural idempotence is the only thing that makes a half-finished run safe, and the plan gets it by construction rather than by procedure. This is the correct call and it is why findings 3 and 4 are truncation problems rather than corruption problems.
- **One log line per category, never per row.** `WCAC_Logger::log()` is a full `get_option`/`update_option` of a 200-entry array on every call (`class-wcac-logger.php:26-44`). Per-row logging on 142 rows would be 142 option rewrites and would flush every other entry out of the ring buffer. The plan names this and avoids it.
- **Not routing through `sync_collection()`.** Correct. `:566-598` downgrades every source error to `warn` and returns without tallying, so a 401 would render as a clean run that wrote nothing.
- **No page fan-out.** Correct. `total_pages` comes from `x-wp-totalpages` (`class-wcac-source.php:70`) and is `0` for a route outside `wp/v2`; copying the `:408-416` pattern would queue nothing or loop.
- **No `400 === error_status()` early return.** Correct. That is the wp/v2 past-the-last-page convention and would swallow a genuine bad request here.
- **`BATCH = 10` untouched.** Correct; Airtable caps `performUpsert` at 10 records per request.
- **Partial progress in the `WP_Error` data.** Necessary given `upsert()`'s abandon-on-first-chunk-failure, and the plan is right that `error_status()` keeps working because `status` stays in the array.
- **Leaving `is_configured()` alone.** Correct. It gates `run_slice()` at `:292` and the CLI at `:40`; tightening it would stop all five existing syncs on every site that never configures Campus Connect.

---

## Minimum changes before implementing

1. Delete the "re-created by the next incremental enqueue five minutes later" reasoning and rebuild the retry/latch story on the real trigger model, or add an auto-enqueue (a separate decision affecting all six syncs).
2. Clear `cc_block` in `enqueue_full()`/`enqueue_incremental()`, not only in `save()`, so route A is not a dead end.
3. Keep `typecast => true` and let the `CAMPUS_STATUS_PRESENT` allowlist alone carry the safety property. Split the toggle from the typecast flag.
4. Reconcile the 45s timeout with the admin `drain` path, which is a web request.
5. Dedupe `cc` in `enqueue_incremental()`.
6. Do not stamp `last_sync` fresh when the CC job errored.
7. Tell the operator in the runbook that the cron keeps running during steps 6-8.

---
