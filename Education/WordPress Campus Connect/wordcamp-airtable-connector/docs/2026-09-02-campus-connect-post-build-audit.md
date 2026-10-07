# Campus Connect sync — post-build audit (2026-09-02)

Four adversarial audits run against the implemented code. **All findings below are OPEN**
unless a later commit says otherwise.

---

## Lens: Secrets

Audit complete. No literal secret exists anywhere in the repo, and the Central application password's core path is genuinely tight. Six escape paths of varying reality, ranked.

---

## VERDICT ON THE CORE PATH — holds

`$cred['pass']` has exactly two consumers in the whole plugin (verified by exhaustive grep):

- `class-wcac-source.php:330` → `base64_encode( $cred['user'] . ':' . $cred['pass'] )` → `headers` only, with `redirection => 0` (`:334`) so WP_Http cannot replay it to a redirect target, and gated on https by `central_scheme_ok()` (`:322`).
- `class-wcac-settings.php:190` → `substr( hash('sha256', user|pass|wp_salt('auth')), 0, 12 )`.

Confirmed clean:
- **Never in a URL.** `central_root()` (`source.php:350-368`) rebuilds scheme/host/port/path only, dropping userinfo, so the `$url` that `get()` bakes into `wcac_source_http` / `wcac_source_json` messages (`source.php:65,73`) cannot carry one. `campus_connect()` and `central_identity_probe()` both route through `central_args()`, so they cannot drift.
- **Never rendered.** `admin.php:680` has no `value` attribute in any state; `$settings['central_app_password']` is read at `:681` and `:710` in boolean context only. `autocomplete="new-password"`, `spellcheck="false"`, `autocapitalize="off"`, wrapped in a `<form autocomplete="off">` that is separate from the settings form, plus `disabled()` when constants win.
- **Never logged.** All 42 `WCAC_Logger::log()` call sites take literal strings or `get_error_message()`; the only `$context` arrays passed anywhere are `array( 'page' => $page )` (`sync.php:828,854,902,928`).
- **Never returned.** `central_identity_probe()` (`source.php:258-277`) returns `status`/`authenticated` only, discarding the `users/me?context=edit` body (which carries the Central account's email).
- **No egress sinks.** Zero `error_log`, `file_put_contents`, `var_dump`, `print_r`, `header()`, `wp_mail`, REST routes, AJAX handlers, or `wp_localize_script` in the plugin.
- **Uninstall.** `wcac_settings` is deleted (`uninstall.php:31-33`); no other option or transient holds either secret.
- No literal secret-shaped string anywhere — regex sweep for `pat…`, `key…`, six-group app-password format, `Basic <b64>`, `Bearer <token>` returns nothing outside `.git`. Docs use placeholders only (`docs/…analysis.md:451`, `readme.txt:118`).
- `php -l` clean on all 10 files.

---

## FINDINGS

### 1. `read_secret()` echoes the application password to the terminal when `shell_exec` or ext-posix is unavailable — `class-wcac-cli.php:1355-1364`

```php
$hide = function_exists( 'shell_exec' )
    && function_exists( 'posix_isatty' )
    && @posix_isatty( STDIN );

if ( $hide ) {
    WP_CLI::log( 'Application password (not echoed):' );
    shell_exec( 'stty -echo' );
}
```

**Trigger:** `wp wcac central-credential --user=someone` at an interactive TTY on a host with `disable_functions=shell_exec` (standard on WP Engine, Pressable, and most managed WordPress hosting) or without ext-posix compiled in (common in slim Docker images, and on Windows).

**Consequence:** `$hide` is false, so no `stty -echo` runs **and no prompt is printed at all**. The command silently blocks on `fgets(STDIN)`; the operator types the application password and every character is echoed — into terminal scrollback, tmux/screen history, `script(1)` transcripts, and any session recorder. There is no failure signal: `shell_exec()`'s return value is never checked either, so on Windows (where `stty` does not exist) `$hide` is true, the "not echoed" prompt prints, and the password is echoed anyway — worse, because the prompt asserts it is not.

The docblock at `:388-391` states this unconditionally: *"Terminal echo is disabled while it is typed, so it does not land in scrollback or a CI job log either."* That is true only on the happy path.

### 2. A DB-stored password becomes permanently invisible and unclearable the moment the wp-config constants are added — `class-wcac-admin.php:700-714`, `class-wcac-settings.php:178`

**Trigger, no crafted request needed:** operator saves the credential through the admin form (lands in `wcac_settings.central_app_password`), then later follows the readme's own recommendation (`readme.txt:232-236`, `docs/…:451`) and moves it to `WCAC_CENTRAL_USER` / `WCAC_CENTRAL_APP_PASSWORD` in `wp-config.php`.

**Consequence:** `$locked` becomes true, and the entire `<p class="submit">` block — including the **Remove stored credential** button — is not rendered:

```php
<?php if ( $locked ) : ?>
    <p class="description">Set in wp-config.php on this server. Remove the constants to manage it here.</p>
<?php else : ?>
    <p class="submit">… <button name="what" value="clear_central">Remove stored credential</button>
```

`credential_fingerprint()` short-circuits to the *constant's* fingerprint (`settings.php:178`: `if ( null === $settings || 'constant' === $cred['source'] )`), and the row is labelled "from wp-config.php" — so the screen actively asserts there is no database copy. `handle_central()` refuses everything after the `clear_central` branch (`admin.php:298-302`), and `save()` never blanks the key (preserve-on-empty, `settings.php:264-270`). The stale, possibly still-valid application password sits in `wp_options` in cleartext indefinitely, travelling in every backup, staging clone and support dump.

This directly falsifies the shipped claim at `readme.txt:234-236`: *"the credential then has no at-rest copy in the database, so it does not travel in database exports or support dumps."* Only `wp wcac central-credential --forget` clears it, and nothing tells the operator to run it.

Related, same handler: `WCAC_Settings::save()` (`settings.php:264`) accepts and persists `central_app_password` with **no** constant check, unlike `handle_central()` (`admin.php:298`). The settings form has no such field, so this needs a hand-crafted authenticated POST to `admin-post.php?action=wcac_save`, but it is an asymmetry that will re-create the orphan row.

### 3. `wp wcac central-check` prints `source_root` verbatim, including any `user:pass@` — `class-wcac-cli.php:362`

```php
array( 'key' => 'source site', 'value' => WCAC_Settings::get( 'source_root' ) ),
```

**Trigger:** a site upgraded from 1.0.x whose `source_root` was saved as `https://someone:secret@central.wordcamp.org`. Baseline `save()` (`git show HEAD:includes/class-wcac-settings.php:88`) was `untrailingslashit( esc_url_raw( trim(...) ) )` — `esc_url_raw()` **preserves** userinfo. The new `strip_userinfo()` (`settings.php:280`) runs only on *submitted* input; there is no upgrade routine, so an existing row survives 1.1.0 untouched forever unless someone re-saves the form.

**Consequence:** the credential is printed to stdout by the one command the readme designates as *"step 0 of the runbook"* (`cli.php:307`) and whose output operators are told to use for diagnosis — i.e. the output most likely to be pasted into a public WordCamp support ticket or captured in a CI log.

### 4. The five original syncs still send and log a `user:pass@` source root — `class-wcac-source.php:124,147` → `:65,73` → `class-wcac-sync.php:828,902`

Same stored value as #3, but consumed raw: `$this->url( WCAC_Settings::get( 'source_root' ), … )` with no `strip_userinfo()`. WP_Http honours URL userinfo as Basic auth, so it is transmitted on every WordCamps/Meetups page; and on any non-200, `get()` returns `sprintf( 'HTTP %d from %s', $code, $url )`, which `sync.php:828` writes into `wcac_log` and `admin.php:786` renders on screen — cleartext credential in `wp_options` and in the admin page.

The 1.1.0 hardening is one-sided: `central_root()` protects the *new* Campus Connect path against exactly this, and the changelog claims *"any `user:pass@` in it is stripped"* (`readme.txt:283-289`) as though it were global. `wordcamps()` and `meetups()` should use `central_root()` too, and `save()`'s strip needs an upgrade-time pass over the stored value.

### 5. The advertised log-context redaction does not exist — `class-wcac-logger.php:26-44` vs `readme.txt:231,290`

`readme.txt:290`: *"Log context is redacted before it is stored, so nothing credential-shaped reaches `wp_options` or a database dump."* `readme.txt:231`: *"The connector redacts its own log contexts…"*

`WCAC_Logger::log()` stores `$context` verbatim:

```php
'message' => (string) $message,
'context' => $context,
```

No key filter, no value scrubbing, no length cap. No current call site passes anything sensitive, so this is not a live leak — but it is an unearned guarantee in shipped user-facing documentation, and the next contributor who reads `readme.txt:290` and passes `array( 'args' => $args )` (which for the Campus Connect request contains `headers['Authorization'] = 'Basic <base64 of user:pass>'`) will write the credential straight into `wp_options`, where `render_log()` will not display it but `wp option get wcac_log` and every DB dump will contain it. Either implement the redaction or delete both claims.

### 6. The Airtable token field is weaker against autofill than the Central field it sits above — `class-wcac-admin.php:562`

```php
<input type="password" id="wcac-key" name="api_key" class="regular-text" autocomplete="off" …
```

The plugin's own comment at `admin.php:646-651` states the threat precisely: *"Chrome and Safari fill password fields regardless of `autocomplete="off"`, and a credential box inside the settings form invites this site's own wp-admin login to be filled in and then base64'd into an `Authorization` header sent to central.wordcamp.org."* That reasoning was used to give the Central credential its own form and `autocomplete="new-password"` — and then not applied to the `type="password"` field five sections above it, which still carries only the `off` value the comment declares ineffective.

**Trigger:** admin opens `admin.php?page=wcac` with a browser password manager that has a saved login for the site; the manager fills `#wcac-key` with the wp-admin password; the admin clicks Save Changes without noticing. **Consequence:** `save()` (`settings.php:260`) stores it as `api_key`, and the next Airtable call sends `Authorization: Bearer <this site's admin password>` to `api.airtable.com` (`airtable.php:102`), logging it against a third party. Change to `autocomplete="new-password"`.

---

## NOTES (no action strictly required)

- **`admin.php:377`** — `$settings = WCAC_Settings::all()` holds the plaintext password in a local for the entire ~330-line render, which defeats the stated intent of the comment at `:652-655` (*"Only 'source' is kept — the credential array carries the password, and there is no reason for it to stay alive for the rest of the page"*). Only exploitable under Xdebug/`display_errors` local dumping, but the mitigation as written is illusory.
- **Central username asymmetry** — treated as sensitive when logging (`admin.php:200-202`, `sync.php:695-696`: *"a third party's WordPress.org login and log lines are pasted into public issues"*), yet passed in argv at `cli.php:418` (visible in `ps` and shell history), printed nowhere but rendered in an HTML `value` at `admin.php:673`. Pick one posture.
- **Fingerprint exposure is fine.** 12 hex chars of SHA-256 salted with `wp_salt('auth')`, shown at `admin.php:690` and printed at `cli.php:152,364,445`. Not reversible without the salt, and an attacker holding the salt already holds `wp-config.php`.
- **Airtable error bodies are safe.** `airtable.php:139-146` returns the response body in the message; Airtable's error envelope (`{"error":{"type":"UNAUTHORIZED"}}`) never echoes the `Bearer` header.
- **`http_api_debug` / `pre_http_request` exposure** (`readme.txt:225-231`) is correctly documented as unfixable from inside the plugin and is not counted as a finding here.

---

## Lens: Data loss

## Audit: damage to the 142 hand-imported rows in `tbld8niqsLWyNbcVF`

Scope: `WCAC_Mapper::campus_event()` and its coercers, `WCAC_Airtable::upsert()`, and `WCAC_Sync::job_campus_connect()`. No files were edited. `php -l` passes on all ten PHP files (unchanged by me).

### What the lens asked, answered plainly first

**The omit-when-empty rule is implemented with strict emptiness, correctly.** `class-wcac-mapper.php:744-748` is `if ( null !== $value && '' !== $value )`. `grep -n "empty("` over the whole mapper returns exactly one hit, and it is the comment on line 741. No `empty()`, no `if ( $value )`, no falsy test anywhere in the campus write path.

**A missing response key cannot blank a column.** `pick()` (`:810`) returns `''` for an absent key; `campus_text('')` → `''`, `campus_int('')` → `null` (`is_numeric('')` is false), `campus_date('')` → `null` (`:345`), `campus_url('')` → `''`. All four are dropped by `$put`. Verified for every one of the twelve `$put` calls at `:772-792`.

**A non-numeric value cannot become 0.** `campus_int()` (`:311-319`) gates on `is_numeric( trim( (string) $value ) )`, so `'n/a'`, `'TBD'`, `'~300'`, `'1,200'` all return `null` and are omitted, and `$number()` (`:761-769`) counts each one into `int_unparsed`, which is logged (`sync:1261`) and shown in the dry run. The adversarial review's Finding 1 is genuinely fixed.

**A chunk failure cannot leave a row half-written.** Airtable rejects a 10-record PATCH atomically, and `upsert()` (`airtable:187-249`) sends one chunk per request, so the failure unit is 10 whole rows untouched, never a partially-written row. PATCH-not-PUT is correct (`airtable:199-204`).

Also correct and worth not re-litigating: `campus_url()` refuses scheme-less input so `esc_url_raw()` cannot fabricate `http://` over a curated `https` cell (`:386-400`); `campus_status()` default-denies and the writable set is `array_intersect`ed with the known label map (`:413-419`) while `settings:304-320` refuses to store an extra label the mapper cannot emit, so `typecast` cannot mint an arbitrary singleSelect option; non-scalars return `''`/`null` everywhere, so a shape change cannot write `Array`; dedupe happens before chunking (`sync:1102-1108`); the volume guard runs before the write (`sync:1145`).

Four real defects survive.

---

### 1. Boolean `false` is silently dropped, so `Series Event` and `Actual Attendees` can only ratchet upward — HIGH

`class-wcac-mapper.php:316-318`. `trim( (string) false )` is `''`, and `is_numeric( '' )` is false, so `campus_int( false )` returns `null` and `$put` omits the key (`:783`, `:788`). `campus_int( true )` takes the other path: `trim( (string) true )` is `'1'` → writes `1`. Verified under PHP.

Input: a report row `{"ID":1234, "Series Event": false}` — an ordinary JSON boolean for a yes/no report column, and the shape a `number`-precision-0 field most plausibly receives from a `(bool)` meta.

Consequence: a curated row that is wrongly flagged `Series Event = 1` can never be corrected back to 0 by the sync. The field is write-once-upward. This defeats the exact invariant the code claims for itself at `:741-743` ("dropping them would mean a wrong non-zero count could never be corrected back down"). The same applies to `Actual Attendees: false`.

It is also invisible. `$number()`'s miss counter (`:765`) only fires when `'' !== trim( (string) $raw )`, which for `false` is `''` — so `int_unparsed` stays 0, `sync:1261` logs nothing, and the dry run's "numbers not numeric" row reads 0. There is no observable difference between "the report says false" and "the report omitted the key".

Fix shape: handle `is_bool( $value )` explicitly in `campus_int()` before the string cast.

### 2. A calendar-invalid `Y-m-d` is passed through unvalidated and can overwrite a curated date — HIGH

`class-wcac-mapper.php:353-354`: the middle branch matches `/^\d{4}-\d{2}-\d{2}$/` and assigns `$date = $raw` verbatim. The other two branches are validated (`gmdate()` on an int; `false === $stamp` check on `strtotime()`); this one is not. The sanity window at `:368` is a **string** comparison, so it cannot catch it — I ran it:

- `'2026-02-30'`: `< '2006-01-01'` false, `> '2031-09-02'` false → passes.
- `'2026-13-45'`: same → passes. A month of `13` cannot be ordered by string comparison against a real ceiling date.

Input: `"Start Date (YYYY-mm-dd)": "2026-02-30"` (an off-by-one in an upstream date builder, or a hand-entered organiser value the report passes through).

Consequence, with `typecast => true` (`sync:1160`): Airtable's date parser is lenient and rolls `2026-02-30` to `2026-03-02`, so a hand-imported Start Date is replaced by a **wrong but entirely plausible** date — the precise failure mode `campus_int()` was written to prevent (`:296-303`), not applied to `campus_date()`. If Airtable instead rejects it, the whole 10-record chunk 422s and ten rows go unwritten. `date_unparsed` stays 0 either way, so neither outcome is counted.

Fix shape: `checkdate()` on the three captured parts in that branch.

### 3. The genuine-zero write is the last unbounded overwrite path, and nothing at runtime can see it — HIGH, conditional on the upstream shape

`class-wcac-mapper.php:318` returns `int 0` for `0` / `"0"`; `:744-748` emits it; `sync:1149` sends it. That is a deliberate decision and the rationale at `:741-743` is sound in isolation. But the premise it rests on — that the report sends `''`, not `0`, for unset meta — is nowhere verified. The docblock that establishes it (`:60-64`, "Central returns empty strings rather than nulls for unset meta") is a statement about the **wp/v2 meta payloads**, not about the WordCamp Reports framework, which builds its own array and is explicitly described elsewhere in this file as outside the plugin's control.

Input: `{"ID":1234, "Actual Attendees": 0}` for an event that has not happened yet.

Consequence: the first cron run overwrites `Actual Attendees` on up to all 142 curated rows with 0, and logs `Campus Connect: 142 rows read, 142 written (0 created, 142 updated)` (`sync:1220-1229`). Nothing counts it — `0` is not "unparsed" (`:765`), not a status gap, not a volume change. `state['errors']` stays 0. WPCC-Tracker then renders "0 attendees" everywhere.

The sibling field is worse because it needs no integer at all: `:782` routes `Number of Anticipated Attendees` through `campus_text()`, and `campus_text( 0 )` returns the string `"0"`, which is non-empty and is therefore written straight over a curated `"80-100"`.

The only defence is an operator running `--dry-run` first and reading the by-field table (`cli:995-1010`), and **nothing enforces that ordering**: `--preflight` is a hard gate (`sync:983-990`), but `campus_run()` and the "Sync Campus Connect now" button will both write on a table that has never been diffed. Given that this table is the one asset the whole feature exists to protect, the first write deserves the same gate the merge column got — or, cheaply, a per-field count of emitted zeroes logged once per run, so the 142-row zeroing is visible in the log the moment it happens rather than in WPCC-Tracker a week later.

### 4. A partially failing chunk resets the failure latch, so the same ten rows can starve forever without ever escalating — MEDIUM

`sync:1214` tallies, then `sync:1265` calls `cc_ok( $written, count( $rows ) )`, and `cc_ok()` (`sync:625-639`) sets `cc_fails => 0`, `cc_block => 0`, `cc_block_why => ''`.

Input: one row carries a value Airtable rejects — the invalid date from finding 2, a drifted singleSelect label, anything. Its chunk 422s on every run; the other thirteen succeed. `$written` is 132, so the all-chunks-failed branch at `sync:1201` is not taken.

Consequence: those ten rows are never updated again; `cc_fails` is reset to 0 on every run so it can never reach the five needed to latch (`sync:714`); the admin error banner never appears; and `cc_ok()` does not touch `cc_last_error`, so the admin row at `admin:477-481` shows whatever stale string an unrelated earlier failure left behind, or nothing at all. The comment at `sync:1198-1200` states this exact hazard and then guards only the total-failure case.

To be fair to the implementation, this is *not* "no signal": each failing chunk logs an error naming its WordCamp IDs (`sync:1185-1193`), the cumulative Errors counter advances (`admin:428`), and the admin shows `132 of 142 rows written` (`admin:466-472`). What is missing is anything that **latches, alerts, or distinguishes a permanent starvation from a one-off** — and the log is a 200-entry ring that a busy backfill will flush.

Fix shape: track consecutive runs with a non-empty `$failed` separately from `cc_fails`, and latch on that; or at minimum have `cc_ok()` write a `cc_last_error` describing the partial failure instead of leaving a stale one.

---

### Lower-severity observations

- `sync:1166` `if ( is_wp_error( $sent ) )` is unreachable: with `continue_on_error => true`, `upsert()` (`airtable:206-235`) never returns a `WP_Error`. Harmless dead defence, but it means the campus path has no live use of the partial-progress data that `airtable:208-217` goes to trouble to attach.
- `$put`'s guard is `null !== $value && '' !== $value`, and in PHP `'' !== false` is **true** — a literal boolean `false` would be *written*. Unreachable today (every coercer returns `string|int|null`), but a trap for the next field routed through a helper that returns a bool.
- The preflight (`cli:748-838`) validates the merge column's *form* — present, numeric, unique — never its *correctness*. A curated row whose hand-typed `WordCamp ID` is a typo that happens to equal another camp's real ID passes preflight cleanly and is then overwritten wholesale with a different event's Name, City, Country, dates and URL. Only `--dry-run` can surface that, which is a second argument for gating the first write on it.
- `'Organizer Name'` is listed in `CAMPUS_KEYS_REQUIRED` (`:246`) but `campus_event()` never reads it. If the report drops that key, `sync:1017-1025` warns "Those columns will not update" about a column that no mapping targets.
- `sync:1061-1066` persists `cc_keys` **before** the volume gate at `:1145`. A run refused for implausible growth still consumes the key-drift comparison, so the next run compares against the refused run's key set and will not warn about a rename that arrived in the same regression.

---

## Lens: Cross-file contract

php -l passes on all 10 files. Full cross-file audit below.

---

## `php -l` results (all pass)

```
wordcamp-airtable-connector.php    No syntax errors detected
uninstall.php                      No syntax errors detected
includes/class-wcac-settings.php   No syntax errors detected
includes/class-wcac-cli.php        No syntax errors detected
includes/class-wcac-logger.php     No syntax errors detected
includes/class-wcac-airtable.php   No syntax errors detected
includes/class-wcac-mapper.php     No syntax errors detected
includes/class-wcac-source.php     No syntax errors detected
includes/class-wcac-sync.php       No syntax errors detected
includes/class-wcac-admin.php      No syntax errors detected
```

Caveat worth recording: the only PHP on this machine is **8.5.7**, so `php -l` proves the files parse but does *not* prove the PHP 7.4 target. I grepped separately for PHP 8-only constructs (`?->`, `match(`, `enum`, `readonly`, `#[`, constructor promotion, `str_contains`/`str_starts_with`/`array_is_list`, trailing comma in parameter lists) — **zero hits**. The two newest constructs used are `?array $body = null` (`class-wcac-airtable.php:92`, PHP 7.1+) and array-dereferencing a static call, `WCAC_Settings::central_credential()['source']` (`class-wcac-admin.php:298`, `:657`) and `$this->state()['last_sync']` (`class-wcac-sync.php:392`), both PHP 5.4+. All 7.4-clean.

## What the lens verified clean

- **Every intra-class `$this->…()` and `self::…()` call resolves** to a method defined in that class (checked mechanically across all seven classes; zero misses).
- **Every cross-object call targets a `public` member.** `$sync->queue()/state()/pending()/camps()/clear_queue()/run_slice()/enqueue_full()/enqueue_incremental()/queue_campus_now()/allow_campus_growth_once()/set_campus_preflight()/clear_campus_block()` are all public in `class-wcac-sync.php`; `WCAC_Sync::LOCK` (`:22`) is a visibility-less (public) const, so `get_transient( WCAC_Sync::LOCK )` at `class-wcac-cli.php:1161` is legal. The two places that need a `protected` behaviour re-implement it locally rather than reaching in — `class-wcac-admin.php:386-387` and `class-wcac-cli.php:1267-1270` both mirror `WCAC_Sync::cc_blocked()` (`class-wcac-sync.php:477-482`) with the identical 12-hour arithmetic. Correct and consistent.
- **Every arity matches.** `WCAC_Mapper::campus_event( array, array )`, `campus_status( $slug, array )`, `campus_key_report( array, $depth = 25 )`, `campus_text( $value, $limit = 0 )`, `WCAC_Airtable::list_records( $table, array $fields, $max_pages = 50 )`, `upsert( $table, array, $merge_field, array $options )`, `WCAC_Sync::set_campus_preflight( $ok, $table )` — all call sites agree.
- **Every settings key read is declared with a default.** Reads across the tree are `api_key, base_id, campus_extra_status, lookback_hours, source_root, sync_campus_connect, sync_children, sync_meetups, sync_wordcamps, time_budget, central_user, central_app_password`, plus `table_for()` called with `wordcamps|meetups|sessions|speakers|sponsors|campus_connect` → `tbl_*`. All eighteen are in `WCAC_Settings::defaults()` (`class-wcac-settings.php:26-45`).
- **Every state key read is declared.** `cc_block, cc_block_why, cc_fails, cc_last_ok, cc_last_error, cc_last_rows, cc_last_written, cc_high_rows, cc_keys, cc_series, cc_unmapped, cc_absent, cc_preflight_ok, cc_preflight_table, cc_growth_once` all appear in `WCAC_Sync::state()` (`class-wcac-sync.php:130-144`), and both external readers go through a defaulting accessor anyway (`WCAC_Admin::state_value()` `:775`, `WCAC_CLI::cc()` `:1257`).
- **Every constant is defined or `defined()`-guarded.** `WCAC_VERSION/FILE/DIR/URL/CRON_HOOK/CONTINUE_HOOK` defined at `wordcamp-airtable-connector.php:19-33`; `WCAC_CENTRAL_USER`, `WCAC_CENTRAL_APP_PASSWORD`, `WCAC_ALLOW_INSECURE_CENTRAL` guarded at `class-wcac-settings.php:105,385` and `class-wcac-source.php:384`. `defined( 'WCAC_Mapper::CAMPUS_STATUS' )` at `class-wcac-settings.php:307` is valid for a class constant (verified on this PHP: `defined("A::B")` → `true`). `uninstall.php:31,37-38` hardcodes `'wcac_run_queue'`/`'wcac_continue_queue'`/`'wcac_lock'` and the five option names — correct, since the plugin file is not loaded there, and each string matches its constant's value.
- **Job dispatch is total.** The only `'to'` values pushed anywhere are `wc, mu, kid, cc` (`class-wcac-sync.php:230,234,240,277,281,288,305,574,839,877,913`); `run_job()` (`:792-805`) handles all four.
- Text domain is `wordcamp-airtable-connector` in all 114 i18n calls; every non-test file carries the `defined( 'ABSPATH' ) || exit;` guard; header version, `WCAC_VERSION` and readme `Stable tag` are all `1.1.0`.

## Findings

### 1. The preflight gate can only be satisfied from WP-CLI, and the admin's own readiness signal does not include it

- Gate: `class-wcac-sync.php:985` — `if ( empty( $state['cc_preflight_ok'] ) || (string) $state['cc_preflight_table'] !== $table )`.
- Only writer of a passing stamp: `class-wcac-cli.php:829` — `$sync->set_campus_preflight( true, $table );` inside `private function campus_preflight()`. `WCAC_CLI` is only loaded when `defined('WP_CLI') && WP_CLI` (`wordcamp-airtable-connector.php:43-46`).
- Readiness used by the admin: `WCAC_Settings::campus_connect_ready()` (`class-wcac-settings.php:154-158`) = credential + toggle + table ID. It does not consult the stamp.

Input that triggers it: a site with no shell access. Operator ticks *Campus Connect events*, saves a Central credential, leaves `tbl_campus_connect` at its default. `campus_connect_ready()` → true, so `class-wcac-admin.php:448-449` prints **ready**, `:535-537` renders the *Sync Campus Connect now* button, and `:172` lets `cc_now` through to `queue_campus_now()`.

Observable consequence: `cc_preflight_ok` defaults to `0` (`class-wcac-sync.php:142`), so every queued job takes the skip branch at `:986-989` — `tally( 0, 0, 1 )` plus a log line. This fires not only on the dedicated button but on every press of *Sync changes now* and every `wp wcac sync`, because `enqueue_incremental()` appends a `cc` job on the same `campus_connect_ready()` test (`class-wcac-sync.php:287-289`). The **Errors** counter (`class-wcac-admin.php:429-430`) climbs on each tick while the Status table keeps saying *ready*, *Campus Connect last run: never*, and — because the skip path does not route through `cc_fail()` — **Campus Connect last error stays blank** (`cc_last_error` is never written, `class-wcac-admin.php:477-481`). `WCAC_CLI::status()` does surface the gate, with a dedicated `campus preflight` row and even a `stale: the table ID changed since it was run` state (`class-wcac-cli.php:122-131,154`); the admin Status table (`class-wcac-admin.php:410-527`) has no equivalent row. The truth is only in the rolling log line at `class-wcac-sync.php:987`, which does render on the page — so it is discoverable, not invisible, but the two status surfaces disagree and the one the operator is looking at is the wrong one.

### 2. `wcac_central_credential_changed` is fired unconditionally, contradicting the contract the bootstrap documents

- Contract, `wordcamp-airtable-connector.php:82-86`: *"WCAC_Settings fires this from save(), set_secret() and forget_central(), and only when the stored credential's fingerprint differs. It is deliberately not fired on every settings save: a form saved for an unrelated reason must not re-arm a job that is failing to authenticate."*
- `WCAC_Settings::save()` honours it — `class-wcac-settings.php:332-334` compares `credential_fingerprint( $current )` against `credential_fingerprint( $clean )`.
- `WCAC_Settings::set_secret()` does **not** — `class-wcac-settings.php:216-218` fires on key name alone, with no comparison. Same at `forget_central()`, `class-wcac-settings.php:242`.

Input that triggers it: Campus Connect is latched off by a 401 (`class-wcac-sync.php:676-677` → `cc_block()`). The operator opens the *Central credential* form to read the fingerprint and presses **Save credential** without touching anything. The username field always renders its stored value (`class-wcac-admin.php:672-673`), so `isset( $post['central_user'] )` is true at `class-wcac-admin.php:305`, and `:311` calls `set_secret( 'central_user', $user )` with a byte-identical value.

Observable consequence: the action fires → `wordcamp-airtable-connector.php:87-92` → `WCAC_Sync::clear_campus_block()` → `cc_unblock()` (`class-wcac-sync.php:525-539`) zeroes `cc_block`, `cc_block_why` and `cc_fails`. The next cron tick re-sends the same rejected Basic auth to central.wordcamp.org, and the five-strike counter restarts from zero — which is precisely what `class-wcac-sync.php:546-548` says must not happen ("an operator editing an unrelated field must not put a 401ing job back into rotation, least of all when browser autofill may just have replaced the password"). Secondary effect on a genuine credential change: `class-wcac-admin.php:311` and `:322` both call `set_secret`, so the action fires **twice**, constructing `WCAC_Sync` twice and doing two read-modify-write cycles on `wcac_state`.

### 3. `CAMPUS_KEYS_REQUIRED` declares a key the mapper never consumes

- Declared required: `'Organizer Name'`, `class-wcac-mapper.php:246`.
- `campus_event()` (`class-wcac-mapper.php:717-801`) reads exactly twelve keys — `ID, Name, Status, Start Date (YYYY-mm-dd), End Date (YYYY-mm-dd), Venue Name, _venue_city, _venue_country_name, Number of Anticipated Attendees, Actual Attendees, Series Event, Created, URL` — and never `pick( $row, 'Organizer Name' )`. There is no Organizer field in the emitted `$fields` array and none in the CLI's read-back list (`class-wcac-cli.php:904-917`).

Input that triggers it: Central's report drops or renames `Organizer Name` (a plausible outcome of the very capability/schema drift this const exists to catch).

Observable consequence: `campus_key_report()` puts it in `missing` (`class-wcac-mapper.php:512`), so `class-wcac-sync.php:1018-1027` logs a **warn**: *"Campus Connect report is missing 1 documented key(s): Organizer Name. Those columns will not update."* — naming a column that does not exist in `tbld8niqsLWyNbcVF` and was never going to update. `WCAC_CLI::report_key_drift()` raises the same false alarm as a `WP_CLI::warning` (`class-wcac-cli.php:529-537`), in the middle of the runbook, immediately before the dry-run the operator is supposed to read carefully. The other twelve required keys all map 1:1 to a consumed value, so this is the single orphan.

### 4. Two spellings for the same sentinel, both unreachable

`class-wcac-sync.php:1088` uses `'(blank)'` where `class-wcac-cli.php:588` uses `'(empty)'`, for the same condition (`'' === $mapped['slug']`) feeding the same `unmapped`/`absent` buckets — the admin's *Campus Connect Status gaps* row (`class-wcac-admin.php:489-497`) and the CLI's status-gap tables would label the identical row differently. Both are in fact dead: `campus_status()` returns `why => 'missing'` for an empty raw value (`class-wcac-mapper.php:441-446`) and never `'unmapped'`/`'absent'`, so a blank slug can never reach either bucket. Cosmetic, but the divergence should collapse to one string if the branch is kept at all.

### 5. `cc_now` reports success on a no-op

`class-wcac-admin.php:178-179` discards the return of `queue_campus_now()` and always sets `$notice = 'queued'`. `queue_campus_now()` returns `false` when a `cc` job is already pending (`class-wcac-sync.php:569-571`). Pressing *Sync Campus Connect now* twice shows "Sync queued. It will run in the background." both times while only one job exists. `WCAC_CLI::campus_run()` handles the same return correctly, printing the distinct "A Campus Connect job was already pending" line (`class-wcac-cli.php:1173-1177`).

---

## Lens: Regression vs the five existing syncs

AUDIT: existing five syncs, behaviour vs `git HEAD` (740a504). No files edited.

## What is genuinely unchanged (verified byte-for-byte, not by eyeball)

- `WCAC_Mapper::wordcamp/meetup/session/speaker/sponsor/now` — byte-identical.
- `WCAC_Airtable::request()` and `::ping()` — byte-identical.
- `WCAC_Sync::job_wordcamps()`, `job_meetups()`, `job_event_data()`, `sync_collection()`, `tally()`, `push()`, `set_queue()`, `error_status()` — no diff hunk touches old lines 380-506 or anything after old line 506; `run_job()`'s only change is an added `case 'cc'`.
- `WCAC_Source::event_posts()`, `term_map()`, `wordcamps()`, `meetups()`, `url()` — untouched.
- `WCAC_Source::get()` signature widening **is** backward compatible: with `$overrides = array()` the three fields resolve to `timeout 25`, `redirection 5`, `array_merge(array('Accept'=>'application/json'), array())`. Identical request for all five existing call sites (`class-wcac-source.php:124, 147, 403, 451`).
- `WCAC_Airtable::upsert()` signature widening is backward compatible for the three existing call sites (`class-wcac-sync.php:850` WordCamps, `:924` Meetups, `:1344` `sync_collection()` for sessions/speakers/sponsors): `typecast` still defaults to `true`, `continue_on_error` defaults to `false` (old abandon-remaining-chunks semantics), the added `errors` key is additive, and `ids`/`created`/`updated` are unchanged — `job_wordcamps()` still gets `$sent['ids'][(string)$wcid]` for the camp cache.
- `$last_request` instance→static (`class-wcac-airtable.php:56`) is strictly more conservative and a no-op for the five syncs: `WCAC_Sync` holds exactly one `WCAC_Airtable`, and the CLI `sync` command builds one `WCAC_Sync` for the whole loop, so no existing path ever had two clients in one process.

## Findings

**1. `class-wcac-settings.php:278-288` — an intentional `source_root` edit is now silently discarded, and the five syncs keep hitting the old host.**
The new guard rejects any non-`https` root. Input: the operator moves their staging Central from `http://old.test` to `http://new.test` (the field's own description at `class-wcac-admin.php:640` says "Only change this to point at a staging copy of WordCamp Central") and presses Save. `esc_url_raw` yields `http://new.test`, `root_scheme_allowed()` returns false, `$root = $current['source_root']`, and `$clean['source_root']` is written back as `http://old.test`. Consequence: the admin notice says **"Settings saved."** (`class-wcac-admin.php:handle_save` redirects to `saved` unconditionally), the input box re-renders the *old* value, and WordCamps/Meetups/Sessions/Speakers/Sponsors keep crawling `old.test` indefinitely. The only signal is a `warn` line buried in the log ring. Same trigger with a scheme-less entry: typing `central.wordcamp.org` used to be normalised by `esc_url_raw` to `http://central.wordcamp.org` and accepted; it is now rejected. This restriction exists solely because the Campus Connect job attaches an `Authorization` header, yet it is enforced globally — including on sites with `sync_campus_connect = 0` and no credential stored, where no header is ever sent. Scoping the check to `central_ready()`, or surfacing a `source_root_rejected` notice instead of `saved`, would keep the credential protection without the silent config no-op.

**2. `class-wcac-cli.php:205-223` — `wp wcac test` now exits non-zero for a reason unrelated to Airtable.**
Baseline: reachable Airtable ⇒ `WP_CLI::success('Airtable responded.')`, exit 0, always. Now, if any Central credential is stored and `central_identity_probe()` fails, the command reaches `WP_CLI::error(...)` and exits **1** even though the ping succeeded. Input: a monitoring cron or CI step running `wp wcac test` to confirm the five syncs can still write to base `appoiPJkMFdnJEmfa`, on a site whose Central application password was revoked last week. Consequence: the check goes red and pages someone about Airtable when Airtable is fine. It also adds a synchronous 20s-timeout HTTP round trip to `central.wordcamp.org/wp-json/wp/v2/users/me` to a command that previously touched only Airtable. A `WP_CLI::warning` plus `success` would report the same fact without changing the contract of an existing command.

**3. `class-wcac-sync.php:376` — a killed Campus Connect job now stalls the five syncs' queue drain for up to 5 minutes instead of 1.**
`run_slice()` sets the lock to `max(60, $budget*3)` at :348 and deletes it in `finally` at :382; the new line raises it to `max(300, $budget*3)` when the job at the head is `cc`. The `finally` covers normal errors and exceptions, but not a process kill. Input: the operator presses **Process queue now** (`class-wcac-admin.php:148`, `run_slice(20)` under `admin-post.php`) while a `cc` job sits at the head; the report fetch plus ~15 PATCHes, or a single Airtable 429 (30s `sleep()` inside `request()`), pushes the request past a 30s PHP-FPM `request_terminate_timeout` — precisely the scenario `WCAC_Source::report_timeout()` and the `cc_now` handler comment are written to avoid. Consequence: `wcac_sync_lock` survives with a 300s TTL, so every `wcac_cron_tick` for the next ~5 minutes returns `processed => 0` at :343 and no WordCamps/Meetups/Sessions job advances. Because the kill also skips the `wp_schedule_single_event(WCAC_CONTINUE_HOOK)` at :400, recovery waits on the 5-minute recurring tick, which can itself land just inside the lock window — worst case the five syncs idle ~10 minutes where the pre-change worst case was ~5. Real but bounded; the fix is to extend the lock only under `WP_CLI`/`wp_doing_cron()`, where `report_timeout()` already grants 45s and there is no FPM ceiling.

**4. `class-wcac-cli.php:1182-1197` — `wp wcac campus-connect --run` can execute up to 50 WordCamps/Meetups/child jobs and write to all five existing tables, reporting none of it.**
`queue_campus_now()` (`class-wcac-sync.php:571`) returns `false` when a `cc` job is already pending, and does **not** move it to the head. `campus_run()` then loops `run_slice(1)` until no `cc` job is queued. Input: the admin **Sync Campus Connect now** button (or a `wp wcac sync --full`, which appends `cc` after `wc`/`mu`) leaves a `cc` job behind a backlog, and the operator then follows the runbook with `wp wcac campus-connect --run`. Consequence: up to 50 `wc`/`mu`/`kid` jobs are drained — each one a real fetch-and-upsert into `tblLBVsO4rU2oj2Ki`, `tblbbyWvUsxF9CHRj`, `tblO5yMO8waGwqO1l`, `tbliYsOf6pAZLOzfd`, `tblPkKmjbgGpazI6K` — from a command named `campus-connect`, and the summary table it prints (`:1204-1211`) reports only `cc_last_rows`/`cc_last_written`, so those writes and any errors they tally into `wcac_state['errors']` are invisible in the command's own output. Per-job behaviour of the five syncs is unchanged; the new thing is a trigger that advances and mutates them under a Campus-Connect-labelled command. Either make `queue_campus_now()` unshift unconditionally (dropping the pending duplicate first), or have `campus_run()` report how many non-`cc` jobs it drained.

**5. Informational — `class-wcac-airtable.php:207-217` replaces the `WP_Error` data on the non-`continue_on_error` path.** When a chunk fails with `continue_on_error` off (the default all three existing call sites use), the original error is re-wrapped: code and message are preserved, but `get_error_data()` changes from `request()`'s `array('status' => $code)` (or `''`, for a `wp_remote_request` transport failure) to `array('status','created','updated','chunk')`. I checked every existing consumer — `class-wcac-sync.php:852`, `:926`, `:1346` all use `get_error_message()` only, and no existing path calls `error_status()` on an *upsert* error (`:823` and `:897` classify *source* errors). So no existing call site's behaviour differs today. It is a latent trap only if a future edit starts inspecting upsert error data expecting the raw transport error.

---
