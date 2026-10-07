# Campus Connect sync — final verification after fix round 2 (2026-09-02)

Regression proven byte-identical end to end. Remaining OPEN items are listed per lens; the
'Fresh eyes' lens found three structural issues the earlier lenses missed.

---

## Lens: Regression

All evidence gathered. No files were edited (`git diff --stat` is unchanged from the start of my run).

## Verdict: the five syncs do not regress. Three residuals, one of them a real open finding.

### Method-level comparison vs `740a504`

Token-normalised body hashes (comments/whitespace stripped), every file:

| File | Changed symbols on the five-sync path |
|---|---|
| `class-wcac-sync.php` | `run_slice`, `run_job`, `state`, `enqueue_full`, `enqueue_incremental` |
| `class-wcac-source.php` | `get`, `wordcamps`, `meetups` |
| `class-wcac-airtable.php` | `upsert`, `throttle` |
| `class-wcac-logger.php` | `log` |
| `class-wcac-settings.php` | `save`, `defaults` |
| `class-wcac-admin.php` | `handle_action`, `handle_save`, `render`, `notice`, `__construct` |
| `class-wcac-mapper.php` | **none** (15 symbols byte-identical, 8 added) |
| `uninstall.php` | code identical; comments only |

`job_wordcamps`, `job_meetups`, `job_event_data`, `sync_collection`, `tally`, `push`, `queue`, `set_queue`, `clear_queue`, `pending`, `error_status`, `set_state`, `camps`, `remember_camps` are **byte-identical** — raw `diff` on the extracted bodies produces zero output.

### End-to-end proof (real source/mapper/airtable/sync code, HTTP faked at `wp_remote_*`)

A slice over `[wc page1, mu page1, kid 1000]` with pagination, term lookups and Airtable upserts:

```
processed=8 remaining=0  created=18 updated=18 errors=0
camps cached=4   queue after=[]
20 HTTP calls: same URLs, same table IDs, same fieldsToMergeOn,
typecast=true, PATCH (never PUT), same record counts
```

`diff` of the baseline and working-tree transcripts: **byte-identical**. Five error paths (`upsert500`, `upsert422`, network error, fetch `400` page-exhausted, child `404`) also byte-identical, including tallies, log level, log message and log context.

---

### R3 — the 300s lock. CLOSED on the named path; one residual.

`class-wcac-sync.php:430`. Executed under a genuinely non-CLI SAPI with `DOING_CRON` defined, exactly as inside `wp-cron.php`:

```
php_sapi_name()   = cli-server
defined('WP_CLI') = false
wp_doing_cron()   = true          <- the condition the old predicate keyed on
run_slice() ran   = [cc,wc,mu]
lock TTLs set     = [wcac_lock=60]      <- baseline value; 300 never set
```

Default WP-Cron loopback is back to the baseline 60s lock. **CLOSED.**

**Residual (MEDIUM, `class-wcac-sync.php:432-434`):** the extension is never lowered again, so on the WP-CLI / system-cron path a `cc` job leaves the five syncs' own jobs running under it:

```
SAPI=cli, budget 60 (`wp wcac sync`), queue [cc, wc, mu, kid]
  cc  runs under lock TTL 300s
  wc  runs under lock TTL 300s     <- baseline: 60s
  mu  runs under lock TTL 300s
  kid runs under lock TTL 300s
```

Trigger: a host that wraps `wp cron event run` in `timeout`, or any SIGKILL, landing during the `wc`/`mu`/`kid` jobs *after* a `cc` job in the same slice. Consequence: every following tick returns `processed => 0` for 300s instead of 60s, and `WCAC_CONTINUE_HOOK` is not scheduled either. Narrowest fix: `set_transient( self::LOCK, 1, max( 60, $budget * 3 ) )` immediately after `run_job( $job )` returns, so the extension covers only the `cc` job.

### R4 — CLOSED on reporting, **OPEN on prevention**

The reporting half is real and works. `class-wcac-cli.php:1255,1284-1286,1297-1305` sum `processed`, attribute by job code and warn.

But `run_slice()`'s `$only` parameter — `class-wcac-sync.php:354`, the prevention half the sync owner wrote and explicitly said "without this argument my half is inert" — **has zero callers.** Every call site passes one argument:

```
wordcamp-airtable-connector.php:73   run_slice()
includes/class-wcac-cli.php:80       run_slice( 60 )
includes/class-wcac-cli.php:1253     run_slice( 1 )       <- campus_run()
includes/class-wcac-admin.php:165    run_slice( 20 )
```

Measured, queue `[cc, wc, mu, kid]`, `cc` returning instantly (preflight unstamped — the documented case):

```
run_slice(1)        ran=[cc,wc,mu,kid]  processed=4  queue_left=[]
run_slice(1,'cc')   ran=[cc]            processed=1  queue_left=[wc,mu,kid]
```

`wp wcac campus-connect --run` still fetches from wordcamp.org and writes the WordCamps, Meetups and children tables. It now says so afterwards; it no longer needs to. One-token fix: `cli.php:1253` → `run_slice( 1, 'cc' )`. The CLI's existing `0 === $result['processed']` branch already handles the no-progress return, so there is no loop risk.

### Regression NEW-1 — CLOSED (and it repairs a baseline defect)

`class-wcac-settings.php:322-334`. Empty Source-site box, unrelated field changed:

```
stored                          baseline after   working-tree after            rejected
'https://central.wordcamp.org'  ''               'https://central.wordcamp.org'  [source_root]
'http://central.wordcamp.org'   ''               'http://central.wordcamp.org'   [source_root]
'central.wordcamp.org'          ''               'central.wordcamp.org'          [source_root]
'not a url at all'              ''               'not a url at all'              [source_root]
''                              ''               ''                              []
```

`lookback_hours=48` persists in every row, so the unrelated edit still lands. Baseline blanked the root for **all five syncs** under a green "Settings saved." — that was a live data-loss bug at `740a504`, and it is now gone.

### Regression NEW-2 — CLOSED

`class-wcac-settings.php:455-457`, now gated on `isset($parts['user']) || isset($parts['pass'])`:

```
'https://central.wordcamp.org'          UNCHANGED, no log
'https://central.wordcamp.org/'         UNCHANGED, no log
'https://central.wordcamp.org?utm=1'    UNCHANGED, no log
'https://central.wordcamp.org/wp?x=1'   UNCHANGED, no log
'https://central.wordcamp.org#frag'     UNCHANGED, no log
'http://central.wordcamp.org/'          UNCHANGED, no log
'central.wordcamp.org'                  UNCHANGED, no log
'https://<user>@central...'             REWRITTEN, username-only line
'https://<user>:<pass>@central...'      REWRITTEN, rotate line
```

This matters beyond the false warning: `wordcamp-airtable-connector.php:133` now runs `maybe_upgrade()` on **every cron tick**. Had the rewrite still been gated on `$safe !== $stored`, cron would have been silently rewriting the URL the five syncs fetch. The gate is load-bearing and it is in place.

---

### Other shared-path changes, checked and sound

**`WCAC_Airtable::throttle()` — instance to static (`class-wcac-airtable.php:56`).** This is a fix, not a regression. Measured, 8 upserts:

```
one instance   baseline 5.09 req/s   working tree 5.08 req/s   (identical)
two instances  baseline 12.0 req/s   working tree 5.1 req/s    (Airtable cap is 5/s per base)
```

The five syncs alone are unaffected; the change stops a second `WCAC_Airtable` from pushing the shared base over the limit and costing the five syncs a 30s 429 sleep in a slice they did not cause.

**`upsert()` gained `$options`.** With three arguments `$typecast` is `true` and `$continue_on_error` is `false` — baseline exactly. The error object is rebuilt rather than passed through, but code and message survive (proven in all four error-mode runs), and no five-sync caller reads its data. `request()` is byte-identical, retries included.

**`WCAC_Logger::log()` gained `redact()`.** The five syncs pass `array( 'page' => $page )` or no context. `'page'` does not match `/pass|secret|token|auth|key|cred/i` and an int passes through as a scalar. Contexts logged identically in every scenario.

**`source_root()` normalisation.** Only two stored values behave differently, and neither is a regression:

- schemeless `central.wordcamp.org` — baseline built `central.wordcamp.org/wp-json/…`, working tree builds `/wp-json/…`; **both are rejected by `wp_remote_get` with `http_request_failed`.** Broken before, broken now, and `esc_url_raw()` in baseline `save()` prepended `http://` so this only ever existed on a hand-edited row.
- `https://…/wp?x=1` — baseline produced garbage (`?x=1%2Fwp-json%2F…`), working tree produces a correct `/wp/wp-json/…`. An improvement.

**`handle_action`, `defaults()`, `uninstall.php`, the settings form.** Five-sync cases untouched; all thirteen five-sync form fields still rendered; `sync_campus_connect` defaults to `0`, so an upgraded site gets no new behaviour; the uninstall option list is unchanged.

### Two cosmetic items, no functional impact

- `maybe_upgrade()` is now registered twice under WP-CLI: `WCAC_CLI::__construct()` (`cli.php:34`) **and** `add_action('init', …)` (`wordcamp-airtable-connector.php:133`). Idempotent, one autoloaded `get_option` each. The constructor docblock at `cli.php:21-26` still says "A cron-only site still needs the same call on the cron path" — that call now exists; the constructor is redundant and could be dropped.
- On a site storing `http://central.wordcamp.org` (legal at baseline, still fetched fine), **every** settings save now shows a red `notice-error`, because `save()` refuses to re-store the value the box shows. The five syncs keep running and the notice does say "Saved, except…", but the operator sees an error on saves that have nothing to do with the source site. This is the R1 residual the ruling accepted; recording it because it is now guaranteed on every save rather than occasional.

### One thing I could not close on this lens

If any deployment points `source_root` at a Basic-auth-protected Central mirror and relied on `user:pass@` in the URL, `wordcamps()`/`meetups()` now send no credential at all — `central_args()` is applied only on the Campus Connect calls, not on the five syncs' fetches. That is the intended security ruling rather than a defect, but it is the one input under which the five syncs behave differently and worse than at `740a504`, so it should be a documented upgrade note rather than a silent change.

---

## Lens: Secrets

**LENS: CREDENTIAL SAFETY, END TO END. No file was edited (`git status` is byte-identical to the state I was handed: the six files the fix agents touched, nothing else).**

## Verdicts

| Item | Verdict |
|---|---|
| Secrets #2 — remove a DB credential from WP-CLI once the constants exist | **CLOSED** |
| Secrets #4 — `user:pass@` root at rest in `wp_options` | **PARTIAL / OPEN** (two residuals, one of them the other half of the original finding) |
| Literal secret anywhere in tree or git history | **CLEAN** |
| CLI prompt cannot echo | **CLOSED for the three named platforms**; one measured 3-5 ms ordering window remains, reproduced |
| Live password paths (wire, log, render, state, REST, uninstall) | **SOUND** |

---

## Secrets #2 — CLOSED

Executed, not read. Harness at `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/sec/` (real `WCAC_Settings` + real `WCAC_CLI` bodies under WP stubs; every credential value generated with `random_bytes()`, never a literal).

`class-wcac-cli.php:457-461` answers `--forget` **before** the constant guard at `:472`, and `forget_central()` (`class-wcac-settings.php:261-282`) empties both keys unconditionally:

```
constants SET,  DB pair stored, --forget   -> user=EMPTY pass=EMPTY, actions fired: none
constants SET,  DB pair stored, --user=x   -> row byte-unchanged, ERROR naming both constants and --forget
constants NONE, DB pair stored, --forget   -> user=EMPTY pass=EMPTY, actions: wcac_central_credential_changed
constants NONE, empty row,      --forget   -> actions: none  (no-op does not unlatch)
```

The refusal at `:472` fires before `read_secret()` at `:492`, so STDIN is never read and the misleading constant-fingerprint confirmation at `:503` is unreachable. The admin mirror (`class-wcac-admin.php:307-313` answering `clear_central` ahead of the constant check at `:317`) is intact, and the Remove button at `:897` renders on `$has_db_copy` with `$locked` included. Both surfaces now agree: store refused, remove allowed.

Two things I checked and will not call findings:

- `--forget=0` / `--no-forget` fall through to the store path and error out (`! empty()` at `:457`). That is the correct reading of the flag, not a defect.
- `WCAC_Settings::save()` still accepts `central_user` with no constant check (`class-wcac-settings.php:341-350`) — the re-audit's "lower severity, same shape". It is now the **only** unguarded writer left. Trigger: a hand-crafted POST to `admin-post.php?action=wcac_save` carrying `central_user`, with a valid `wcac_save` nonce and `manage_options` (`class-wcac-admin.php:96-103`). Consequence: a username-half orphan row under constants. It cannot create a password orphan (grep for `$input['central_app_password']` returns nothing), and `$has_db_copy` surfaces it with a working Remove button. Genuinely low; not on the closing list; left open deliberately.

## Secrets #4 — PARTIAL, still open at rest

The `wp_options` half works. Real `maybe_upgrade()` over eleven stored shapes:

```
https://acct:PW@central.wordcamp.org           REWRITTEN  clean  warn "…treated as exposed and rotated"
https://acct:PW@central.wordcamp.org/          REWRITTEN  clean
https://acct@central.wordcamp.org              REWRITTEN  clean  warn "a username was removed…no password"
https://:PW@central.wordcamp.org               REWRITTEN  clean
https://acct:PW@central.wordcamp.org/wp?x=1#f  REWRITTEN  clean
HTTPS://acct:PW@central.wordcamp.org           REWRITTEN  clean
https://central.wordcamp.org/                  untouched, log=0   <- NEW-2 stays closed
https://central.wordcamp.org?utm=1             untouched, log=0
central.wordcamp.org                           untouched, log=0
//acct:PW@central.wordcamp.org                 UNTOUCHED, STILL HAS USERINFO, log=0
acct:PW@central.wordcamp.org                   UNTOUCHED, STILL HAS USERINFO, log=0
```

No log line ever carried the value. Reach is right: `wordcamp-airtable-connector.php:124-134` adds `init` under `(defined('WP_CLI') && WP_CLI) || wp_doing_cron()`, and `DOING_CRON` is defined in `wp-cron.php` before `wp-load.php`, so it is true at plugin-load time on the loopback path; `class-wcac-cli.php:33-35` covers dispatch (WP-CLI's `is_good_method()` skips `__`-prefixed methods, so no subcommand is exposed). The two halves overlap on `wp wcac …` — one extra `get_option`, harmless. The only context I could not verify without a WordPress checkout is `ALTERNATE_WP_CRON`, and it is empty in practice: alternate cron only fires inside front-end web traffic, which is not the CLI/cron-only profile the finding targets, and `admin_init` covers such a site anyway.

**Residual 1 (the other half of the original finding, unaddressed) — legacy `wcac_log` rows still carry the credential in cleartext, and the admin still prints them.**
`WCAC_Logger::log()` redacts `context` (`class-wcac-logger.php:50`) but stores `message` verbatim (`:49`). Baseline 1.0.x built Central URLs from the raw option (`git show 740a504:includes/class-wcac-source.php:116,139`), baked the full URL into `wcac_source_http` / `wcac_source_json` (`:57,:65`), and logged it (`git show 740a504:includes/class-wcac-sync.php:401,475`). Measured on the current logger:

```
message 'WordCamps fetch failed: HTTP 503 from https://acct:<pw>@central.wordcamp.org/wp-json/…'
   -> stored verbatim, password present
context array( 'url' => same string )
   -> stored verbatim too: scrub() (class-wcac-logger.php:116-133) has no '://user:pass@' rule
```

Trigger: any non-200 or unparseable response from Central on a 1.0.x site whose `source_root` carried userinfo — the exact site `maybe_upgrade()` exists for. Consequence: after 1.1.3 repairs `wcac_settings`, the same credential is still in `wcac_log`, in every DB export and `wp option get wcac_log`, and is rendered on the settings screen by `render_log()` (`class-wcac-admin.php:936`, `esc_html` only). The rolling 200-entry cap ages it out on a busy site and never on a quiet or broken one. This is not a new idea: `docs/2026-09-02-campus-connect-sync-analysis.md:605` named `wcac_log` as the sink in the first place; the fix closed the forward path only. Narrowest remedy: when `maybe_upgrade()` actually rewrites a root carrying userinfo, purge or scrub `wcac_log` in the same pass (and add the value-level `://…:…@` rule to `scrub()`, which the re-audit already asked for as hardening).

**Residual 2 — a `user:pass@` root that `strip_userinfo()` cannot parse is kept forever, silently.**
`class-wcac-settings.php:455-457` admits the row (userinfo present), then `:461` returns on `'' === $safe`. For `//acct:pw@central.wordcamp.org` (protocol-relative) `strip_userinfo()` (`:488-505`) demands a scheme, returns `''`, and the cleartext row survives with **no log line at all**. Reachable from 1.0.x's own form: baseline save was `untrailingslashit( esc_url_raw( trim(...) ) )` (`git show 740a504:includes/class-wcac-settings.php:88`), and `esc_url()` neither prefixes `http://` (first char is `/`) nor rejects it (`wp_kses_bad_protocol` finds no scheme before the first `:`), so it stores verbatim. The stated justification for declining — "rewriting would discard a value the operator may still be able to repair by hand" (`:439-441`) — does not hold for *this* subclass: `source_root()` already returns `''` for it (verified), so all six syncs are already dead, the admin refuses to print it (`class-wcac-admin.php:796-806`), and a blank box will not clear it (`class-wcac-settings.php:322-335`). The operator can only overwrite it by typing a new URL; doing nothing keeps cleartext indefinitely. `acct:pw@host` (no scheme) behaves the same but is not reachable through 1.0.x's `esc_url_raw` — hand-edit or migration only. Low likelihood, real consequence; the narrow fix is to let the `'' === $safe` branch blank a row that carries userinfo, since nothing readable is lost.

## Literal-secret sweep — CLEAN

Working tree (all file types, `.git` excluded) and full history (`git log -p --all`) against: the six-group application-password shape, `[A-Za-z0-9]{24}`, `pat…`/`key…` prefixes, `Basic <b64>` / `Bearer <token>`, `://user:pass@`, and any base64-ish run of 40+. Every hit is prose inside `docs/*.md` describing the defect, with invented placeholders (`mp:abcd1234efgh`, `someone:hunter2`, `abcd EFGH ijkl MNOP qrst UVWX`). No `.env`, no config fragment, no untracked file; `.gitignore` is three lines. Nothing credential-shaped was introduced by this fix round.

## CLI prompt echo — CLOSED, with one measured window

Driven through a real pty with `expect`, probe values generated per run and never printed:

```
STDIN = file ('< secret.txt')                -> no prompt, no stty, 16 chars read       PASS
pty + php -d disable_functions=shell_exec    -> refused at cli.php:1523 before any read PASS
pty, stty available, value sent 0.05-1.0 s
  after the prompt                           -> value never appears on the pty          PASS
pty, value sent the instant the prompt
  is seen                                    -> value ECHOED on the pty (1 of ~19 runs)
```

The ordering at `class-wcac-cli.php:1527-1530` prints the prompt **first** and disables echo **second**. Measured cost of `shell_exec('stty …')` on this host: 3.4-5.4 ms. Anything arriving in that window, or already sitting in the tty input queue as type-ahead — an operator pasting command+password from a note, or a password manager's type-into-terminal — is echoed by the line discipline and stays in scrollback, under a prompt that says `(not echoed)`. Narrowest fix: issue `stty -echo` before `WP_CLI::log()` (the `-g` probe at `:1519` has already proven stty works, so it can be one shell_exec: probe and disable, then prompt). Everything else about this path is sound: `escapeshellarg`-restored exact state in `finally` (`:1536`), `stream_isatty` first so slim Docker and Windows reach the refusal, username in argv but never the secret, and WP-CLI's `--prompt` cannot reach the password because it is not in the synopsis (`:437-442`).

## Full path trace — sound

- **Wire.** `$cred['pass']` has exactly two consumers: `class-wcac-source.php:337` (`base64_encode` into `headers`, with `redirection => 0` at `:339` and the https gate at `:328`) and `class-wcac-settings.php:196` (salted fingerprint). Every Central URL comes from `central_root()` → `source_root()` (`:419`), so the URL in `wcac_source_http` / `wcac_source_json` cannot carry userinfo. The unauthenticated probe `central_auth_advertised()` passes no overrides, so it never attaches the header. No TOCTOU between `central_scheme_ok()` and the fetch: both read the same stripped accessor, and no request path saves settings mid-request.
- **Log.** All 43 `WCAC_Logger::log()` call sites pass either no context or `array( 'page' => $n )` — I grepped for any other shape and got nothing. `redact()` catches the named trap (`array('args' => $args)` with a live `Authorization`).
- **State.** `cc_last_error` (`class-wcac-sync.php:802-808`) stores `get_error_message()`, whose URL is already stripped; baseline persisted no messages at all, so `wcac_state` has no legacy copy.
- **Render.** Both password inputs (`class-wcac-admin.php:720`, `:848`) emit a placeholder and no `value`. `render()` blanks `central_app_password` at `:496` before ~490 lines of markup, and takes only a boolean from the raw root at `:493-494`.
- **REST / options.** No `register_setting`, no `show_in_rest`, no `register_rest_route`, no `wp_ajax`, no `error_log`/`print_r`/`var_dump`/`file_put_contents`/`wp_mail`/`wp_localize_script` anywhere in `includes/` or the two root files. Admin surface is `manage_options` throughout (`class-wcac-admin.php:16`).
- **Autoload.** Every writer passes `false`, and so did baseline (`git show 740a504:includes/class-wcac-settings.php:103`) — so there is no legacy autoloaded row putting both secrets into `alloptions`. The comment at `class-wcac-settings.php:212` is earned.
- **Uninstall.** `uninstall.php:31-33` deletes `wcac_settings`, `wcac_log`, `wcac_state`, `wcac_queue`, `wcac_camps` — which also disposes of residual 1 above, for anyone who uninstalls.
- **Fingerprint.** 12 hex of `sha256(user|pass|wp_salt('auth'))`; irreversible against a 24-char alphanumeric application password without the site's salts.

One documentation nit, not a finding: `readme.txt:134-135` teaches `< secret.txt` without telling the operator to delete that file afterwards.

---

## Lens: Integrity

No files were edited. Here is the audit.

## `php -l` — all 10 PHP files pass

`wordcamp-airtable-connector.php`, `uninstall.php`, and all eight `includes/class-wcac-*.php`: **No syntax errors detected.** (Local PHP is 8.5.7, so this proves parsing only. A separate sweep of every `^+` line in `git diff HEAD` for `?->`, `match(`, `enum`, `readonly`, `#[`, promotion, `str_contains`/`str_starts_with`/`str_ends_with`/`array_is_list`, `??=`, arrow fns returns **zero hits** — 7.4-clean.) ABSPATH guard present in all nine plugin files at line 8/17; `uninstall.php` correctly guards on `WP_UNINSTALL_PLUGIN`. Zero em dashes in any `.php` or `readme.txt`.

## Mechanical cross-file sweeps — clean

- **Symbol resolution.** Token-stripped pass over all 8 classes: every `Class::member` reference resolves to a declared method/const/static prop, and no cross-file reference reaches a non-`public` member. Every `$this->m()`, `$this->prop`, `self::m()`, `self::CONST`, `self::$prop` resolves inside its own class. Zero misses.
- **New/changed cross-file signatures all match their callers**: `WCAC_Settings::maybe_upgrade()` (public static, 0 args; called at `wordcamp-airtable-connector.php:121` and `class-wcac-cli.php:34`), `forget_central()`, `credential_fingerprint()` (both arities), `central_credential()['source']` (`admin.php:316,826`, `cli.php:473`), `run_slice( $budget = null, $only = '' )` against all four call sites, `job_name()`/`job_summary()` (private, same class).
- **Settings keys.** Every read (`api_key, base_id, campus_extra_status, lookback_hours, time_budget, sync_*`, plus `tbl_*` via `table_for()` with `wordcamps|meetups|sessions|speakers|sponsors|campus_connect`) has a declared default at `settings.php:38-58`. Still **zero `get( 'source_root' )` outside `WCAC_Settings`**.
- **State keys.** All 22 keys read via `$state[...]`/`cc()`/`state_value()` are declared at `sync.php:121-145`, `cc_zeroes` included. Three declared-but-unread (`started`, `finished`, `last_sync`) are pre-existing and written by `set_state()`.
- **Constants.** `WCAC_VERSION/FILE/DIR/URL/CRON_HOOK/CONTINUE_HOOK` defined at `:19-33`; `WCAC_CENTRAL_USER`, `WCAC_CENTRAL_APP_PASSWORD`, `WCAC_ALLOW_INSECURE_CENTRAL` all `defined()`-guarded. `uninstall.php:31-38` option/hook strings match their constants. Version `1.1.3` agrees across header, `WCAC_VERSION`, `Stable tag`.
- **Notice keys.** Every key `handle_save`/`handle_action`/`handle_central`/`test_central` sets (`saved, save_rejected, queued, cc_moved, drained, cleared, log_cleared, test_ok, test_failed, central_saved, central_cleared, central_locked, central_ok, central_401, central_403, central_failed`) exists in `notice()`'s map; no orphans either direction. Both `$rejected` tokens `save()` can append (`source_root`, `central_user`) are whitelisted in `rejected_notice()`'s `$labels`.
- **Sentinels.** `grep '(empty)'` over `includes/` and `readme.txt` returns **nothing outside historical changelog prose**. All five live sentinel sites read `(blank)`: `sync.php:1207`, `cli.php:646, 687, 1168, 1182`.
- **Five syncs.** Normalised method-body comparison against `740a504`: `job_wordcamps, job_meetups, job_event_data, sync_collection, tally, push, queue, set_queue, clear_queue, pending, error_status, set_state, camps, remember_camps` all **byte-identical**. `run_slice`'s only deltas vs baseline are the `$only` peek, the `$long_lock` line and the `$only` cast — with `$only=''` and a non-`cc` head the path is baseline-identical.

---

## Verdicts

### Contract NEW-1 — **CLOSED**
`cli.php:139-152` and `admin.php:552-560` now branch identically. Admin: `$cc_block` → `! $cc_configured` → `$cc_ready` → else. CLI: `$blocked` → `! campus_connect_ready()` → `$stamped > 0 && ! $stale` → else. In an elseif chain the third admin test reduces to exactly the CLI's, and the fourth string is byte-identical on both sides (`configured, but the preflight below has not passed`). The blocked predicates also agree: `cli.php:1396-1400` and `admin.php:513-514` both mirror `sync.php:534-539`'s 12-hour window. The false justification comment is gone (`admin.php:497-503`), and `readme.txt:421-423` now says `wp wcac status` *"went on reporting the job as ready until 1.1.3"` — true as built.

### Contract NEW-2 — **CLOSED in code; one surface now contradicts it (see finding 2)**
`settings.php:268` captures `credential_fingerprint()` before the write, `:280` gates the `do_action` on it having moved — the same shape as `set_secret():230,241`. Under constants `credential_fingerprint()` short-circuits to the constant on both sides, so removing a DB copy fires nothing while still emptying the row, keeping `admin.php:897`'s Remove-under-`$locked` button and `cli.php:456` `--forget` working. `readme.txt:424-429` is now accurate.

### Contract NEW-3 — **CLOSED**
`cli.php:687` is `'(blank)'`. `readme.txt:339-340` no longer claims the spelling was unified in 1.1.1; it says 1.1.1 left `--statuses` printing `(empty)` and 1.1.3 fixed it. Claim matches build.

### Contract NEW-4 — **CLOSED**
`settings.php:455-457` returns early unless `wp_parse_url()` yields `user` or `pass`, so a trailing slash, query string or fragment no longer triggers a rewrite or an alarm; `:470-474` splits the message so a username-with-no-password gets its own line rather than "treat it as exposed and rotated". This gate is load-bearing for the new headless hook at `wordcamp-airtable-connector.php:132-134` — without it, cron would silently rewrite the URL all six syncs fetch. It is in place.

### Data loss D3 — **CLOSED for all three named residuals; two residuals remain, stated below**
I ran the `sync.php:1398-1456` block verbatim against a 142-row report:

```
run 1  fresh install, steady state  WARN  Series Event 0 -> 140
run 2  steady state, baseline set   INFO  140 cell(s), no field above previous
run 3  Actual Attendees regression  WARN  Actual Attendees 0 -> 142; 282 cell(s) in all
run 3b same, one chunk of 10 failed WARN  Actual Attendees 0 -> 132; Series Event (130)
dup ID, surviving row non-zero      (no line)   surviving row zero -> WARN, counted once
```

All three under-deliveries are genuinely fixed: failed chunks subtracted (`$entry['keys']` are the `WordCamp ID` merge values written at `airtable.php:220-226`, and `mapper.php:830` guarantees that key is present in every payload, so the subtraction cannot silently no-op); counting per surviving merge value; steady-state `Series Event` demoted to `info`. `cc_zeroes` is declared at `sync.php:143` and written only at `:1434`, after `cc_volume_ok()` and after the upsert, so a refused or wholly-failed run never becomes the baseline.

---

## Findings

### 1. OPEN — `run_slice()`'s new `$only` parameter has zero callers, so R4 residual 1 (the prevention half) is still open and the code written to close it is dead

- `includes/class-wcac-sync.php:354` — `public function run_slice( $budget = null, $only = '' )`
- `includes/class-wcac-cli.php:1253` — `$result = $sync->run_slice( 1 );`

`grep -rn run_slice` over the tree returns four call sites: `wordcamp-airtable-connector.php:73`, `admin.php:165`, `cli.php:80`, `cli.php:1253`. **None passes a second argument.** The parameter is unreachable.

Two docblocks state the contract the only relevant caller violates:
- `sync.php:335-338`: *"`$only` confines a slice to one kind of job, and exists for `wp wcac campus-connect --run`: a command named after one table must not write to the other five."*
- `sync.php:627-629`: *"Putting cc at the head is only half of that: the caller must also pass `'cc'` as `run_slice()`'s `$only`."*

**Trigger:** any site that has not run `wp wcac campus-connect --preflight` (the default; `sync.php:1100-1115` returns immediately) with at least one `wc`/`mu`/`kid` job already queued. Operator runs `wp wcac campus-connect --run`. `queue_campus_now()` puts `cc` at the head; `run_slice(1)` runs it in ~0ms; the budget is only tested *between* jobs, so the loop re-enters and shifts the next job — a real `wordcamp.org` fetch and a real Airtable PATCH into `tblLBVsO4rU2oj2Ki` from a command named `campus-connect`. Bounded at roughly one second's worth of jobs (`campus_queued()` is then false, so only one slice runs) rather than the pre-fix fifty, but not zero.

The **reporting** half is genuinely closed and correct — `cli.php:1255,1284-1286,1297-1305` sum `processed`, attribute the first `processed` entries of the pre-slice queue snapshot by job code, and raise a `WP_CLI::warning` naming them. The attribution is sound (`run_slice()` shifts from the head, `push()` only appends). `readme.txt:331-334` describes only the reporting half and so is not a false claim.

This is a hand-off gap, not a judgement call: the sync-side report says *"Without this argument my half is inert and residual 1 stays open"*, and the CLI-side report says the prevention half *"is in `run_slice()`/`job_campus_connect()`, not mine"*. Each assumed the other wired it.

**Narrowest fix:** one argument at `cli.php:1253` → `$sync->run_slice( 1, 'cc' )`. It cannot regress the five syncs (every other caller keeps the default `''`, which is baseline-identical), and it cannot loop: with `$only='cc'` and a non-`cc` head the call returns `processed => 0`, which `cli.php:1290-1293` already handles by warning and breaking.

### 2. OPEN (low) — the admin and the CLI now disagree about what removing a DB credential under wp-config constants does; the admin's text is the one that is wrong

- `includes/class-wcac-admin.php:374` — `'central_cleared' => array( 'warning', __( 'Central credential removed. The Campus Connect sync cannot run until a new one is stored.' ...`
- `includes/class-wcac-cli.php:459` — `WP_CLI::success( 'Stored Central credential removed. A wp-config.php constant, if you use one, is untouched.' );`

**Trigger:** `WCAC_CENTRAL_USER` + `WCAC_CENTRAL_APP_PASSWORD` defined in wp-config, plus a stale DB copy. `admin.php:891-897` renders **Remove stored credential** (this is the S2 fix, and correct); `handle_central()` answers `clear_central` at `:309-312` before the constant check at `:317`, calls `forget_central()`, and redirects to `central_cleared`.

Post-NEW-2 this path is a deliberate no-op on the effective credential — that is the whole point of the fix — yet the notice tells the operator the sync has stopped. It has not; it keeps running on the constant. The same action from WP-CLI says the opposite, correctly. This is squarely the "admin and CLI agree" contract, and it sits on the exact path Contract NEW-2's fix turned into a button.

**Narrowest fix:** one extra notice key chosen in `handle_central()` when `'constant' === WCAC_Settings::central_credential()['source']` at the time of the clear. No data-layer change.

### 3. Residual (not a defect) — D3's alarm is one-shot for a persistent regression

Measured above, run 3 vs run 4: a report regression that zeroes `Actual Attendees` on all 142 rows warns **once**, then logs at `info` on every subsequent run while 142 curated cells continue to be flattened, because `$count > $before` is false once the baseline has absorbed the new profile. That is inherent to comparing against the previous run, and it is a real improvement on the pre-fix behaviour (a `warn` that fired every run and was therefore ignorable). But it should not be described as a standing tripwire: the single `warn` sits in a 200-entry ring (`logger.php:16`) alongside every other line the plugin writes. A "rise, or above the highest ever seen" comparison would keep it standing; `cc_high_rows` at `sync.php:724` is the precedent already in the file.

Second, smaller: on the **first** run after upgrade the baseline is empty, so the ordinary steady state (`Series Event` 0 on ~140 non-series camps) fires a `warn` reading `Series Event 0 -> 140`. One-time and self-correcting on run 2, but it is a false alarm on the run an operator is most likely to be watching.

### 4. Cosmetic — `maybe_upgrade()` now runs twice per WP-CLI invocation

`wordcamp-airtable-connector.php:132-134` adds it on `init` when `WP_CLI`, and `cli.php:33-35` calls it from `WCAC_CLI::__construct()`. Both halves landed. Idempotent and cheap (one `get_option`, early return unless the authority really carries userinfo), and WP-CLI's `CommandFactory` skips `__`-prefixed methods so the constructor is not exposed as a subcommand — so this is redundancy, not a defect. Either one alone would do; the bootstrap hook is the broader of the two, since it also covers the cron-only site.

---

## Also verified sound, stated plainly

- **Regression R3 is properly closed.** `sync.php:430` is `( defined( 'WP_CLI' ) && WP_CLI ) || 'cli' === php_sapi_name()`. `wp_doing_cron()` is gone from the predicate, so the loopback `wp-cron.php` fetch (SAPI `fpm-fcgi`/`apache2handler`) takes the baseline 60s lock and a kill there strands the five syncs for at most 60s, as at `740a504`. A crontab entry running `php wp-cron.php` or `wp cron event run` still gets the 300s extension. The `$long_lock` line is evaluated on every job but acts only on `'cc' === $job['to']`, so the five syncs never see it.
- **Regression NEW-1's two halves match.** `settings.php:322-334` refuses a blank `source_root` while a value is stored, keeps the stored bytes and appends the existing `'source_root'` token; `admin.php:493-496` derives `$root_unreadable` from the raw value and immediately drops it (only a boolean escapes — no new `get('source_root')` reader, S3/S4 stay closed); `admin.php:796-806` explains the empty box; `rejected_notice():448-452` names the second refusal reason under the same token, which is already whitelisted. No third key was invented, so `handle_save():108-121` needed no change and got none.
- **Secrets #2 is closed and mirrors the admin.** `cli.php:473` refuses a store under the constants, placed *after* the `--forget` early return at `:456`, exactly as `admin.php:309-312` answers `clear_central` before its constant check at `:317`. A DB copy stays removable from both surfaces while locked.
- **Secrets #4 at rest is closed on both headless paths** (`init` under WP-CLI or `wp_doing_cron()`), and `wp_doing_cron()` is safe to call at plugin-load time — it lives in `wp-includes/load.php`, loaded long before plugins, and `wp-cron.php` defines `DOING_CRON` before requiring `wp-load.php`. The `init` hook also fires before `WCAC_CRON_HOOK`, so the row is repaired before the sync reads it.
- **The settings form and the credential form are separate `<form>` elements** (`admin.php:712-812` posting `wcac_save`, `admin.php:832-901` posting `wcac_central`), so `central_user` never reaches `WCAC_Settings::save()` from the UI. `save()`'s missing constant check on `central_user` (`settings.php:343-352`) needs a hand-crafted POST and creates only a username-half orphan that `admin.php:882`'s `$has_db_copy` surfaces. Correctly left open.
- **`uninstall.php` still needs nothing** — `cc_zeroes` lives in `wcac_state`, already deleted at `:31`.

---

## Lens: Fresh eyes (hostile first read)

# Hostile read: would I let this run unattended against 142 irreplaceable rows?

Short answer: not yet. The mapper and the write verb are genuinely good — `campus_event()` (`class-wcac-mapper.php:808-895`) emits keys only for usable values, `campus_int()` returns `null` rather than `0` for `'n/a'`, and the PATCH-upsert at `class-wcac-airtable.php:204` cannot clear an omitted cell. The *cell-level* non-destructive guarantee holds. What does not hold is everything at the level above it: **row creation, failure escalation, and concurrency.** Those three are what would actually bite.

---

## 1. The write path is blind, and nothing anywhere alarms on record CREATION

`class-wcac-airtable.php:189` + `:204` — `performUpsert` on `WordCamp ID`. A match updates; **a miss creates**. `class-wcac-sync.php:1299` sends 142 records through it, and the sync path never reads Airtable back (`class-wcac-airtable.php` docblock on `list_records()` says so explicitly: preflight and dry run only). So the sync has no idea which IDs exist.

Triggering inputs, in descending likelihood for month one:

- **Someone edits a `WordCamp ID` cell in Airtable.** A mis-drag on a fill handle, a paste one column off, a cleared cell. The preflight validated uniqueness *once*; the gate at `class-wcac-sync.php:1100` only re-checks that the table **ID** is unchanged, never the table's **content**, and there is no age rule (`class-wcac-admin.php:508`, `class-wcac-cli.php:137` compute `stale` from the table ID alone). The admin cheerfully reports "passed 3 weeks ago". Next tick: that row's report counterpart matches nothing and a fresh row is created beside the curated one. Permanent — there is no delete verb (`readme.txt:177`).
- **Central changes what `ID` means** in `campus-connect-details` (a different post type, a term ID, a site ID). One unattended tick turns 142 curated rows into 142 curated rows *plus* 142 orphans.

The observable consequence is nil, because nothing watches `$created`:
- `class-wcac-sync.php:1322` computes it, `:1370-1379` prints it inside an **`info`** line, and `:1364` folds it into `tally()`.
- The admin shows one cumulative "Rows created / updated" figure (`class-wcac-admin.php:553-554`) shared with the WordCamps, Meetups and children syncs, so 142 Campus Connect creates are indistinguishable from a normal camp crawl.
- The Campus Connect row itself reports `cc_last_written` (`class-wcac-admin.php:628`, `class-wcac-cli.php:185`), which is created **plus** updated. There is no surface anywhere that separates them.

And the guard that is supposed to cover this measures the wrong quantity. `cc_volume_ok()` (`:847-895`) says in its own docblock that it exists because a report regression "would CREATE ~1,500 rows" — but it tests the **incoming row count**, which is unchanged in both scenarios above. Worse, it ratchets: `cc_ok()` raises `cc_high_rows` to whatever was accepted (`:723`), and the ceiling is `max($high + 50, $high * 1.5)` (`:869`). From 142 that is 142 → 213 → 320 → 480 → 720 → 1080 → 1620. **Six accepted runs reach the exact number the guard was written to prevent**, and every one of them is under the ceiling, so nothing blocks and nothing warns.

Narrowest fix: `cc_ok()` already knows `$created` separately — refuse (or `cc_block()`) when creates exceed a small absolute threshold, and make `cc_high_rows` only rise on an explicitly accepted growth rather than on every run.

## 2. The consecutive-failure latch is unreachable, because the only thing that schedules a run resets it

`class-wcac-sync.php:829` blocks after `$fails >= 5`. `cc_unblock()` (`:582-596`) sets `cc_fails = 0`. It is called unconditionally at the top of **every** enqueue path: `enqueue_full():225`, `enqueue_incremental():273`, `queue_campus_now():635`.

There is no way to get a second `cc` job into the queue without going through one of those three. `readme.txt:96-102` states plainly that nothing in the plugin queues anything — the five-minute cron only drains. So the second cc run *always* begins with `cc_fails` reset to 0.

**`cc_fails` can therefore only ever hold 0 or 1, and `$fails >= 5` at :829 is dead code.** The same applies to the twelve-hour latch expiry at `:534-539`: an immediate block set by one run is wiped by the next run's enqueue, long before twelve hours.

Triggering input: any repetition at all — a crontab entry running `wp wcac sync` hourly (the only way to operate this unattended), or a human pressing "Sync changes now" twice.

Observable consequence, per condition that was designed to latch on the five-count:
- `wcac_central_empty` (`:1130`) — the comment says "Routed through cc_fail() so it latches rather than repeating forever." It repeats forever.
- `wcac_central_envelope` (a maintenance page or WAF body with HTTP 200) — same.
- `wcac_airtable_all_chunks` (`:1354`) — the comment says "a persistent 422 could then never reach the latch." It still never does.
- Airtable 5xx, Central 500, request timeouts.

And the immediate latches (401/403/404/redirect, `:786-798`) are cleared just as reliably, so a revoked application password produces **one rejected HTTP Basic auth against wordcamp.org per scheduled run, indefinitely**, each preceded by a full server-side report generation on a third party's infrastructure. That is precisely the behaviour `without_queued_duplicates()` (`:493`) says it exists to prevent: "three presses of 'Sync changes now' would mean three more rejected Basic auth attempts against a third party's server."

Narrowest fix: `cc_unblock()` should clear `cc_block`/`cc_block_why` (the operator saying "try again") without zeroing `cc_fails`, or zero it only on an explicit operator action rather than on the scheduled `enqueue_incremental()`. Note `cc_partial` is already handled this way and consequently *does* latch (`:751`) — the asymmetry looks accidental.

## 3. The 60s lock is shorter than the job it guards, and the job leaves the queue before it runs

`run_slice()` sets `max(60, $budget * 3)` (`:367`); `time_budget` defaults to 20 (`class-wcac-settings.php:54`), so the lock is **60 seconds**. The long-lock extension at `:430-433` deliberately excludes the web and loopback-cron paths. The code's own estimate of a cc job is 45s report + ~15 PATCHes, and a single Airtable 429 adds a 30s sleep inside `request()` (`class-wcac-airtable.php:137`). **90s is an ordinary run, not a pathological one.**

Meanwhile `:390-391` shifts the job off the queue and persists the shortened queue *before* running it, so while cc is executing `campus_job_pending()` (`:515`) returns false and the dedupe at `:493` sees nothing.

Trigger: the WP cron tick starts a cc job at t=0. The lock evaporates at t=60. At t=61 the hourly `wp wcac sync` fires (or a human presses "Process queue now", `class-wcac-admin.php:165`). `enqueue_incremental()` pushes a **second** cc job, `run_slice()` finds no lock, and a second Campus Connect upsert runs against the same table concurrently with the first.

Consequences, in order of severity:
- Two concurrent `performUpsert` requests carrying the same merge value that is **not yet in the base** can both find no match and both create. That is a duplicate pair on a table with no delete verb, and it then fails every future chunk containing that ID (10 rows starved per run) until `cc_partial` reaches 5.
- Two processes doing non-atomic read-modify-write on `wcac_queue` and `wcac_state` — the exact hazard the comment at `:396-412` names, then declines to fix for this path.
- `WCAC_Logger::log()` is a full read-modify-write of a 200-entry option (`class-wcac-logger.php:37-54`); concurrent writers silently drop entries, including the warnings in item 4.

Narrowest fix: hold the concurrency lock for the cc job on every SAPI, and re-check `campus_job_pending()` against a "currently running" marker rather than against the queue alone.

---

## One item marked closed that I do not think is

**The zero-write alarm now goes silent on run 2.** `class-wcac-sync.php:1420-1466`: `$risen` is populated only when `$count > $before`, and the baseline `cc_zeroes` is written at `:1436` on the same run that raises the alarm. So a report regression that starts sending `"Actual Attendees": 0` for all 142 rows produces exactly **one** `warn` line, and every subsequent run — while it keeps re-writing the same 142 zeroes — logs `info`. Two aggravating details: `cc_zeroes` defaults to `array()` (`:143`), so the *first healthy run* of any install emits the same `warn` shape ("Series Event 0 -> 140"), teaching the operator that this warning is noise; and `cc_zeroes` is rendered nowhere in the admin or CLI, so the state that would let anyone reconstruct it after the log ring rotates is invisible. The de-noising fix removed the false positives by removing the alarm's persistence. This is still the only unbounded overwrite path in the plugin and its sole control is a log line that fires once.

## Smaller, but I would fix before going unattended

- **`preflight_drift()` is one-directional** (`class-wcac-cli.php:930`): it reports Airtable rows absent from the report, never report IDs absent from Airtable — which is exactly the set that will be created. The operator is shown the harmless direction and not the destructive one.
- **`Series Event` is written but excluded from the only review that exists.** `campus_dry_run()` is documented as "the only review the 142 hand-curated rows get" (`class-wcac-cli.php:951`), yet `:1119` states Series Event is not in the diff. A field the sync writes to every row is invisible to the pre-flight review of that write.
- **`$keys` is reused for two unrelated things** in `job_campus_connect()` (the key report at `:1135`, then the per-chunk merge values at `:1400`). Harmless today only because the second use is after the last read of the first. It is one reordering away from a silent bug in the key-drift detector.

## What is genuinely sound

Say it plainly: the emit-only mapper, `campus_int()`'s null-versus-zero distinction, `campus_status()`'s default-deny against typecast minting select options, the envelope checks in `WCAC_Source::campus_connect()` (`:186-215`) that refuse a maintenance page rather than reading it as zero rows, the credential handling (never in a URL, never in a log, `central_args()` at `class-wcac-source.php:307`), and the choice of PATCH over PUT. Those are the parts most people get wrong, and they are right here.

---
