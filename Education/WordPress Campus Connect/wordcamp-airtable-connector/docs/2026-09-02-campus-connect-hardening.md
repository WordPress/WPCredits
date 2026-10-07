# Campus Connect sync — hardening round + verification (2026-09-02)

A (creation alarm / ratchet), B (failure latch) and C (concurrency) are CLOSED, proven by an
executable harness: 47 pass on the working tree vs 19 pass / 30 fail on the committed control,
so the harness demonstrably reproduces the defects it claims to fix.

**Open items are in the second hostile read below.** They are the state of the plugin as handed over.

---

## Lens: Structural (A/B/C proven by execution)

**VERDICT: A = CLOSED (one new residual, named below). B = CLOSED. C = CLOSED (bounded). Zero-write alarm = CLOSED.**

Everything below is executed, not read. Harness: `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/abc/` — `wcac_abc_bootstrap.php` (WP + Source/Airtable/Settings/Logger stubbed; **real** `WCAC_Sync` and **real** `WCAC_Mapper`), `wcac_abc_item_{a,b,c,d}.php`, `a7.php`. The Airtable stub is a real in-memory base keyed by merge value, so a `performUpsert` **match updates and a miss CREATES** — create counts are measured, not asserted. Every suite also runs against `git show HEAD:includes/class-wcac-sync.php` (`sync_HEAD_control.php`) as a control via `WCAC_SYNC_FILE`. **I edited nothing**: `git diff --stat` is unchanged (admin 116, cli 276, sync 282).

Totals — working tree 47 pass / 2 fail; same suites against committed HEAD 19 pass / 30 fail. The control reproduces every defect the review named, which is what makes the passes mean something.

## A — creation alarm and ratchet: CLOSED

| scenario | control (HEAD) | working tree |
|---|---|---|
| 6 unattended runs, 142→213→320→480→720→1080→1620 | high ratchets every run, **base reaches 1620, never blocked, never warned** | refused at the **first** step: 213-row report, `cc_high_rows` stays 142, `cc_block` set |
| 6 runs of the same 142 rows | high stays 142 | high stays 142 |
| fresh install, first report is the 1500-row regression | 1500 created, no block, next tick fetches again | 1500 created, **latched**; 3 further scheduled ticks create 0 and make **0 report requests** |
| Central redefines `ID` (142 rows, new ID space) | 142 orphans, silent | 142 orphans, then `error` + latch; 3 more ticks create nothing |
| one edited `WordCamp ID` cell | 1 orphan, no warn | 1 orphan + `warn: Campus Connect created 1 row(s)…` (below `CC_MAX_CREATES`=10, correctly no block) |

The ratchet is dead: `class-wcac-sync.php:810` (`'cc_high_rows' => $high > 0 ? $high : (int) $read`) seeds once; `:938-947` is the only place it moves. The 213-row run is stopped by the *creates* guard (`cc_creates_ok()` at `:1005`, called at `:1729` after `cc_ok()`), not the volume guard — 71 creates ≥ 10 → block. That is the right guard firing: the review's point was that creates, not row count, are the quantity that matters.

Honest limits, all by design and all confirmed: creates **happen** on run 1 (no read-back exists to pre-empt them); the guard stops run 2 compounding it. `cc_block` expires after 12h, so a persistent creates regression costs exactly one bad run per 12h (D.5: base 284→426, then re-latches). And one press of "Sync Campus Connect now" clears the creates latch and creates 142 more (D.6) — `queue_campus_now():698` → `cc_unblock()`, the operator override.

### The one thing I would not ship: a stale `--accept-growth` (A.7 / `a7.php`)

`cc_growth_once` is only consumed inside `cc_volume_ok()` (`:938-947`), which sits at `:1474`. Six early returns precede it — `:1277` (blocked), `:1300` (preflight), `:1308` (source error), `:1318` (empty report), `:1326` (ID key gone), `:1467` (all rows dropped). Any of those leaves the flag **armed indefinitely**, and it is rendered on no surface (`grep cc_growth_once` hits only sync.php; nothing in admin.php, cli.php or readme.txt).

Executed sequence, all realistic, no concurrency needed:

1. baseline good run — `cc_high_rows = 142`
2. operator runs `wp wcac campus-connect --run --accept-growth`; Central serves a maintenance page → `wcac_central_empty` at `:1315`. Non-immediate, so `cc_fails = 1`, **no block**. → `cc_growth_once = 1`, still armed.
3. days later, an unattended cron tick; the report regression arrives with 1500 rows:

```
created=1500  base=1642  blocked=no  high=1500
warn: Campus Connect created 1500 row(s) that the destination table did not already hold.
```

4. next tick: `blocked=no  high=1500` — the volume guard is now permanently re-based and will never fire on 1500 again.

`$accepted` at `:1473` waives **three** guards (volume `:1474`, creates `:1729`, zero profile `:1638`). Pre-fix the same stale flag waived one. So the fix widened what a forgotten flag silences, and the outcome is 1358 permanent orphans with a single `warn` line in a 200-entry ring. Narrowest fix, in the same spirit as the rest: consume/expire the flag at the top of `campus_connect_run()` (or stamp it with a run id / short expiry) so it cannot outlive the run the operator was looking at.

## B — consecutive-failure latch: CLOSED

Control vs working tree, `enqueue_incremental()` + `run_slice()` per cycle (a crontab `wp wcac sync`):

- **control**: `cc_fails` = 1, 1, 1, 1, 1, 1 — `$fails >= 5` unreachable, exactly as the review said.
- **working tree**: 1, 2, 3, 4, **5 → blocked**. `cc_fail():~934` is reachable. Reason string: *"Campus Connect has failed five times in a row…"*

Revoked application password (immediate 401 latch), 21 scheduled cycles:

- **control: 21 report requests** — 21 rejected Basic auths against wordcamp.org, each after a full server-side report generation.
- **working tree: 1**. Rewinding `cc_block` past 12h yields exactly **one** retry (total 2), then 10 more ticks add none. Retry rate 2/day, was 1/tick.

A blocked run makes **no** request at all (`campus_connect_run():1271` gates before `$this->source->campus_connect()`), and logs `warn: Campus Connect job skipped: the sync is blocked. …`.

Operator "try again" still works, both routes, verified to clear `cc_block` **and** `cc_fails` and to fetch on the very next run: `clear_campus_block()` (Test Central access / Clear the block / `wp wcac test` / credential saved via `wordcamp-airtable-connector.php:90`) and `queue_campus_now()` (Sync Campus Connect now / `wp wcac campus-connect --run`). No non-operator path resets the counter: `enqueue_full()` and `enqueue_incremental()` both leave `cc_fails` where it was.

Unchanged and correctly documented, not a regression: one good run resets `cc_fails` to 0 (`cc_ok():795`), so a feed that fails four times and succeeds once can never latch. `cc_partial` is the counter that covers the starvation case.

## C — concurrent Campus Connect runs: CLOSED (a bound, not a mutex)

Process 2 is driven re-entrantly from inside process 1's `upsert()`, with `wcac_lock` deleted first — which is precisely what expiry at t=60 does.

| overlap scenario | control (HEAD) | working tree |
|---|---|---|
| hourly `wp wcac sync` enqueues + drains mid-run | p2's queue = `["wc","mu","cc"]`, **2 report fetches, 2 upserts** | p2's queue = `["wc","mu"]` — the cc job is never queued (`without_queued_duplicates():528` → `campus_job_pending():565` → `cc_running():551`). 1 fetch, 1 upsert |
| "Sync Campus Connect now" pressed mid-run (cc job really is in p2's queue and really is shifted) | 2 upserts | 1 upsert + `warn: … another Campus Connect run is still going` (`job_campus_connect():1220`) |
| **the duplicate-pair case**: 3 report IDs absent from the base, p1's creates not yet committed when p2 upserts | **p2 created 3 rows** that p1 then created again — the permanent duplicate pair, on a table with no delete verb | **p2 created 0**; exactly 3 rows exist, once each |

`CC_LOCK` is set at `:1227` with **no SAPI condition** and released in `finally` at `:1232`, so this holds on web, loopback cron and CLI alike — it does not depend on `run_slice()`'s `php_sapi_name()` extension. A thrown exception mid-upsert releases the marker (verified); a clean run leaves neither lock behind.

**Residual, declared and real (C.4):** the marker is a 900s TTL, not a mutex. Advancing past `CC_LOCK_TTL` mid-run lets both processes upsert (2 upserts in the overlap). That is reachable, not merely theoretical: `WCAC_Airtable::BATCH = 10` → 15 chunks for 142 rows, and `request()` at `class-wcac-airtable.php:136` sleeps **30s per 429** with `$max = 4` attempts, i.e. up to 90s per chunk = 1350s for a sustained rate-limit, plus a 45s report. Under sustained 429s a run can outlive 900s. The exposure is bounded to that case, and is strictly better than the pre-fix 60s. If you want it gone rather than bounded, refresh the marker inside the chunk loop rather than raising the TTL.

Minor, worth a line in the runbook: a cc job queued by the button during a run is shifted, skipped and **lost** — the operator's press produces a log line and nothing else, and no retry is scheduled.

## Zero-write alarm (same owner as A): CLOSED

- Fresh install, 142 rows carrying `Series Event = 0`: control emits `warn: … zero writes are up … Series Event 0 -> 142` on run 1 — the exact noise-training the review named. Working tree emits `info: … There was no previous profile to compare against, so this run becomes the baseline: Series Event (142).` (`cc_zeroes_set`, `:162`/`:1615`).
- Report regression sending 0 for all 142 rows, five consecutive runs — warns per run: **control 1, 0, 0, 0, 0** (one-shot, exactly as reported); **working tree 1, 1, 1, 1, 1**, still warning on run 5 while it goes on overwriting (`:1638` no longer baselines a run that raised the alarm).
- After an explicit `--accept-growth`, the same profile goes quiet.

Contract for the other two surfaces holds: `cc_ok()` persists `cc_last_created` (control does not), a 142-update run records `0`, and `state()` carries **no** default for it, so `class-wcac-admin.php:539`'s "-1 = the run predates the key" stays honest on a pre-upgrade state row.

## Not verified here

`class-wcac-cli.php` and `class-wcac-admin.php` render these values but were not exercised — this lens drove `WCAC_Sync` only. The state keys they depend on (`cc_last_created`, `cc_fails`, `cc_block_why`, `cc_high_rows`) are all written as their owners documented.

---

## Lens: Regression

No files edited. Full method-body comparison plus an executed end-to-end harness against `740a504`.

## Verdict: the five existing syncs do not regress. One deliberate behaviour change, three narrow residuals, no defects.

**Evidence produced (all in `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/fivelens/`)**

1. Token-level body extraction of every function in all 10 PHP files at `740a504`, `HEAD` and worktree (`extract.php` / `cmp.php`). 52 baseline bodies present, 0 removed or renamed, 21 changed.
2. End-to-end harness (`run5.php`, WP stubbed at the `wp_remote_*` boundary so both versions traverse the same fake network) over 12 scenarios, comparing URLs, table IDs, merge fields, typecast, per-record field payloads, record counts, tallies, camp cache, log lines, scheduled hooks and throttle sleeps.
3. Lock-transient probe (`lock5.php`), admin page render diff (`render5.php`), `campus-connect --run` queue probe (`ccrun5.php`), `source_root` matrix (`root5.php`).

**Harness results — worktree byte-identical to baseline after removing the new `cc_*` state keys and normalising timestamps:**

`full`, `incremental`, `central_500`, `airtable_500`, `airtable_422`, `httproot`, `slashroot`, `double_enqueue`, `ccon_stranded` → **IDENTICAL**. `ccon`/`ccon_double` differ only by the added `cc` queue entry and its own log line; the wc/mu/kid network calls, `updated=76` tally and camp cache are identical.

---

## The two hand-applied one-liners

**`run_slice($only)` now having a caller — sound.** `includes/class-wcac-cli.php:1336` passes `'cc'`. Probed directly (`ccrun5.php`): `wp wcac campus-connect --run` on a queue of `[wc, mu, kid, kid]` runs exactly one job (`cc`), and the queue afterwards is **byte-identical and in the same order** to the pre-command queue. State keys touched: `last_run` and `finished` only. `created`/`updated`/`errors` untouched. I checked whether `last_sync` advancing at `class-wcac-sync.php:485` could skip an incremental window — it cannot: `last_sync` and `finished` are **write-only in both versions** (grep across all files finds no reader; the incremental cursor is `lookback_hours`, computed fresh at `:300`). No finding.

**The lock TTL restore — correct, with one residual.** `includes/class-wcac-sync.php:463-472`. Probed sequence for a `[wc, cc, kid, mu]` slice:

```
baseline:  set ttl=60 | wc | cc | kid | mu | del
worktree:  set ttl=60 | wc | set ttl=300 | cc | set ttl=60 | kid | mu | del
```

A **cc-free slice is transient-for-transient identical** to baseline (`lock5nocc.php`, both versions: one `set ttl=60`, one `del`). Both new `set_transient` calls are behind `$long_lock` at `:455`, so the web and loopback-cron paths are untouched entirely. Round 2's stated regression (wc/mu/kid inheriting 300s) is genuinely closed.

*Residual, low:* the restore stamps 60s **from the moment the cc job ends**, not from slice start. After a 140s cc job under WP-CLI, a `kill -9` during the following `kid` job strands `wcac_lock` until t≈200 instead of baseline's t=60, stalling the five syncs for up to (cc duration + 60)s. Only reachable under the `cli` SAPI where, as the docblock itself argues, nothing short of `kill -9` skips the `finally`. Not worth trading.

---

## Findings

**F1 — `source_root` userinfo is stripped from the WordCamps and Meetups fetches. Deliberate, correct, but it is a five-sync behaviour change.**
`includes/class-wcac-source.php:124` and `:147` changed from `WCAC_Settings::get( 'source_root' )` to `$this->central_root()` → `WCAC_Settings::source_root()` (`includes/class-wcac-settings.php:419`).
Trigger: a stored root of `https://bob:hunter2@central.wordcamp.org`. Measured:

| stored | baseline URL | worktree URL |
|---|---|---|
| `https://bob:hunter2@central.wordcamp.org` | `https://bob:hunter2@central.wordcamp.org/wp-json/...` | `https://central.wordcamp.org/wp-json/...` |
| `https://central.wordcamp.org/` | `https://central.wordcamp.org//wp-json/...` (double slash) | `https://central.wordcamp.org/wp-json/...` |

Consequence: an install pointing at a private Central mirror that authenticated via URL userinfo loses Basic auth on WordCamps and Meetups and starts logging `HTTP 401`. This is the credential-out-of-URLs fix and the trailing-slash row shows it also repairs a baseline bug, so I would keep it. It is guarded correctly: `maybe_upgrade()` (`class-wcac-settings.php:446`) rewrites the stored row **only** when `wp_parse_url` finds `user` or `pass`, verified across an 11-case matrix; a root with a query, fragment, port, sub-path or trailing slash is left alone, and a warn is logged on rewrite. `root_scheme_allowed` is not consulted on read, so an existing `http://` root keeps working.

**F2 — every five-sync `tally()` now writes a 3.5x larger `wcac_state` row.** `class-wcac-sync.php:134` adds 17 `cc_*` defaults; `set_state():176` does `array_merge( $this->state(), $patch )`, so `tally():188` rewrites all of them. Measured on the `full` scenario with Campus Connect **disabled** and every `cc_*` key at its default: same 18 writes, `171 → 604` bytes average (`3,094 → 10,888` bytes total). Scaled to a ~1,500-camp backfill that is roughly 2 MB of extra `UPDATE` traffic per backfill, growing with `cc_keys`/`cc_unmapped`/`cc_absent`/`cc_zeroes` content. `autoload=false` in both, so no per-pageload cost. Perf only, not correctness. Not worth fixing narrowly.

**F3 — the cc job is interposed between `mu` and `kid` in the incremental queue.** `class-wcac-sync.php:315`. Queue goes `[wc, mu, kid...]` → `[wc, mu, cc, kid...]`. Since the budget is only tested between jobs and the cc job is documented at 45s + ~15 PATCHes, an ordinary 20s tick that previously reached the `kid` jobs can now exit before them, deferring the sessions/sponsors refresh to the next tick. Bounded (the `WCAC_CONTINUE_HOOK` fires 30s later), only when Campus Connect is enabled, and inherent to putting a sixth sync in one queue. Note, not defect.

**F4 — "Sync Campus Connect now" jumps the five syncs' queue.** `class-wcac-sync.php:713` `array_unshift( $keep, array( 'to' => 'cc' ) )`, reached from `class-wcac-admin.php` `case 'cc_now'` and from `wp wcac campus-connect --run`. During a full backfill this puts a potentially 90s job ahead of ~1,500 camp jobs. Operator-initiated and one job deep. Note.

**F5 — `wp wcac test` now issues an extra request to wordcamp.org.** `class-wcac-cli.php:249`. Baseline touched Airtable only. Guarded by `central_ready()`, so it is silent on a site with no Central credential. Behaviour change to a shared command; intended.

**F6 — cosmetic diagnosability loss.** A stored root that `wp_parse_url` cannot resolve to scheme+host (`//central.wordcamp.org`, `central.wordcamp.org`) yields `''` from `source_root()`, so the failing request URL logged by `job_wordcamps` is `/wp-json/wp/v2/wordcamps` with no host. Baseline logged the host. Both fail; only the message is worse. `esc_url_raw()` on the save path makes such a row reachable only by direct DB write.

---

## Verified sound (stated plainly)

- **Byte-identical to `740a504`:** `job_wordcamps`, `job_meetups`, `job_event_data`, `sync_collection`, `queue`, `set_queue`, `push`, `pending`, `clear_queue`, `tally`, `camps`, `remember_camps`, `error_status`, `WCAC_Source::event_posts`, `WCAC_Source::term_map`, `WCAC_Source::url`, `WCAC_Airtable::request`, `WCAC_Settings::all`/`get`/`table_for`/`is_configured`, `WCAC_CLI::clear`, and the whole of `class-wcac-mapper.php`. `run_job` gains only a `case 'cc'` branch; the three existing branches are unchanged.
- **`run_slice` with `$only = ''` is behaviourally identical.** The peek/break at `:447-454` is skipped, `$only = (string) $only` at `:390` is inert, and both `$long_lock` branches require `'cc' === $job['to']`.
- **`without_queued_duplicates` never drops a five-sync job.** Proved: a double `enqueue_incremental` with Campus Connect on yields `[wc, mu, cc, kid, wc, mu, kid]` — all six wc/mu/kid jobs kept, exactly one `cc` dropped, final tally `updated=76` identical to baseline's.
- **A stranded `wcac_cc_running` marker costs the five syncs nothing.** Scenario `ccon_stranded` (transient pre-set, two enqueues, full drain) is byte-identical to the baseline trace.
- **`WCAC_Airtable::upsert`'s rewrapped `WP_Error` changes nothing observable.** Scenarios `airtable_500` and `airtable_422` produce identical log lines, tallies and camp cache in both versions; `$continue` defaults false and `$typecast` defaults true.
- **`WCAC_Logger::redact()` leaves five-sync context alone.** Their only context anywhere is `array( 'page' => $page )` (`class-wcac-sync.php:1080, :1106, :1154, :1180`), a scalar that passes through untouched; the 20-entry cap and key mask cannot bite.
- **`WCAC_Source::get()`'s `$overrides` default to the exact baseline args** (`timeout 25`, `redirection 5`, `array_merge(array('Accept'=>...), array())`). No five-sync caller passes overrides.
- **`WCAC_Airtable::throttle()` instance → static `$last_request` (`class-wcac-airtable.php:56`)** only tightens pacing when a request creates more than one client; worst case one extra 220 ms `usleep`. Safer, not riskier.
- **`WCAC_CLI::stamp()` is exactly equivalent** to the inline `gmdate(...) . ' UTC'` it replaced at the `last slice` row, for every integer input.
- **Admin page rendered and diffed with identical state:** Jobs pending, Current run, Rows created / updated, Last slice, Camps cached, the five table-ID fields, lookback, budget and source site are unchanged. The only edit to a five-sync row is an added `<span class="description">cumulative since the last full backfill</span>` on Errors (`class-wcac-admin.php:585`). `handle_action`'s five existing cases and `handle_save`'s five-sync path are byte-identical.
- `php -l` clean on all 10 files.

---

## One correction for the record

The sync owner's handover states "`run_slice()` is byte-identical." That is no longer true — the hand-applied restore at `includes/class-wcac-sync.php:463-472` changed it. The change is correct and I would keep it; the claim just needs updating so a later reviewer does not skip the function.

---

## Lens: Fresh eyes, second hostile read

**No. I would not let this run unattended, weekly, against those 142 rows.**

Not because the guards are absent — they are well-built — but because **every one of them is calibrated for a 5-minute cadence and silently evaporates at a weekly one**, and because the one flag the code tells operators to type disarms all of them at once. Six findings, all reproduced in a harness that runs the real `WCAC_Sync` + `WCAC_Mapper` against stubbed Source/Airtable: `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/wcac_cold_review_harness_v1.php` (+ `s1.php`, `s2.php`, `s3.php`, `s4.php`, `s6.php`, `s7.php`, `s10.php`). I edited no repo file.

## 1. Every latch expires in 12 hours, which is shorter than the cadence — so under a weekly schedule no block ever stops a run

`/Users/maciejpilarski/GitHub/wordcamp-airtable-connector/includes/class-wcac-sync.php:592`

```php
return $since > 0 && ( time() - $since ) < 12 * HOUR_IN_SECONDS;
```

`cc_block()` is the only stop the creates guard, the volume guard, the 5-failure counter and the partial-write counter have. All four expire 12 hours after being set. A weekly run meets the latch 168 hours later — always expired.

Trigger: Central changes what the report's `ID` field means (the case `cc_creates_ok()`'s own docblock at `:991` names), and it is not stable across runs. Observed (`s10.php`):

```
week 0 (healthy): table=142
week 1: table=284 blocked=YES
week 2: table=426   <- block expired 6.5 days ago
week 3: table=568
week 4: table=710   (142 curated + 568 permanent orphans)
cc_last_rows every week: 142   cc_high_rows: 142
```

Four weeks, 568 unrecoverable rows, and every number an operator reads — rows read, rows written, high-water mark — says 142. The block "outlasts the next tick" only if the next tick is inside 12 hours.

## 2. The creates guard has no cumulative term, so a sub-threshold drip is unbounded and unblockable by design

`class-wcac-sync.php:40` (`CC_MAX_CREATES = 10`) and `:1012` (`if ( $accepted || $created < self::CC_MAX_CREATES )` → `warn` and return)

Trigger: nine report IDs with no matching row — indistinguishable, to this code, from nine genuinely new camps. Someone clearing or fat-fingering a `WordCamp ID` cell in a hand-curated table produces exactly this. Observed (`s2.php`), 52 weekly runs:

```
after 52 weekly runs: table rows = 610
ever blocked? NO
state: high=142 fails=0 partial=0 block=no created=9 written=142 rows=142
```

468 permanent rows, zero blocks, `cc_high_rows` still 142, one `warn` line per week in a 200-entry ring nobody is reading — that is what "unattended" means. Nothing anywhere holds a running total of creates.

The gate that was supposed to catch this, `campus_preflight()` (`class-wcac-cli.php:891` → `set_campus_preflight( true, $table )`), **stamps once and never expires**. It certifies "142 present, unique merge values" at time T and is trusted forever; the sync never reads the table back (`:1246`).

## 3. `--accept-growth` waives three independent guards, and it is the only remedy the code prints

`class-wcac-sync.php:1666` — the zero-write warn tells operators: *"until a run is accepted with: wp wcac campus-connect --run --accept-growth"*. That same flag reaches `cc_creates_ok( $created, $accepted )` at `:1729`, whose first test is `$accepted ||`.

Trigger: an ordinary zero-write rise (one camp leaves a series; `Actual Attendees` reported as 0). Operator does what the log line says. Observed (`s4.php`):

```
WARN: Campus Connect zero writes are up on the last accepted run: Actual Attendees 0 -> 142 ...
table rows now: 284
state: high=142 ... created=142 written=142 rows=142 block=no
log> WARN: Campus Connect created 142 row(s) that the destination table did not already hold.
```

142 permanent rows, **no block**, because the flag typed to silence a benign counter also lifted the ceiling on creation. The docblock at `:728` argues this is deliberate ("one acceptance covers all three readings"). It is defensible for the volume/zero pair; extending it to the one irreversible direction is not, and the advertised use case is the most trivial of the three.

## 4. The very first run of any install has no volume ceiling, and a bad first run permanently poisons the ceiling for all later ones

`class-wcac-sync.php:947` (`if ( $high < 1 ) { return true; }`) and `:810` (`'cc_high_rows' => $high > 0 ? $high : (int) $read`)

`cc_volume_ok()` runs before the upsert; `cc_ok()` seeds the high-water mark after it. Trigger: the report is broken-wide on the first run after preflight. Observed (`s1.php`):

```
rows in destination after run: 1500 (was 142)
state: high=1500 ... created=1358 written=1500 rows=1500 block=YES
```

1,358 permanent rows written before anything looked, and the same failing run set `cc_high_rows = 1500` — a ceiling of `max(1550, 2250)` for a 142-row table, from then on.

`cc_volume_ok():938-946` makes this worse: the override sets `cc_high_rows = $incoming` and returns *before* any write, so it is consumed by a run that then fails outright. Observed (`s4.php`, S5): `--accept-growth`, report returns 1500, upsert 401s, nothing written — `cc_high_rows` is 1500 anyway. The volume guard is now dead and no log line says so.

## 5. Fix B closed `wp wcac sync` and left the command the runbook actually gives operators wide open

`class-wcac-sync.php:698` — `queue_campus_now()` still calls `cc_unblock()` unconditionally. It is reached by `wp wcac campus-connect --run` and the "Sync Campus Connect now" button. `readme.txt:160` step 9 gives operators exactly that command — the natural crontab line for a weekly job.

Trigger: revoked application password, 8 scheduled runs. Observed (`s7.php`):

```
wp wcac sync                  -> 1 report requests to Central in 8 runs
wp wcac campus-connect --run  -> 8 report requests to Central in 8 runs
```

On that path `cc_fails` still cannot exceed 1, `$fails >= 5` at `:916` is still unreachable, and every scheduled run still fires a full server-side report generation plus a rejected HTTP Basic auth at wordcamp.org. Worse, `cc_unblock()` fires **before** the run, so a creates block is cleared and the job re-runs with no acknowledgement (`s6.php`, S6b: `blocked=YES` → `queue_campus_now()` → `blocked=no, block_why=cleared`).

## 6. `CC_LOCK_TTL` is shorter than the code's own worst-case run, and the lock has no owner token

`class-wcac-sync.php:34` (TTL 900) vs `class-wcac-airtable.php:137` (`sleep(30)` per 429, up to 3 per chunk) with `continue_on_error` at `:1495`.

142 rows = 15 chunks × 90s of 429 sleeps = 1350s, plus a 45s report = **1395s against a 900s lock**. And `:1231`'s `delete_transient( self::CC_LOCK )` in the `finally` is unconditional — it deletes whatever marker is present, not its own. Observed (`s3.php`):

```
t=901   marker expired (TTL 900) -> B may start; B holds the marker
t=1380  A's finally deleted the marker while B is mid-upsert: B IS NOW UNPROTECTED
```

The exact duplicate-creation window the lock was added to close, reopened by the lock's own release path. Store `wp_generate_uuid4()` (or the pid) in the transient and only delete it when it still matches.

Related, same code: when the lock does hold, `job_campus_connect():1220` logs *"It will be picked up by a later sync"* — false. `run_slice():415` already shifted and persisted the job, and per `readme.txt:468` the cron never fills the queue. Observed (`s10.php`, S11): the job is gone, the report is never fetched, and `campus_run()` reports `"did not finish cleanly. Consecutive failures: 0. Last error: none recorded"` — naming neither the lock nor a cause.

## What I found sound

- **The non-destructive write contract holds.** PATCH-only (`class-wcac-airtable.php:189`), omit-on-empty (`class-wcac-mapper.php:833`), `campus_int()` returning null for `'n/a'`/`'~300'` and int 0 for a genuine zero, `campus_status()` default-deny against a hand-confirmed label list, `campus_url()` requiring the scheme. I tried to make the mapper clear or corrupt a curated cell and could not.
- **`cc_partial` genuinely latches** (`s7.php`, S8: five runs → block at run 5) — one chunk 422ing forever does escalate.
- **The zero-write alarm now repeats** (`s7.php`, S9: warn on every run, not once).
- **The ratchet is really stopped** — `cc_high_rows` stayed 142 across 52 accepted runs.
- **Envelope validation** in `class-wcac-source.php:183-213` (HTML page, JSON-serialised WP_Error at HTTP 200, missing `data` list) is the right shape, and routing empty-report through `cc_fail()` rather than "clean run, wrote nothing" is correct.
- **The credential never leaks** into a URL, a WP_Error message or a log line.

## What would change my answer

Findings 1, 2 and 3 are one problem in three places: **nothing in this plugin counts creates across runs, and nothing survives the gap between runs.** The narrowest fix I would want before it runs alone: persist a cumulative `cc_created_total` and refuse when it drifts from the preflight row count; make `cc_block` require an explicit operator clear rather than expiring on a wall clock shorter than the schedule; give creates their own acceptance flag; and drop `cc_unblock()` from `queue_campus_now()`'s pre-run path (clear the block *after* a successful run, not before an attempt).

---
