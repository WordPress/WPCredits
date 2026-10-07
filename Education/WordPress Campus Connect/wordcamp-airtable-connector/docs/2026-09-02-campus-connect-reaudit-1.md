# Campus Connect sync — re-audit after fix round 1 (2026-09-02)

Most originally-reported findings are CLOSED. The fix pass introduced NEW defects, two HIGH.
Anything marked OPEN / PARTIALLY CLOSED / NEW below is outstanding.

---

## Lens: Regression

I have the evidence I need. Here is the re-audit.

---

# Re-audit — lens: the five existing syncs (WordCamps, Meetups, Sessions, Speakers, Sponsors)

Method: current working tree vs baseline `740a504`, plus executable harnesses (`/tmp/wcactest/`) that stub WordPress and compare real behaviour, not just diff text. No file was edited.

## Shared-symbol equivalence — proven, not eyeballed

I extracted each method body from both trees and compared byte-for-byte, then ran the ones that changed.

| Symbol | Verdict |
|---|---|
| `WCAC_Mapper::wordcamp/meetup/session/speaker/sponsor/now` | **byte-identical**; so are every shared helper (`text`, `block`, `int_or_null`, `float_or_null`, `stamp_to_date`, `stamp_to_datetime`, `gmt_to_datetime`, `terms`, `pick`). D1/D2 landed only in `campus_int()`/`campus_date()`. |
| `WCAC_Airtable::request()`, `::ping()` | **byte-identical.** |
| `WCAC_Airtable::upsert()` | Widened, backward compatible. With 3 args `$typecast` resolves `true`, `$continue` `false`; `created`/`updated`/`ids` unchanged, `errors` additive. All three existing call sites (`class-wcac-sync.php:916`, `:990`, `:1469`) use `get_error_message()` only, and `error_status()` is never called on an upsert error (only `:889`, `:963`, `:726` — all source errors). |
| `WCAC_Airtable::$last_request` instance→static | No-op for the five syncs; one client per process on every existing path. |
| `WCAC_Sync::run_slice()` | Changed — see R3 below. |
| `WCAC_Sync::job_wordcamps/job_meetups/job_event_data/sync_collection/tally/push/queue/set_queue/clear_queue/pending/error_status/set_state` | **byte-identical.** `run_job()`'s only change is `case 'cc'`. `state()`'s only change is added `cc_*` defaults. |
| `WCAC_Sync::without_queued_duplicates()` | Filters `'cc' === $job['to']` only; `wc`/`mu`/`kid` pass through in order. |
| `WCAC_Settings::all()`, `::get()`, `::is_configured()`, `::table_for()` | **byte-identical.** |
| `WCAC_Settings::save()` | Changed — see R1 and NEW-2. |
| `WCAC_Source::get()` | Widened. Ran both trees side by side: `wordcamps(2, $since)` and `meetups(1, null)` produce **byte-identical URLs and identical `$args`** (`timeout 25`, `redirection 5`, `headers {Accept: application/json}`, same UA). |
| `WCAC_Source::event_posts()`, `::term_map()`, `::url()` | **byte-identical.** |
| `WCAC_Logger::log()` | Now stores `redact($context)`. Ran it against the only four contexts the five syncs ever pass (`array( 'page' => $page )` at `class-wcac-sync.php:894,920,968,994`): stored **unchanged**. No other call site in the plugin passes a context at all. |
| `WCAC_CLI::sync()` | Differs from baseline by one character: an em dash became `--`. Behaviour identical. `WCAC_CLI::clear()` byte-identical. |

Static checks: every `WCAC_*::member` reference in the tree resolves to a defined member (`/tmp/check.php`), and no cross-class call reaches a non-public method (`/tmp/vis.php`). `php -l` clean on all ten files. No PHP 8-only syntax (the `match(` grep hits are all `preg_match(`).

---

## The four Regression findings

### R1 — `source_root` silently discarded → **CLOSED**

`class-wcac-settings.php:295` resets `self::$rejected`; the guard at `:315-319` appends `'source_root'`; `::last_rejected()` at `:383`. `class-wcac-admin.php:108-121` reads it immediately after `save()` and redirects to `save_rejected` with a whitelisted `wcac_rejected` key, and `::rejected_notice()` (`:403`) prints a `notice-error` naming **Source site**, the https rule, and `WCAC_ALLOW_INSECURE_CENTRAL`.

Harness output:

```
stored='https://central.wordcamp.org'  input='http://new.test'       -> stored now='https://central.wordcamp.org'  rejected=["source_root"]
stored='https://central.wordcamp.org'  input='central.wordcamp.org'  -> stored now='https://central.wordcamp.org'  rejected=["source_root"]
```

The finding offered two remedies; the notice one is implemented correctly. The global scope of the https rule (enforced even with `sync_campus_connect = 0`) is unchanged, which the finding accepted as the alternative.

### R2 — `wp wcac test` exit code → **CLOSED**

`class-wcac-cli.php:219-227`: `WP_CLI::error` is now `WP_CLI::warning` with identical text, followed by `WP_CLI::success( 'Airtable responded. Campus Connect cannot authenticate…' )` and `return`. Reachable Airtable ⇒ exit 0 on all three branches (`:212`, `:226`, `:232`). The docblock at `:192-197` records the contract so it is not re-tightened.

Residual, not an exit-code regression, still worth knowing: the command still makes a synchronous 20s-timeout call to `central.wordcamp.org/wp-json/wp/v2/users/me` whenever a credential is stored, so a monitoring cron's wall time is still affected. `clear_campus_block()` on the success path is write-free unless the latch is actually set (`cc_unblock()` early-returns), so `wp wcac test` remains read-only in the normal case.

### R3 — 300s lock stalls the five syncs after a kill → **PARTIALLY CLOSED**

`class-wcac-sync.php:383`:

```php
$long_lock = ( defined( 'WP_CLI' ) && WP_CLI ) || wp_doing_cron();
```

Measured lock TTLs with a `cc` job at the head:

```
web request   lock TTLs: ["wcac_lock=60"]
wp_doing_cron lock TTLs: ["wcac_lock=60","wcac_lock=300"]
```

The **reported trigger is genuinely closed**: "Process queue now" and the `cc_now` button run under `admin-post.php` with neither `WP_CLI` nor `DOING_CRON`, so the lock stays at `max( 60, $budget * 3 )` = 60s, exactly as baseline.

What is **not** closed is the consequence, on the most common deployment. The comment at `:377-382` asserts cron is a place with "no web request to lose". That is false for WordPress's default WP-Cron: `wp_doing_cron()` is true inside `wp-cron.php`, which `spawn_cron()` fetches over loopback HTTP and which PHP-FPM therefore terminates at `request_terminate_timeout` like any other request. A `finally` does not run on an FPM kill or a `max_execution_time` fatal. And `WCAC_Source::report_timeout()` grants the report **45s** on that same `wp_doing_cron()` branch, so the default cron path is the one *most* likely to exceed a 30s ceiling. On such a host the original scenario reproduces verbatim: `wcac_sync_lock` survives with a 300s TTL, every subsequent `wcac_cron_tick` returns `processed => 0` at `:344`, and no WordCamps/Meetups/Sessions job advances for ~5 minutes (worst case ~10, since the kill also skips the `WCAC_CONTINUE_HOOK` scheduling at `:411`).

The predicate that actually matches the finding's reasoning is `defined( 'WP_CLI' ) && WP_CLI` alone, or `php_sapi_name() === 'cli'`. `wp_doing_cron()` does not distinguish a real system cron from the loopback one.

### R4 — `campus-connect --run` drains non-cc jobs → **PARTIALLY CLOSED**

`WCAC_Sync::queue_campus_now()` (`class-wcac-sync.php:585-604`) now filters every `cc` entry out and unshifts one unconditionally. Verified:

```
input queue: [wc, mu, cc, kid]
queue_campus_now() -> false
queue now:  [{"to":"cc"},{"to":"wc"},{"to":"mu"},{"to":"kid"}]     <- cc at head, rest in order
```

With a realistic (slow) `cc` job, `run_slice(1)` runs **exactly one job**:

```
slow cc:    jobs run = ["cc"]        processed=1 remaining=3
```

The 50-job drain is gone. Two residuals remain, both real:

1. **When the `cc` job short-circuits, one non-cc job is still drained.** `job_campus_connect()` returns immediately at `class-wcac-sync.php:1051` when the preflight gate has not been stamped — the default state on any site that has not yet run `wp wcac campus-connect --preflight`, which is precisely the operator most likely to be typing `--run`. `run_slice()` checks its budget *between* jobs, so the instant return leaves budget on the clock:

   ```
   instant cc: jobs run = ["cc","wc"]  processed=2
   ```

   That is one real fetch-and-upsert into `tblLBVsO4rU2oj2Ki` from a command named `campus-connect`. Bounded at ~1s of jobs rather than 50, but not zero.

2. **The command still reports none of it.** The audit's second remedy ("have `campus_run()` report how many non-`cc` jobs it drained") was not taken. Worse, `class-wcac-cli.php:1216` prints `array( 'key' => 'jobs run', 'value' => $slices )` — `$slices` counts **slices**, not jobs. In the case above it prints `jobs run: 1` while two jobs ran and one wrote to a table the command does not name. `run_slice()` already returns `$result['processed']`; summing that instead of `$slices++` would make the label true and close the residual.

---

## NEW defects introduced by the fix pass

### NEW-1 (medium) — S4b and S4c contradict each other, and an unparseable stored root is destroyed by the next unrelated save

`class-wcac-settings.php:418-441` (`maybe_upgrade()`) deliberately leaves an unparseable `source_root` alone, and says so:

> *"A root that cannot be parsed at all is left alone rather than blanked … rewriting would discard a value the operator may still be able to repair by hand."*

But `class-wcac-admin.php:773` now renders that field from `WCAC_Settings::source_root()`, which returns `''` for exactly those values:

```
source_root('central.wordcamp.org')  =>  ''      (no scheme)
```

So the box renders **empty**. The operator then saves any unrelated setting — lookback window, a table ID — and `class-wcac-settings.php:315` exempts the empty string from the rejection guard (`'' !== $raw && …`):

```
stored='https://central.wordcamp.org'  input=''      -> stored now=''   rejected=[]
stored='https://central.wordcamp.org'  input='   '   -> stored now=''   rejected=[]
```

Result: the stored root is blanked, `last_rejected()` is empty, the admin shows a green **"Settings saved."**, and all five syncs then build `/wp-json/wp/v2/wordcamps?…` and fail every job with `http_request_failed` (`WCAC_Source::get()` `:56-58` → `tally( 0, 0, 1 )`).

Baseline had no such path: it rendered `$settings['source_root']` raw and round-tripped whatever was stored. The trigger needs a stored root with no scheme or no host (hand-edit, `wp option update`, a migration) — low probability, but it is the exact value `maybe_upgrade()` was written to preserve. The guard at `:315` should treat "the field was submitted empty but a usable root is stored" as a rejection, or the input should render the raw stored value with the userinfo stripped only for display.

### NEW-2 (low) — `maybe_upgrade()` logs a false credential-exposure warning and silently mutates a clean root

`strip_userinfo()` (`class-wcac-settings.php:450-467`) rebuilds `scheme://host[:port][path]` and drops query and fragment. `maybe_upgrade()` rewrites whenever `$safe !== $stored` and then logs one fixed line. Measured:

```
'https://central.wordcamp.org/wp?x=1' -> 'https://central.wordcamp.org/wp'
  log=["warn: Stored source site rewritten: a user:pass@ authority was removed from it.
       Any credential it contained should be treated as exposed and rotated."]
```

No credential was present. On `admin_init`, with no operator action, the plugin silently changes the URL the five syncs fetch and tells the operator to rotate a secret that never existed. Baseline `save()` preserved query strings, so such a row is reachable. Gate the log line (and ideally the rewrite) on `isset( $parts['user'] ) || isset( $parts['pass'] )` rather than on string inequality.

### NEW-3 (low, behaviour change rather than defect) — an unparseable root now fails differently

`WCAC_Source::central_root()` (`class-wcac-source.php:357`) returns `''` for a root `strip_userinfo()` cannot parse, so `wordcamps()`/`meetups()` now request a relative URL and fail with *"A valid URL was not provided"* where baseline passed the raw string to `wp_remote_get()`. Both fail; only the message changes. No fatal, no new branch. Flagging it only because it is the read-side half of NEW-1.

---

## Genuinely fine — stated plainly

- **The five syncs' request wire format is unchanged.** Same URLs, same headers, same timeout, same redirection, same user agent, proven by running both trees.
- **Their mapping is unchanged.** All five mappers and every shared helper are byte-identical.
- **Their Airtable writes are unchanged.** `request()` and `ping()` byte-identical; `upsert()`'s defaults reproduce the old semantics exactly, including the abandon-remaining-chunks behaviour.
- **Their job bodies are unchanged.** `job_wordcamps`, `job_meetups`, `job_event_data`, `sync_collection`, `tally`, `push`, and the whole queue accessor set are byte-identical.
- **Logger redaction is a no-op for them.** Their only contexts are `array( 'page' => int )` and they store verbatim; the `Authorization`-in-`$args` trap the readme promises to catch is genuinely caught.
- **`queue_campus_now()` preserves the order of every non-`cc` job**, so an in-flight backfill is reordered only by having one `cc` job placed ahead of it.
- **The preflight-skip no longer inflates the shared Errors counter** (`class-wcac-sync.php:1052-1065`), which the five syncs also read; the reason now goes to `cc_last_error` instead.
- **R5 is unchanged and still latent-only** — no existing consumer inspects upsert error data.
- **`uninstall.php` needs nothing**; `cc_partial` lives in `wcac_state`, already deleted.

---

## Priority for a second fix pass

1. **R3 residual** — `wp_doing_cron()` is not a "no web request to lose" signal. `class-wcac-sync.php:383`. This is the one item that still stalls the five syncs on a default install.
2. **NEW-1** — blank-input exemption at `class-wcac-settings.php:315` combined with the render at `class-wcac-admin.php:773`.
3. **R4 residual** — `$slices` mislabelled as "jobs run" at `class-wcac-cli.php:1216`; sum `$result['processed']` and name the non-`cc` jobs.
4. **NEW-2** — gate the rotate-your-credential warning on actual userinfo, `class-wcac-settings.php:429-436`.

---

## Lens: Secrets

# Re-audit: Credential leakage lens

No files edited. `php -l` clean on all 10 files (informational). Verdicts below are against the current working tree (`df355b6` + the fix pass).

## Core path: still holds

`$cred['pass']` still has exactly two consumers, verified by grep for `$cred[`:
- `class-wcac-source.php:330` `base64_encode( $cred['user'] . ':' . $cred['pass'] )` into `headers` only, with `redirection => 0` (`:333`) and the https gate at `:322`.
- `class-wcac-settings.php:193` into the salted fingerprint.

Zero egress sinks (`error_log`, `file_put_contents`, `var_dump`, `print_r`, `var_export`, `wp_mail`, `wp_localize_script`, `register_rest_route`, `wp_ajax`) anywhere in `includes/` or the two root files. `render_log()` (`class-wcac-admin.php:882-914`) still prints When/Level/Message only, never `context`. `uninstall.php:31-33` still deletes `wcac_settings`, and nothing in the fix pass added an option or transient that could hold a secret (`cc_partial` went into `wcac_state`, already deleted).

**Literal-secret sweep: clean.** Regex over the whole tree excluding `.git` for `pat[A-Za-z0-9]{14,}`, `key[A-Za-z0-9]{14,}`, the six-group application-password shape, `Basic <b64>`, `Bearer <token>`, and `://user:pass@` returns two hits, both inside the audit/analysis docs describing the defect (`docs/2026-09-02-campus-connect-post-build-audit.md:79`, `docs/2026-09-02-campus-connect-sync-analysis.md:605`, the latter an invented illustrative `mp:abcd1234efgh`). Nothing added by the fix pass: `git diff df355b6 | grep '^+'` filtered for credential-shaped strings yields only prose and identifiers.

---

## The six Secrets findings

### 1. `read_secret()` echoes the password — CLOSED

`class-wcac-cli.php:1385-1431`. Three-branch terminal test (`stream_isatty` → `posix_isatty` → assume-terminal), then a `stty -g` capability probe, then refusal.

Every trigger the finding named is now covered, and I checked each rather than trusting the shape:

- `disable_functions=shell_exec` (WP Engine, Pressable): `function_exists( 'shell_exec' )` is false for a disabled function, so `$saved` stays `''`, and `:1417` refuses before `fgets()` at `:1425`. No prompt, no read.
- Slim Docker without ext-posix: previously fatal, because the old predicate required `posix_isatty` and so evaluated to "not a terminal" on exactly the hosts the finding named. `stream_isatty()` at `:1389` is core since 7.2 and unconditional, so the probe is now reached and the refusal fires. This is the deviation the CLI agent flagged, and it is correct: the work order's specified predicate would have left the defect standing on two of the three named platforms.
- Windows: `stty` is not a command, the probe is empty, refusal. The old code's worse case (prompt asserting "not echoed" while echoing) is gone.
- The refusal message at `:1417` carries `<login>` and `secret.txt` only. No credential value.
- Restore is `shell_exec( 'stty ' . escapeshellarg( $saved ) )` inside `finally` (`:1427-1430`), not a blanket `stty echo`, so a terminal that was not echoing before is not left echoing after.
- The non-TTY path (`< secret.txt`) reads with no prompt and no `stty` at all, unchanged.

Docblock at `:1360-1384` and the command docblock at `:426-431` now state the refusal instead of asserting echo is always off. `readme.txt:127-136` matches.

Residual, not a finding: `shell_exec( 'stty -echo' )` at `:1422` is still unchecked. After a successful `stty -g` the probability that `-echo` fails is negligible, and the failure mode is the pre-fix one rather than a new one.

### 2. DB copy invisible and unclearable once the constants exist — CLOSED in the admin, OPEN in WP-CLI

**The admin half is genuinely fixed.** `class-wcac-admin.php:846-865`: the Remove button renders whenever `$has_db_copy` is true, `$locked` included, and only *Save credential* is hidden under `$locked`. `handle_central()` answers `clear_central` at `:306-313`, *before* the constant refusal at `:316-322`, so the button works while locked. `:840-844` prints a paragraph saying a database copy exists, is overridden, still sits in `wp_options`, travels in exports, and should be removed. The screen no longer asserts there is no DB copy.

`WCAC_Settings::save()` no longer writes `central_app_password` at all: the branch is deleted (grep for `$input['central_app_password']` returns nothing), and the docblock at `class-wcac-settings.php:283-289` records that the credential has exactly two writers. The hand-crafted-POST asymmetry the finding named as "related" is closed for the password.

**What is not closed — a new asymmetry the fix created:**

`class-wcac-cli.php:422-457` has no constant check anywhere. With `WCAC_CENTRAL_USER` / `WCAC_CENTRAL_APP_PASSWORD` defined in `wp-config.php`, `wp wcac central-credential --user=someone` still writes `central_user` and `central_app_password` into `wcac_settings` (`:451-452`), re-creating precisely the orphan row finding 2 exists to eliminate. `handle_central()` refuses the same operation and redirects to `central_locked`; the CLI accepts it silently.

It is worse than silent. Two compounding effects:

- `:456` reports `WP_CLI::success( 'Credential stored. Fingerprint: %s', WCAC_Settings::credential_fingerprint() )`. Under constants, `credential_fingerprint()` short-circuits to the *constant's* fingerprint (`class-wcac-settings.php:191`), so the command prints a fingerprint that has nothing to do with the value just typed and confirms a store that is inert.
- The X2 gate makes it quieter than before: `set_secret()` computes `$before` and the post-write fingerprint from `credential_fingerprint()` (`class-wcac-settings.php:230,238`), which under constants is the constant's on both sides, so `wcac_central_credential_changed` never fires. That is right for the latch, but it means the write produces no signal of any kind.

The row is at least now *visible* (the admin renders the Remove button), so this is narrower than the original finding. Remedy is one guard mirroring `admin.php:316`: refuse with the same message, or store and warn that the constants override it.

Lower severity, same shape: `class-wcac-settings.php:324-332` still accepts `central_user` from `save()` with no constant check. The settings form posts no such field, so this needs a crafted POST to `admin-post.php?action=wcac_save`, and it can only create a username-half orphan, which `$has_db_copy` (`admin.php:845`) then surfaces.

### 3. `central-check` prints `source_root` verbatim — CLOSED

`class-wcac-cli.php:371` is `WCAC_Settings::source_root()`. `grep -rn "get( 'source_root' )"` over `includes/` returns only the accessor's own body (`class-wcac-settings.php:400`) and `save()`'s read of the previous value (`:316`). No other reader anywhere.

`source_root()` (`:399`) → `strip_userinfo( untrailingslashit( … ) )`, and `strip_userinfo()` (`:449-466`) rebuilds scheme/host/port/path only and returns `''` without a scheme or host. `strip_userinfo()` is still `private`, so the raw value cannot be reached from outside the class.

### 4. The five original syncs transmit and log `user:pass@` — CLOSED for transmit/log/render; PARTIALLY CLOSED at rest

Transmit and log: `class-wcac-source.php:124` (`wordcamps()`) and `:147` (`meetups()`) now call `$this->central_root()`, and `central_root()` (`:356-358`) delegates to `WCAC_Settings::source_root()`. All six root consumers in that class route through it (`:124, :147, :174, :226, :262, :368`). The URL that `get()` bakes into `wcac_source_http` / `wcac_source_json` (`:64, :73`) therefore cannot carry userinfo on any path, so `sync.php:894,920,968,994` and `admin.php`'s log table cannot render one. Rendered value in the settings form is `WCAC_Settings::source_root()` (`admin.php:773`). The changelog claim that stripping is global is now true.

**At rest, the repair does not reach every site.** `WCAC_Settings::maybe_upgrade()` (`class-wcac-settings.php:418-436`) is hooked only on `admin_init` (`wordcamp-airtable-connector.php:122`) and called from the activation closure (`:129`). `admin_init` does not fire under WP-CLI, and does not fire on a WP-Cron request. A site driven entirely by `wp` and cron — which is the operator profile the readme's own CLI route addresses, and the one most likely to have hit `wp wcac central-check` in the first place — keeps the legacy cleartext `user:pass@` row in `wp_options` indefinitely, through every backup and staging clone. Nothing leaks from it (every reader is stripped), but the finding's "delete it" half is unmet on those sites. One line in `WCAC_CLI::__construct()` or in `wcac_cron_tick()` closes it.

Also worth recording, since it is a behaviour change rather than a defect: `maybe_upgrade()` deliberately declines to rewrite when `strip_userinfo()` returns `''` (`:429`), so an unparseable root is left alone rather than blanked. That is the right call — blanking would break `url()` for all five syncs — and it does not leak, because `source_root()` returns `''` for the same input.

### 5. Advertised log-context redaction does not exist — CLOSED for the claim and the named trap, with demonstrated residual gaps

`class-wcac-logger.php:50` now stores `self::redact( $context )`. I ran the real methods against a stubbed `get_option`/`update_option` rather than reading them:

| input context | stored |
|---|---|
| `array( 'args' => array( 'headers' => array( 'Authorization' => 'Basic …' ), 'timeout' => 45 ) )` | `{"args":{"headers":{"Authorization":"[redacted]"},"timeout":45}}` |
| `array( 'h' => 'Basic dXNlcjpwYXNzd29yZA==' )` | `{"h":"[redacted]"}` |
| `array( 'v' => 'abcdEFGHijklMNOPqrstUVWX' )` (stored app-password form) | `{"v":"[redacted]"}` |
| `array( 'note' => '… abcd EFGH ijkl MNOP qrst UVWX …' )` (profile-screen form) | `{"note":"[redacted]"}` |
| `array( 'table' => 'tbld8niqsLWyNbcVF', 'rec' => 'recABCDEFGHIJKLMN' )` | unchanged — no false positive |
| `array( 'page' => 3 )` (the only live call sites) | unchanged |

The exact trap the finding named — `array( 'args' => $args )` for the Campus Connect request — is covered, so `readme.txt:231,290` are now earned. WP application passwords are `wp_generate_password( 24, false )`, alphanumeric, so the third pattern matches the real shape.

Three residual gaps, all in `scrub()` (`:117-133`), none live today (the only contexts in the plugin are `array( 'page' => $page )`), but each is exactly the "next contributor" scenario the finding was filed over. Measured, not asserted:

- **cURL/Requests-style header list.** `array( 'req' => array( 'headers' => array( 0 => 'Authorization: Basic dXNlcjpwYXNzd29yZA==' ) ) )` stores **verbatim**. The key is `0`, not a string, so the mask does not apply; and pattern 1 anchors on `^(?:Basic|Bearer)`, which a full header line does not start with. WP_Http's associative shape is covered; the numerically-indexed sibling is not.
- **A `user:pass@` URL under a neutral key.** `array( 'url' => 'https://someone:hunter2@central.wordcamp.org/wp-json/' )` stores verbatim. Given that findings 3 and 4 are entirely about that string, a value-level `://…:…@` rule belongs here.
- **The stored 24-char password embedded in prose.** `'pw=abcdEFGHijklMNOPqrstUVWX now'` survives; pattern 3 is whole-value anchored, unlike pattern 2.

Also true and worth saying plainly: `redact()` covers `context` only, not `message` (`:49-50`). Every real log line in the plugin is a literal string plus `get_error_message()`, and I checked all 43 call sites — none carries a credential. The readme claims only context redaction, so the documentation is accurate. The one third-party-dependent surface is `class-wcac-airtable.php:141-143`, which falls back to the raw response body as the message; Airtable's error envelope never echoes the request headers, so this stays a note rather than a finding.

### 6. Airtable token field weaker against autofill — CLOSED

`class-wcac-admin.php:698` is now `autocomplete="new-password" spellcheck="false" autocapitalize="off"`, matching the Central field at `:816`. The placeholder at `:699` is `'pat...'`, a shape hint, not a value.

---

## New defects introduced by the fix pass

1. **`wp wcac central-credential` creates the orphan row the S2 fix exists to remove** — `class-wcac-cli.php:422-457`, no constant guard, versus `class-wcac-admin.php:316-322` which refuses. Reports the constant's fingerprint (`:456`) as confirmation of a store that is inert, and fires no action. See finding 2 above. This is the only one I would hold a release for.

2. **`maybe_upgrade()` never runs on a CLI-only or cron-only site** — `wordcamp-airtable-connector.php:122`. See finding 4 above.

3. **Redaction gaps in `scrub()`** — `class-wcac-logger.php:117-133`. See finding 5 above. Hardening, not a live leak.

Checked and clean, so as not to leave doubt:

- The new `wcac_rejected` query argument is not a reflection surface. `handle_save()` (`admin.php:110-121`) writes only literals from `last_rejected()`, which can contain `'source_root'` or `'central_user'` and nothing else (`settings.php:317,328`); `rejected_notice()` (`admin.php:402-450`) re-`sanitize_key`s each token and drops anything outside a two-entry label whitelist, falling back to a label rather than echoing.
- Neither new log line carries a value: `settings.php:318` names the rule, `settings.php:435` names the repair. Neither prints the root.
- `render()`'s blanking is now real: `admin.php:473-476` takes two booleans and clears `$settings['central_app_password']` before the remaining ~470 lines. The comment at `:791-794` is true as written.
- The `wcac_central_credential_changed` gate in `set_secret()` (`settings.php:230,238`) and `forget_central()` (`settings.php:262-263,272`) does not open a leak path; it only narrows when the latch clears.

## Two cosmetic notes, outside the lens

- `class-wcac-admin.php:699` still reads `'Saved — leave blank to keep'` with an em dash, in a translatable UI string, against the standing house rule and inconsistent with the Central field at `:817` which uses a hyphen. One character.
- Under `$locked`, `admin.php:809` renders the *database* username in the input's `value` while the fingerprint row at `:830-834` is labelled "from wp-config.php". The new paragraph at `:840-844` explains that a DB copy exists, so it is no longer misleading, but the field shows a username that is not the one in use.

---

## Lens: Data loss

## Re-audit — Data-loss lens (damage to the 142 hand-imported rows in `tbld8niqsLWyNbcVF`)

No files edited. Coercers exercised under PHP, not read. `php -l` clean on `class-wcac-mapper.php`, `class-wcac-sync.php`, `class-wcac-airtable.php`.

---

### D1 — boolean `false` dropped, counters ratchet upward — **CLOSED**

`includes/class-wcac-mapper.php:337-339` inserts `if ( is_bool( $value ) ) { return $value ? 1 : 0; }` between the `is_scalar()` guard and the string cast. Measured, not inspected:

```
campus_int(false) => 0     campus_int(true) => 1     campus_int('') => NULL
campus_int(0) => 0         campus_int('n/a') => NULL campus_int('1,200') => NULL
```

Write path verified end to end. `$put` (`mapper:785-789`) is `null !== $value && '' !== $value`; `'' !== 0` is `true` under strict comparison, so `$put( 'Actual Attendees', 0 )` (`:824`) and `$put( 'Series Event', 0 )` (`:829`, still correctly behind `array_key_exists( 'Series Event', $row )` at `:828`) both emit the key. `upsert()` sends it verbatim (`airtable:186-194`) and PATCH-upsert writes it. A curated `Series Event = 1` is now correctable back to 0.

Counter honesty checked: `$number()` (`mapper:802-810`) increments `int_unparsed` only when the coercer returns `null`; `false` now returns `0`, so it is recorded as a parsed value, not a miss — exactly the semantics the finding asked for.

No regression to the ID gate: `campus_int(false)` used to be `null` → `$id = 0` → row dropped (`mapper:768-778`); it is now `0` → `$id = 0` → row dropped. Same for `cli.php:876` (`null !== $id && $id > 0`). Behaviour identical.

### D2 — calendar-invalid `Y-m-d` overwriting a curated date — **CLOSED, and wider than the finding asked**

`mapper:385` gates the bare-`Y-m-d` branch on `checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] )` (month, day, year — argument order correct). Measured:

```
'2026-02-30' => NULL   '2026-13-45' => NULL   '2026-04-31' => NULL
'2026-02-29' => NULL   '2026-00-10' => NULL   '2026-05-00' => NULL
'2024-02-29' => '2024-02-29'   '2026-05-15' => '2026-05-15'
```

The mapper agent was right that the audit's premise about the sibling branch was wrong, and the extra fix is load-bearing. `strtotime('2026-02-30T00:00:00Z')` returns a timestamp (rolls to 2026-03-02) rather than `false`, so the `false === $stamp` check the audit called validation was not. `mapper:399` now checks the **raw** parts as well, which is the only correct place — a genuine offset shift must still move the day. Measured:

```
'2026-02-30T00:00:00Z' => NULL        '2026-02-30 12:00:00' => NULL
'2026-05-15T00:30:00+02:00' => '2026-05-14'   (offset shift preserved)
'2026-12-31T23:30:00-05:00' => '2027-01-01'   (offset shift preserved)
'2024-02-29T10:00:00Z' => '2024-02-29'        (real leap day preserved)
'1778371200' => '2026-05-10'                  (timestamp branch untouched)
```

Drops fall through `null === $date` (`mapper:404`) into `$date()`'s counter (`mapper:791-800`), so `date_unparsed` moves and `sync:1358` logs it. The chunk-422 half of the consequence is closed too, because the bad value never reaches the payload.

### D3 — the genuine-zero write — **PARTIALLY CLOSED (detection only; that was the ruling, but the alarm is weaker than it looks)**

The cheap half is in and correct in mechanism. `$zeroes` accumulates per field name at `sync:1186-1191`, inside the row loop, after the `id < 1` skip and immediately before `$records[...] = $mapped['fields']` (`:1192`), so only rows that will be sent are counted; one aggregated `warn` at `sync:1328-1345` names the fields and counts. It catches both shapes the finding named — `campus_int()`'s int `0` and `campus_text()`'s string `"0"` for `Anticipated Attendees` — because the test is `0 === $value || '0' === $value`.

The preventive half is **still open by design**: nothing gates the first write on a `--dry-run`. `campus_run()` and the admin button still write to a table that has never been diffed. The dry run does surface it (`cli:1083-1086`: `null === $current` returns `true`, so a `0` landing in an empty cell shows as a change), but reading it remains voluntary.

Three ways the new alarm under-delivers, all worth knowing before anyone treats it as a tripwire:

1. **D1 turns this line into per-run background noise.** Now that `false` maps to `0`, a report that sends `"Series Event": false` on the ~140 non-series camps will log *"wrote a zero or "0" to 140 cell(s): Series Event (140)"* on **every** run. The 142-row `Actual Attendees` regression the counter exists to catch would then be one more entry in a line that always fires, in a 200-entry ring. The two fixes interact and nobody appears to have costed it.
2. **It says "wrote" but is computed from the payload, not the result.** It is emitted after the write, but from `$zeroes`, so with `$failed` non-empty up to `10 × count($failed)` of the cells it claims were written were not.
3. **Duplicate merge IDs are counted once per row, not once per surviving record** — a deliberate over-count, self-flagged, and bounded by the dupe warning the run already emits.

### D4 — partial chunk failure resets the latch; stale `cc_last_error` — **CLOSED for the reported defect; the fix ships a NEW gap (see N1)**

Both halves the finding asked for are in. `cc_ok( $written, $read, $chunks = 0, $reason = '' )` (`sync:665`) splits cleanly: `$chunks < 1` resets `cc_partial` **and** clears `cc_last_error` (`:679-680`), so the stale-string complaint at `admin:477-481` is answered; `$chunks > 0` increments `cc_partial` (`:687`), writes a real `cc_last_error` naming the unwritten count, the chunk count and the last reason (`:688-698`), and calls `cc_block()` at five (`:702-704`). `cc_fails` is still reset on the partial path, which is correct — the report was fetched, so the dead-report latch must not fire. New state key declared at `sync:133`, so `state()`'s `wp_parse_args` defaults it to 0 on existing installs. Call site (`sync:1384-1389`) passes `count( $failed )` and `end( $failed )['message']`. The all-chunks-failed early return at `sync:1294-1303` still routes to `cc_fail()` ahead of it, so a total failure cannot be misread as partial.

Minor accuracy note: `$read` is `count( $rows )`, not `count( $records )`, so on a run with skipped or deduped rows `"%d of %d rows were not written"` overstates the unwritten count by the number of rows that were never candidates. Cosmetic, in a string the operator reads while diagnosing.

---

## NEW defects introduced by the fixes

**N1 — `cc_unblock()` does not reset `cc_partial`, so D4's "five runs in a row" is a one-run latch after the first block. MEDIUM.**
`includes/class-wcac-sync.php:535-549` clears `cc_block`, `cc_block_why` and `cc_fails`, and nothing clears `cc_partial`. It is called from `enqueue_full()` (`:224`), `enqueue_incremental()` (`:272`), `queue_campus_now()` (`:586`) and `clear_campus_block()` (`:562`) — i.e. from every operator re-arm, including the credential-changed hook at `wordcamp-airtable-connector.php:90`. Sequence: five partial runs latch (`cc_partial` = 5, block set) → operator presses *Sync changes now* or *Sync Campus Connect now* → block cleared, `cc_partial` still 5 → the next run with **any** failing chunk, transient or not, goes to 6 and re-blocks for 12 hours. The message `cc_ok()` writes — *"The same Campus Connect rows have failed to write on five runs in a row"* — is then false on two counts: it was one run since the re-arm, and nothing in `cc_ok()` compares the failing chunk's `keys` between runs, so "the same rows" is never verified. `cc_partial` should be cleared alongside `cc_fails` in `cc_unblock()`, and the message should not claim identity it does not check.

**N2 — `cc_partial` has no surface. LOW.**
`grep -rn "cc_partial" includes/ readme.txt` returns hits only at `sync:133,650,679,687,697`. It is not in the admin Status table, not in `wp wcac campus-connect --status`, and not in the readme. An operator sitting at `cc_partial` 4 sees `cc_last_error` but has no way to know a block is one run away, and after N1 no way to know why a block returned immediately after they cleared it.

**N3 — residual of the same class DL5 fixed, one state key over. LOW.**
The `cc_keys` / `cc_series` write was correctly moved past the volume gate (single write site now, `sync:1235-1236`, after `cc_volume_ok()` at `:1225`; the comparison at `:1121` still reads the pre-fetch `$state`). But `cc_unmapped` / `cc_absent` are still persisted at `sync:1193-1198`, **before** that gate — so a run refused for implausible growth still overwrites the Status-gap buckets the admin renders at `admin:489-497`, with figures from a run that wrote nothing. Not damage to the 142 rows; a reporting surface that describes a refused run as though it had happened.

---

## Genuinely fine, checked and not re-litigated

- **The upsert path is untouched and still safe.** `class-wcac-airtable.php` is byte-identical to `df355b6`. PATCH not PUT (`airtable:204`, with the comment naming the 142 rows as the reason), one chunk per request, `continue_on_error` recording `chunk`/`message`/`keys` (`:227-231`) — the failure unit is still ten whole rows untouched, never a half-written row.
- **No regression to the five existing syncs from these two files.** Every mapper diff hunk lands in `243-546` (the key consts, the campus coercers, `campus_key_report()`); `wordcamp()`, `meetup()`, `session()`, `speaker()`, `sponsor()` are outside every hunk. Every sync hunk is `cc_`-scoped except `:373` (the R3 lock split, another lens's item).
- **The `CAMPUS_KEYS_IGNORED` addition cannot affect writes.** `mapper:549` merges it into `$known`, which feeds `unexpected` only; `missing` is still diffed against `CAMPUS_KEYS_REQUIRED` alone (`:553`). The mapper agent's reasoning for adding it rather than bare-deleting `'Organizer Name'` holds — a bare delete moves the same false alarm from `missing` to `unexpected` and fires it every run.
- **The audit's own lower-severity trap is still unreachable.** `$put`'s `'' !== false` hazard needs a coercer that returns a bool; after D1, `campus_int()` returns `int|null`, `campus_text()`/`campus_url()` return `string`, `campus_status()` returns a string label. Nothing can hand `$put` a boolean.
- **`WCAC_Logger::redact()` does not touch messages**, only `$context` (`logger:50`), and no campus log line passes a context. The new zero/partial warnings reach `wp_options` intact.

---

## Lens: Cross-file contract

I have everything I need. No files were edited.

# Cross-file re-audit — WordCamp Airtable Connector, post-fix

## `php -l` — all 10 files pass

```
wordcamp-airtable-connector.php   uninstall.php                    class-wcac-settings.php
class-wcac-cli.php               class-wcac-logger.php            class-wcac-airtable.php
class-wcac-mapper.php            class-wcac-source.php            class-wcac-sync.php
class-wcac-admin.php
```
Machine PHP is 8.5.7, so this proves parsing, not the 7.4 target. Separate sweep for `?->`, `match(`, `enum`, `readonly`, `#[`, promotion, `str_contains`/`str_starts_with`/`array_is_list` across the diff: **zero hits**. New constructs added this pass are `is_bool()`, `checkdate()`, `stream_isatty()` (7.2+), `get_class()`, `gettype()`, `array_unshift`, `wp_doing_cron()`, `end()` — all 7.4-clean.

## Mechanical symbol resolution — clean

Token-stripped (comments removed) resolution pass over all nine classes:

- **Every `$this->method()` and `self::method()` resolves** to a method in its own class. Zero misses.
- **Every `$this->prop`, `self::$prop` and `self::CONST` resolves.** Zero misses.
- **Every cross-object call targets a `public` member**: `$sync->` (state, pending, camps, queue, clear_queue, run_slice, enqueue_full, enqueue_incremental, queue_campus_now, clear_campus_block, allow_campus_growth_once, set_campus_preflight), `$source->` (campus_connect, central_auth_advertised, central_identity_probe), `$airtable->ping`. All public.
- **All 14 new/changed cross-class statics exist with the assumed signature and visibility**, including the three contracts this pass created: `WCAC_Settings::last_rejected()` (`settings.php:383`, public static, consumed at `admin.php:108`), `WCAC_Settings::source_root()` (`settings.php:399`, consumed at `cli.php:371`, `admin.php:773`, `source.php:357`), `WCAC_Settings::maybe_upgrade()` (`settings.php:418`, consumed at `wordcamp-airtable-connector.php:120`). The `source.php` agent's blocking-dependency flag is resolved.
- Six greps that looked like cross-class reaches into `protected` members (`WCAC_Sync::cc_blocked`, `WCAC_Sync::error_status`, `WCAC_Source::report_timeout`, `WCAC_CLI::status`, `WCAC_Sync::state`, `WCAC_Source::get`) are **all inside docblocks or `//` comments**. False positives.

**Settings keys**: every read (`api_key, base_id, campus_extra_status, lookback_hours, source_root, sync_*, time_budget, central_user, central_app_password`, plus `tbl_*` via `table_for()` with `wordcamps|meetups|sessions|speakers|sponsors|campus_connect`) has a declared default at `settings.php:38-58`. **Zero remaining `get( 'source_root' )` outside the class** — S3/S4/S4b landed on all four sides.

**State keys**: all 15 `cc_*` reads plus `mode/created/updated/errors/last_run` are declared at `sync.php:120-145`, including the new `'cc_partial' => 0` at `:123`.

**Constants**: `WCAC_VERSION/FILE/DIR/URL/CRON_HOOK/CONTINUE_HOOK` defined at `:19-33`; `WCAC_CENTRAL_USER`, `WCAC_CENTRAL_APP_PASSWORD`, `WCAC_ALLOW_INSECURE_CENTRAL` all `defined()`-guarded (`settings.php:478-490`, `source.php:374`). New `WCAC_Logger::REDACTED/CONTEXT_*` and `WCAC_Mapper::CAMPUS_KEYS_IGNORED` are read only inside their own file. `uninstall.php:31-38` option/hook strings all match their constants. Version `1.1.1` agrees across plugin header, `WCAC_VERSION` and `Stable tag`.

**Notice keys**: every key `handle_action()`/`handle_central()`/`test_central()` sets exists in `notice()`'s map or the `save_rejected` branch; no orphans in either direction. `cc_moved` (X5) is wired at both ends.

---

## The five Contract findings

| # | Finding | Verdict |
|---|---|---|
| X1 | Preflight invisible to admin | **PARTIALLY CLOSED** — see NEW-1 |
| X2 | `wcac_central_credential_changed` unconditional | **PARTIALLY CLOSED** — see NEW-2 |
| X3 | `'Organizer Name'` orphan | **CLOSED** |
| X4 | Two sentinel spellings | **PARTIALLY CLOSED** — see NEW-3 |
| X5 | `cc_now` reports success on a no-op | **CLOSED** |

**X3 CLOSED.** `'Organizer Name'` is out of `CAMPUS_KEYS_REQUIRED` (`mapper.php:235-247`) and into a new `CAMPUS_KEYS_IGNORED` (`mapper.php:270-272`), merged into `$known` at `:549`. Both buckets stay empty on a healthy report; a genuine `_venue_city` → `Venue City` rename still reports. `campus_key_report()`'s return shape is unchanged, so `sync.php:1086` and `cli.php:538` need nothing. The added const was the right call — a bare deletion would have moved the same false alarm from `missing` to `unexpected`.

**X5 CLOSED.** `admin.php:198` `$notice = $sync->queue_campus_now() ? 'queued' : 'cc_moved';`, `'cc_moved'` message at `admin.php:365`, and the contract matches the landed `sync.php:585-604` (`return ! $pending;`) and `cli.php:1184-1188`.

The other 15 findings, verified at both ends: **R2, R3, R4, S1, S2, S3, S4, S4b, S4c, S5, S6, D1, D2, D3, D4, DL5 — all CLOSED.** R5 and DL1-DL3 were WONTFIX and correctly untouched; `class-wcac-airtable.php` is byte-identical to `df355b6`, which is correct.

---

## NEW defects the fixes introduced

### NEW-1 — HIGH. X1b made the admin and the CLI **disagree** on the row they were supposed to reconcile

- `admin.php:552-560` now branches four ways: `blocked` / `off` / `ready` / `configured, but the preflight below has not passed`.
- `cli.php:116-120` still computes the same row from `campus_connect_ready()` alone: `$campus = $blocked ? 'blocked' : 'ready';` — and never consults `$stamped`, **which it computes two lines later at `:122`** for its separate `campus preflight` row.

So on a never-preflighted site, `wp wcac status` prints `campus connect: ready` for a job that can only skip, while the settings screen now correctly says it has not passed. Before the fix both surfaces said "ready"; now they contradict each other, and `cli.php` is the one that is wrong.

The justification written into the fix is itself inaccurate — `admin.php:481`: *"WCAC_CLI::status() has carried both since 1.1.0."* It carries a separate preflight row; its `campus connect` verdict is exactly as wrong as the admin's was. `readme.txt:344-346` inherits the error, claiming the admin now shows "what `wp wcac status` already reported".

One-line fix in `cli.php`, not shipped.

### NEW-2 — HIGH. `forget_central()` fires the credential-changed action on a no-op, and S2 just made that path a button

`settings.php:262-274`:
```php
$had = ( isset( $all['central_user'] ) && '' !== trim( (string) $all['central_user'] ) )
    || ( isset( $all['central_app_password'] ) && '' !== trim( (string) $all['central_app_password'] ) );
…
if ( $had ) {
    do_action( 'wcac_central_credential_changed' );
}
```

`$had` is computed from the **option row**, not the **effective** credential. `set_secret()` at `:230,241` got this right — it compares `credential_fingerprint()` before and after, which short-circuits to the constant's fingerprint (`settings.php:190-192`) when wp-config wins. `forget_central()` does not.

Consequence, with wp-config constants set and a stale DB copy present: removing the DB copy does not change the effective credential, yet the action fires → `wordcamp-airtable-connector.php:87-92` → `clear_campus_block()` → `sync.php:535` `cc_unblock()` zeroes `cc_block`, `cc_block_why`, `cc_fails`. A Campus Connect job that is 401ing against the *constant* credential goes straight back into rotation with the five-strike counter reset — the precise contract at `wordcamp-airtable-connector.php:82-85` ("only when the stored credential's fingerprint differs") and the hazard `sync.php:546-548` is written against.

**The S2 fix is what makes this reachable.** `admin.php:865` now renders **Remove stored credential** under `$locked` whenever `$has_db_copy`, and `admin.php:307-313` answers `clear_central` *before* the constant check at `:317`. The second reachable path is `cli.php:423-424` (`--forget`). Both are exactly the operator action `readme.txt:126-129` now instructs.

`readme.txt:337-339` therefore ships a false claim: *"saving the Central credential form without changing anything no longer releases a Campus Connect block. Only a credential that actually differs does."*

Fix shape: mirror `set_secret()` — capture `credential_fingerprint()` before the write and compare, instead of testing `$had`.

### NEW-3 — MEDIUM. X4 left the second spelling, and the readme now claims otherwise

- `sync.php:1158` `'(blank)'` and `cli.php:599` `'(blank)'` — the persisted `cc_unmapped`/`cc_absent` buckets and the `--dry-run` gap tables. Agreed. ✓
- `cli.php:640` `$key = '' === $raw ? '(empty)' : $raw;` — the `--statuses` table's `slug` column. **Still the old spelling.**

Both are CLI status surfaces for the identical condition: an operator runs `wp wcac campus-connect --statuses`, sees `(empty)`, then runs `--dry-run` and sees `(blank)` for the same rows.

`readme.txt:354` ships: *"One spelling, `(blank)`, for a blank status on both status surfaces."* That is false as built — this is the same class of unearned documentation claim S5 was filed over, reintroduced in the changelog that announces S5's fix. Either the one-token edit at `cli.php:640` or the readme bullet has to go.

### NEW-4 — MEDIUM. `maybe_upgrade()` tells operators to rotate a credential that never existed

`settings.php:428-436` rewrites whenever `$safe !== $stored`, then unconditionally logs (`:436`):

> `Stored source site rewritten: a user:pass@ authority was removed from it. Any credential it contained should be treated as exposed and rotated.`

But `strip_userinfo()` (`settings.php:450-467`) also drops the query string and fragment and re-applies `untrailingslashit`. Executed against the real function bodies:

| stored `source_root` | result |
|---|---|
| `https://central.wordcamp.org` | left alone ✓ |
| `https://central.wordcamp.org/` | **rewritten + "rotate your credential"** |
| `https://central.wordcamp.org?utm=1` | **rewritten + "rotate your credential"** |
| `https://central.wordcamp.org#x` | **rewritten + "rotate your credential"** |
| `https://u:p@central.wordcamp.org` | rewritten + warn ✓ (correct case) |
| `central.wordcamp.org` | left alone ✓ (deliberate, correctly reasoned) |

Three of the five rewrite triggers have nothing to do with userinfo, and all four warn identically. Since this runs on `admin_init` and the log is a 200-entry ring, the operator sees a security alarm instructing them to rotate a Central application password on the basis of a trailing slash. Fix: gate the message on `isset( wp_parse_url( $stored )['user'] ) || isset( …['pass'] )`, or split into two messages.

Secondary, worth a line: `maybe_upgrade()` is hooked only on `admin_init` (`:122`) and the activation closure (`:128`). A WP-CLI-only or headless site never rewrites the row, so `readme.txt:322-323`'s *"It is rewritten out of the stored option"* holds only for sites someone logs into. The read-time protection (S4) is unconditional, so nothing leaks either way.

---

## Genuinely fine — stated plainly

- **`WCAC_Logger::redact()`/`scrub()` composes correctly with every call site.** The only contexts in the plugin are `array( 'page' => $page )` (`sync.php:895,921,969,995`); `'page'` does not match `CONTEXT_KEY_MASK`, and the 24-char rule cannot swallow a 17-character `rec…`/`tbl…`/`app…` ID. Unguarded `mb_strlen`/`mb_substr` at `logger.php:131-132` is safe and consistent with the repo (`cli.php:1138`, `mapper.php:34,57,305,654`) — WordPress polyfills both in `wp-includes/compat.php`.
- **D1 composes with D3 and with both other `campus_int()` consumers.** `campus_int(false)` → `0` now reaches the `$zeroes` counter (`sync.php:1186-1190`), which is correct — it is a real write. `mapper.php:768-770` and `cli.php:876` both funnel `null` and `0` to the same outcome, so `ID: false` still drops the row.
- **`cc_ok( $written, $read, $chunks, $reason )`** has exactly one caller (`sync.php:1384-1389`) passing four arguments; the added fourth parameter is optional, so no arity break.
- **R3's lock split does not break the queue.** `$long_lock` gates only the extension; the `max( 60, $budget * 3 )` at `:349` still covers the web path.
- **`uninstall.php`** needs nothing: `cc_partial` lives in `wcac_state`, already deleted at `:31`.
- **`class-wcac-airtable.php` untouched** is the right call — R5's "record the shape in the docblock" was already satisfied at `airtable.php:174-177` before this pass.
- The nine files uniformly use `defined( 'ABSPATH' ) || exit;` rather than the task's `if ( ! defined( 'ABSPATH' ) ) { exit; }`. Uniform and pre-existing; `uninstall.php` correctly uses `WP_UNINSTALL_PLUGIN` instead. Not worth diverging one file.

## Residual, not defects

- **R1's over-broad enforcement is untouched.** The fix surfaced the rejection but did not scope it, so a site with a legacy `http://` root — where `sync_campus_connect` may be 0 and no header is ever sent — now gets a red `notice-error` about **Source site** on *every* settings save, because the form always resubmits the rendered value (`admin.php:773` → `settings.php:311`). Previously a silent log warn. Louder is the point of R1, but it fires on saves that never touched the field. The audit's suggested alternative (scope to `central_ready()`) was not taken.
- **Pre-existing, adjacent to R1**: submitting an *empty* Source site box passes the `'' !== $raw` guard at `settings.php:314` and writes `''`, then reports "Settings saved." Present in `740a504` too, so not this pass's doing, but it is the one silent destructive save R1's notice does not cover.
- **R3 traded a stall for a window.** In a web request a `cc` job now runs under a 60s lock (`run_slice(20)` → `max(60,60)`). A 45s report plus PATCHes can outlive it, letting the next cron tick enter `run_slice()` concurrently — the non-atomic `wcac_queue` read-modify-write the extension existed to prevent. Bounded and the ruling asked for it; the comment at `sync.php:377-383` documents the trade honestly.
- **`cli.php:456` prints the wrong fingerprint under constants.** `wp wcac central-credential --user=X` writes a DB row that wp-config overrides, then prints `credential_fingerprint()` — which short-circuits to the *constant's* fingerprint. The admin refuses outright (`admin.php:317-323`, `central_locked`); the CLI has no equivalent guard. Pre-existing in shape, but X2 means it now also fires no action, so there is no signal at all.
- **Two em dashes in translatable strings**, `admin.php:501` and `:699` — both present in `740a504`, so carried, not introduced. `:699` `'Saved — leave blank to keep'` and `:817` `'Saved - leave blank to keep'` are near-duplicate strings differing only in the dash, producing two `.pot` entries for one concept; that pair was created by the Campus Connect work at `df355b6`.
- `rejected_notice()`'s message reads "except **this**, which was refused" while interpolating a comma-separated list — grammatically wrong when both keys are rejected. Cosmetic.

---
