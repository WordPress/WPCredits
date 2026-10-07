# Latch expiry + operator command — fix and verification (2026-09-02)

RULING: the latch never expires on a clock; it is cleared only by an acknowledged operator action.

Proven: over 52 weeks HEAD reaches 7,526 rows / 7,384 permanent orphans; the fixed tree stays at
284 / 142. Revoked credential over 8 runs: 1 request on both command paths, was 8.

**The 'Stranding' lens found four inverse defects (A1 critical, A2 high, A3 high, A4 medium)
introduced by this very fix.** They are fixed in the following commit.

---

## Lens: Repro (both findings, executed vs control)

Both findings are closed by execution. Here is the verification report.

## Method

Built an execution harness that loads each revision **unmodified** except for one inserted `namespace WCACSIM;` line (the bootstrap asserts everything past line 1 is byte-identical to the source, else it aborts). That namespace makes unqualified `time()`, `sleep()` and `usleep()` resolve to harness versions, giving a real virtual clock without touching the code. Real `WCAC_Sync`, `WCAC_CLI`, `WCAC_Source`, `WCAC_Airtable` and `WCAC_Mapper`; stubbed WP, a fake Central and a fake Airtable base implementing genuine `performUpsert` merge-on-`WordCamp ID` semantics, seeded with 142 curated rows each carrying a hand-entered column. Requests are counted at the wire.

Harness: `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/L6/` (`boot.php`, `stubs.php`, `s1_weekly.php`, `s2_revoked.php`, `s3_recovery.php`, `s4_five.php`, `s5_coexist.php`, `s6_allfour.php`). No file in the repo was edited; `git status` and the three md5s are unchanged from when I started.

One correction I made mid-run and mention because it changes how the tables read: my first "blocked?" helper read `cc_block > 0` directly. That is variant-blind — it silently gives HEAD the working tree's answer. Every number below asks each variant's **own** `cc_blocked()` by reflection.

## Finding 1 — weekly cadence, unstable report IDs (`s1_weekly.php`)

Week 0 healthy; from week 1 Central's ID field means something else and changes again every week, so all 142 rows miss the merge key. Ticks 168h. Driven through the real cron path (`enqueue_incremental()` → `run_slice()`).

| week | HEAD (control) | working tree |
|---|---|---|
| w0 | 142 | 142 |
| w1 | 284 | 284 |
| w2 | **426** | 284 — run refused before any write |
| w3 | **568** | 284 |
| w4 | **710** | 284 |
| w5 | **852** | 284 |

The control reproduces the finding's numbers exactly, including `cc_last_rows`/`cc_high_rows` reading 142 every single week. Extended to 52 weeks: HEAD reaches **7,526 rows / 7,384 permanent orphans / 53 report requests**; the working tree stays at **284 / 142 orphans / 2 requests**. It does not reach 710 at any horizon.

`s6_allfour.php` checks the four stops named in the finding at +7d and +365d, each armed for real rather than hand-written into state:

| guard | HEAD still on at +7d | working tree at +7d / +365d |
|---|---|---|
| creates (`cc_creates_ok`, sync:1095) | **NO** | yes / yes |
| volume (`cc_volume_ok`, sync:1026) | **NO** | yes / yes |
| contract (no usable ID, sync:1571) | **NO** | yes / yes |
| five failures (`cc_fail`, sync:1008) | **NO** | yes / yes (0 further report requests) |
| partial writes (`cc_partial`, sync:915) | latches at 5 | latches at 5, stands |

Mechanism: HEAD had the same `12 * HOUR_IN_SECONDS` comparison hand-copied in three files — `class-wcac-sync.php:592`, `class-wcac-cli.php:1623`, `class-wcac-admin.php:515`. All three now defer to the single `WCAC_Sync::cc_block_active()` at `class-wcac-sync.php:593`. The only surviving `HOUR_IN_SECONDS` in `includes/` is the unrelated lookback window at `class-wcac-sync.php:301`.

## Finding 2 — revoked credential, 8 scheduled runs (`s2_revoked.php`)

Central returns HTTP 401 on the report route. Eight scheduled invocations of the real `WCAC_CLI` methods, STDIN closed as under cron.

| path | HEAD @1h | HEAD @24h | HEAD @168h | working tree, all three |
|---|---|---|---|---|
| `wp wcac sync` | 1 | **8** | **8** | **1** |
| `wp wcac campus-connect --run` | **8** | **8** | **8** | **1** |

The finding's stated control ("1 in 8" vs "8 in 8") reproduces exactly at a sub-12h gap. Two things the finding did not state that the run shows: at any *real* cadence HEAD leaks 8 on the `wp wcac sync` path too, because the latch expires between ticks; and on HEAD's `--run` path `cc_fails` is pinned at **1** across all eight runs, so `$fails >= 5` is provably unreachable there. `s3_recovery.php` section (a) confirms the counter is now live on that path: five consecutive 404s give cc_fails 1→2→3→4→5, latch on run 5, and **run 6 makes no request** (5 requests total, not 6). HEAD makes 6 with cc_fails stuck at 1.

Mechanism: HEAD's `queue_campus_now()` called `cc_unblock()` unconditionally at `class-wcac-sync.php:698`, before the run. The working tree gates at `class-wcac-cli.php:1336-1353` before the `$accept_growth` branch, and `queue_campus_now( $ack )` at `class-wcac-sync.php:764` only unblocks on an explicit acknowledgement.

## Recovery routes and the human override — 39/39 pass (control: 23/39)

Every route executed against a real armed block. `s3_recovery.php`:

- **Refuses (unattended):** `wp wcac test` / saved-credential path (`clear_campus_block()`, sync:719) returns `false` against a data block and the block stands; crontab `--run` exits 1, writes nothing, prints `Reason: …` first; `--run --clear-block` under cron halts non-zero and the block survives; **`--run --clear-block --yes` is not an escape hatch** — `WP_CLI::confirm()` at `class-wcac-cli.php:1352` is called with one argument, so `--yes` never reaches it; admin "Sync Campus Connect now" (`queue_campus_now()` with no ack, gated at `class-wcac-admin.php:202`) returns `false`, queues nothing, keeps the block.
- **A human can still force a run:** answering `y` at a terminal → exit 0, block discarded, a fresh successful run recorded, all 142 rows written **as updates with 0 created**, and `Campus Connect block discarded by hand` in the log. Answering `n` halts and keeps the block. Admin `queue_campus_now( true )` (the `cc_clear` case at `class-wcac-admin.php:213`, whose button at `:605` renders **only** inside the `if ( $cc_block )` notice, after the reason) clears and puts `cc` at the head of the queue.
- **Access blocks still answer to credential evidence:** a 401 latches immediately with `cc_block_kind = access`; after the credential is fixed, `clear_campus_block()` returns `true` and the job runs unattended again.
- **`wp wcac campus-connect --run --clear-block --accept-growth` works as one command** (readme.txt:345's open question): exit 0, the accepted run is not re-blocked by the creates guard, and `cc_growth_once` is consumed rather than left armed.
- The 142 curated rows keep their hand-entered column through every one of these.

## Collateral checks

- **The five existing syncs do not regress.** `s4_five.php` runs `wp wcac sync --full` then `wp wcac sync` with Campus Connect off, against canned upstream payloads (52 requests, 10 Airtable rows), and dumps every request URL, every Airtable row, all CLI output and all log lines. **HEAD vs working tree: byte-identical.** The only difference anywhere in the dump is `wcac_state` gaining one bounded string key, `cc_block_kind`.
- **While Campus Connect is latched the other syncs keep running** (`s5_coexist.php`): exactly one `Campus Connect job skipped: the sync is blocked` line per run, and the WordCamps and Meetups tables are written on every run — confirming readme.txt:169-193's claim.
- **No credential value appears** in any log line, `cc_block_why` or `cc_last_error` on any 401 path.
- `php -l` clean on all ten PHP files.

## Assessment

Both findings are closed. The distinction the task named is preserved mechanically rather than by convention: the attended/unattended test is `WP_CLI::confirm()` reading a terminal that cron does not have, and on the admin side it is button placement — the only control that can discard a latch is rendered after the reason, inside the red notice.

One limitation worth stating plainly: I could not render the admin screen end to end without WordPress, so the admin half is proven at the `queue_campus_now()` / `cc_block_active()` boundary (executed) plus reading of the two `handle_action()` branches and the button's `if ( $cc_block )` guard (static). Everything else above is executed.

---

## Lens: Regression

## Verdict: no regression. The five syncs are sound.

I could not produce a finding against WordCamps, Meetups, Sessions, Speakers or Sponsors. Evidence below.

### Method-body comparison (comments stripped, whitespace normalised)

`/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/extract.php` tokenises both versions and compares each method.

**`class-wcac-sync.php` vs 740a504** — 20 shared methods, 15 byte-identical, including every method that carries the five syncs' work: `job_wordcamps`, `job_meetups`, `job_event_data`, `sync_collection`, `queue`, `set_queue`, `push`, `pending`, `clear_queue`, `tally`, `camps`, `remember_camps`, `set_state`, `error_status`, `__construct`.

The 5 that differ are additive only:
- `state()` (:134) — 8 default keys → 27; the 19 `cc_*` keys, one of which (`cc_block_kind`, :150) is this round's.
- `enqueue_full()` (:239) — appends a `cc` job; this round's hunk is **comment-only**.
- `enqueue_incremental()` (:296) — appends a `cc` job and calls `without_queued_duplicates()`; untouched this round.
- `run_slice()` (:380) — the cc lock extension, gated on `'cc' === $job['to']`; untouched this round.
- `run_job()` (:1129) — one added `case 'cc'`.

`without_queued_duplicates()` (:529) is the only new filter on the queue path and it `continue`s on `'cc' === $job['to']` alone — wc/mu/kid pass through unfiltered, in order.

**This round only (HEAD → worktree):** `class-wcac-cli.php` 35 shared methods, 5 differ (`test`, `campus_connect`, `campus_run`, `block_text`, `campus_blocked`); `class-wcac-admin.php` 15 shared, 3 differ (`handle_action`, `notice`, `render`). 87 added non-comment code lines, all inside cc surfaces.

### End-to-end harness, 740a504 vs working tree

`fivelens/run6.php` drives `enqueue_* → run_slice` against stubbed HTTP, tracing every request (URL, method, timeout, redirection, auth-header length), every Airtable body (record count, `fieldsToMergeOn`, `typecast`, first field set), the queue, the camps cache, tallies, option write counts, scheduled events and the full log ring. 12 scenarios × 2 versions, zero stderr on all 24 runs — worth noting on its own, since a missing `cc_block_kind` default would have raised a notice.

Normalising away the dormant `cc_*` state keys and throttle jitter, **8 of 9 five-sync scenarios are byte-identical**: `full`, `incremental`, `double_enqueue`, `central_500`, `airtable_500`, `airtable_422`, `httproot`, `slashroot`. Option write counts match exactly in both (`wcac_queue` 14, `wcac_state` 21, `wcac_log` 1, `wcac_camps` 2) — no added DB churn.

### Under a standing block — the case this round created

`fivelens/blocked.php`, block ages 1h / 7d / 200d. Across the five syncs' entire observable surface (46 requests, camps, scheduled events, tallies, every non-cc log line) the **only** difference at any age is one log line: `Incremental sync queued (3 jobs…)` → `(4 jobs…)`, the cc job being counted. Pre-existing since df355b6.

The critical check on `queue_campus_now()`'s new early return (`class-wcac-sync.php:765-771`): called blocked-and-unacknowledged with wc/mu/kid pending, it returned `false`, made **0 writes to `wcac_queue`**, and left the queue exactly `["wc","mu","cc","kid"]`. It returns before `$keep` is built, so it cannot clobber the five syncs' pending jobs.

`fivelens/weeks.php`, four weekly cycles:

```
BASELINE 740a504          week1 airtable=19 upd=38 err=0 | w2-4 airtable=28 upd=56 err=0
WORKING TREE              week1 airtable=19 upd=38 err=0 | w2-4 airtable=28 upd=56 err=0
WORKING TREE, cc blocked  week1 airtable=19 upd=38 err=0 | w2-4 airtable=28 upd=56 err=0
```

Week-for-week identical. A permanently blocked cc job costs a slice 4 transient ops (`wcac_lock` re-set/restore, `wcac_cc_running` set/del), 0 Central report calls and 0 extra Airtable calls — it no-ops at `campus_connect_run()`'s first line and never reaches the five syncs' budget.

### Admin screen

`scratchpad/adminrender.php` renders all 9 state cases on HEAD and on the working tree. Both clean, no notices — including case 5, a pre-upgrade state row with no `cc_block_kind`, which survives because `state_value()` (`class-wcac-admin.php:1124`) and `cc()` (`class-wcac-cli.php:1542`) both default. Every one of the 47 diff lines sits under `<th>Campus Connect</th>`; the five syncs' rows are byte-identical. The new `cc_clear` form (`class-wcac-admin.php:602`) opens and closes at :602-606, before the first other form at :850 — no nested `<form>`, so it cannot hijack the settings form.

### Three differences from 740a504 that are not regressions, for the record

1. **`class-wcac-source.php:340-345`** — `source_root()` strips `user:pass@`. With `source_root` set to `https://bob:hunter2@central.wordcamp.org`, baseline put the credential in all four wc/mu request URLs; the working tree does not. This is the one place the five syncs' URLs differ from baseline. It is a pre-existing security fix, the file is **untouched this round** (`git diff --stat HEAD -- includes/class-wcac-source.php` is empty), and the requests still return the same records.
2. **`class-wcac-sync.php:296`** — the `Incremental sync queued (%d jobs…)` count now includes the cc job. Cosmetic, pre-existing.
3. **`class-wcac-sync.php:134`** — `wcac_state` grew from 8 to 27 default keys, ~460 bytes more per write; this round adds ~30 of that (`cc_block_kind`). `set_state()` (:178) passes `false` for autoload, so it stays out of the autoload cache, and the write *count* is unchanged.

### Caveat on rule 2

`php -l` is clean on all three changed files, but only PHP 8.5 is installed here — no 7.4 binary, so 7.4 was checked by inspection, not execution. The 87 added code lines use only default parameter values, a typed `array` parameter, and boolean/string comparisons; no `match`, `?->`, named arguments, attributes, enums, `readonly` or arrow functions.

---

## Lens: Stranding / inverse risk

## Verdict on the two findings as posed

**Finding 1 (latch expiry) is closed.** `12 * HOUR_IN_SECONDS` appears in none of the three PHP files. `WCAC_Sync::cc_block_active()` (`includes/class-wcac-sync.php:593`) is the single rule and both former hand-copies delegate to it (`class-wcac-cli.php:1676`, `class-wcac-admin.php:556`). Executed at ages 1s / 13h / 7d / 400d: blocked=YES in all four.

**Finding 2 (crontab bypass) is closed for the two paths it names**, and only for those two. `wp wcac campus-connect --run` exits non-zero at `class-wcac-cli.php:1342` before `queue_campus_now()` is reached; the admin "Sync Campus Connect now" button refuses at `class-wcac-admin.php:202`. `queue_campus_now( true )` has exactly two callers, both gated (admin:228 behind `current_user_can` + `check_admin_referer` on a POST; cli:1360 behind `WP_CLI::confirm()`). No cron, REST or hook reaches it.

But a **third** unattended command was never in scope, and it now carries the entire hole.

---

## A1 (critical, inverse). `wp wcac test` on a nightly cron resets `cc_fails` and makes `$fails >= 5` unreachable again

`clear_campus_block()` (`class-wcac-sync.php:719-729`) falls through to `cc_unblock()` whenever no block is *active*, and `cc_unblock()` zeroes `cc_fails` (`:690`). `wp wcac test` calls it (`class-wcac-cli.php:265`), gets `true` back, and prints no warning.

Executed (Central 504s once a week, Central is up by the time the nightly test runs):

```
fail 1 -> cc_fails=1   `wp wcac test` returns true, no warning, cc_fails now 0
fail 2 -> cc_fails=1   `wp wcac test` returns true, no warning, cc_fails now 0
fail 3 -> cc_fails=1   ...
```

`cc_fails` never exceeds 1. This is the same shape as the original Finding 2 (`wp wcac sync` -> 1 request in 8 runs; `campus-connect --run` -> 8 in 8), relocated to `wp wcac test`. It was already true at `665f89d`, but it was survivable there because the 12-hour expiry was a second wait-and-see mechanism. `class-wcac-sync.php:920-928` now states outright that the five-count "is the whole of the wait-and-see mechanism now that the latch has no expiry", and `:707` names `wp wcac test` as "a plausible nightly crontab line". Both are true, and together they are the defect.

Narrowest fix: return early from `clear_campus_block()` when `! self::cc_block_active( $state )`. Nothing that is not a block should be cleared by a command that proves only that the credential works. That preserves both acknowledged surfaces and the R3 refusal exactly.

## A2 (high, inverse). The five-in-a-row block is hard-coded `'access'` whatever failed, so a credential test clears data-shaped blocks

`class-wcac-sync.php:1008` passes `'access'` unconditionally. `cc_fail()` is the funnel for far more than credential faults. Executed, five consecutive failures of each cause:

| underlying cause | kind written | `wp wcac test` clears it |
|---|---|---|
| Airtable upsert `WP_Error` (`sync:1616`), 30 rows already created per run | `'access'` | **YES** |
| every chunk failed (`sync:1653`) | `'access'` | **YES** |
| `wcac_central_contract`, report lost the ID key (`sync:1431`) | `'access'` | **YES** |
| `wcac_central_empty`, 0 rows (`sync:1423`) | `'access'` | **YES** |
| Central HTTP 500 | `'access'` | YES |

`readme.txt:328-332` asserts the opposite: "A volume, creates, partial-write or contract block survives it ... a nightly run of it cannot quietly stand one of them down." False for the contract, empty-report and Airtable-write cases.

Combined with A1 this is row-creating, not cosmetic. `cc_fail( $sent )` at `sync:1616` returns before `cc_creates_ok()`, which is only reached at `sync:1837` after `cc_ok()`; so rows a partially-committed upsert created are tallied but never measured against `CC_MAX_CREATES` (pre-existing, and it is what turns A2 into permanent rows). Executed, weekly `wp wcac sync` plus nightly `wp wcac test`, one drifted singleSelect option:

```
week  1 ran, created 30 (fails=1) | test cleared the block | table 172
week 10 ran, created 30 (fails=1) | test cleared the block | table 442
week 20 ran, created 30 (fails=1) | test cleared the block | table 742

after 20 weeks: table=742, orphans=600, block standing=no, cc_fails=0
cc_block_why the operator ever sees: ''
```

Narrowest fix at `:1008`: `'' !== $why ? 'access' : 'data'`. `$why` is non-empty only for the classified access/404/3xx set, so it names exactly the credential-shaped causes and nothing else. Note this alone does not close A1 (unclassified 5xx would still be reset nightly by `cc_fails` zeroing); A1 and A2 need both fixes.

## A3 (high, operator not told what to do). Every on-screen string for a volume or creates block names a command that now hard-errors

`class-wcac-sync.php:1062` and `:1116` (and `:1774`) store, verbatim into `cc_block_why`:

> `... accept it with: wp wcac campus-connect --run --accept-growth`

That string is what the red banner (`admin:598`) and `wp wcac status` print. With the block up, that exact command is rejected at `cli:1342` with "run: `wp wcac campus-connect --run --clear-block`". Following *that* instruction, executed:

```
reason on screen: The Campus Connect report jumped from 142 rows to 5000 ...
--run --clear-block:      blocked=no, cc_high_rows=142, cc_growth_once=0
next run's volume check:  proceed=false, blocked=YES   <- same block, same reason
--run --clear-block --accept-growth: proceed=true, blocked=no
```

Only the three-flag form escapes, and it appears nowhere on either surface, only at `readme.txt:345`. So the operator is told twice, by the plugin, to run commands that cannot work.

Worse on the admin side: `grep -c 'accept.growth' includes/class-wcac-admin.php` is 0. There is no accept-growth control on the screen, and the banner's only instruction is the button, which clears and immediately re-queues into the same refusal. **A volume or creates block is not recoverable from the admin screen at all.** `readme.txt:346` says "There is deliberately no button for `--accept-growth`", but nothing on screen says it, and the kind-conditional advice at `admin:791` sends the operator to the button.

Narrowest fix: append `--clear-block` to the two stored reason strings (`sync:1062`, `sync:1116`) so they read `wp wcac campus-connect --run --clear-block --accept-growth`, and add one clause to `admin:791`'s data-block sentence saying a volume or creates block additionally needs `--accept-growth` and has no button.

## A4 (medium). `cc_partial` is reset by nothing an operator can reach

`cc_unblock()` zeroes `cc_fails` but not `cc_partial`; the only reset is `sync:891`, a run with zero failed chunks. Executed:

```
5 partial runs           -> cc_partial=5, blocked=YES, kind=data
acknowledged clear       -> blocked=no, cc_fails=0, cc_partial=5
one more partial run     -> cc_partial=6, blocked=YES
```

One permanently-rejected cell therefore costs an acknowledged clear after **every single run**, forever. Recoverable (no DB surgery), but the status row (`admin:775`) prints "Consecutive runs leaving rows unwritten: 6" and the advice beside it says only "A run that writes every row clears both counters" without saying that is the *only* thing that clears this one. Whether `cc_unblock()` should also zero `cc_partial` is a genuine judgement call: resetting it restores a five-run grace period per acknowledgement, which is arguably what acknowledging means; leaving it is defensible. The missing sentence is not.

## A5 (low, UI dead end). The clear button is silently inert when the sync is switched off

The banner renders on `$cc_block` alone (`admin:595`), but `case 'cc_clear'` returns `$notice = ''` when `campus_connect_ready()` is false (`admin:222-226`), so the redirect carries no notice of any kind. State: a data block standing, then the operator switches the Campus Connect toggle off or removes the Central credential. Pressing "Clear the block and sync Campus Connect now" reloads an identical page, block still up, nothing on screen. Only the log says why. Not a true stranding (re-enabling the sync restores the button, and `--run --clear-block` needs only `is_configured()` at `cli:562`), but it is the one button on the screen that does nothing and says nothing. `case 'cc_now'` has the same shape at `admin:190-193`; the `cc_clear` branch was copied from it. One notice key, or gate the button's render on readiness.

## A6 (low, monitoring). The cron refusal exits 0, not non-zero

`WP_CLI::confirm()` (wp-cli 2.12.0, `php/class-wp-cli.php:983-993`) ends in a bare `exit;`, which is status **0**. So `wp wcac campus-connect --run --clear-block` in a crontab behaves correctly (it does not run; `fgets(STDIN)` on /dev/null returns false, `'y' !== ''`), but it exits *successfully*. `readme.txt:186-193` does not claim non-zero there, so the readme is accurate, but any monitoring wired to that line's exit status sees green while the sync never runs. Also: if STDIN is an open pipe with no writer rather than /dev/null, `fgets` blocks and the command hangs. It holds no lock at that point (`cli:1327` only reads the transient), so nothing else is harmed. One sentence in the unattended section would cover both.

---

## Sound, and worth saying plainly

- **No stranding requiring database surgery exists in any state.** Full matrix, executed: `data` and legacy-`''` blocks are refused by `clear_campus_block()` and cleared by both acknowledged surfaces; `access` blocks are cleared by all five routes. A block of any kind at any age is clearable by `wp wcac campus-connect --run --clear-block`, which requires only Airtable configuration, never `campus_connect_ready()`. A3 and A5 are operators being misdirected, not operators without a route.
- The four write guards are correctly classified and correctly refused: partial (`sync:915`), volume (`:1060`), creates (`:1113`), contract (`:1571`) all take the fail-safe `'data'` default, and `clear_campus_block()` returns false for every one.
- A row written before `cc_block_kind` existed reads as data, is refused by the credential routes, and is cleared by both acknowledged surfaces. Verified.
- `--clear-block` cannot be smuggled through another mode (`cli:336-338`), and the mode guard sits after the `count( $modes )` check so `$modes[0]` is never read off an empty array.
- The kind never leaks between blocks: `cc_unblock()` and `cc_ok()` both zero `cc_block_kind`.

**Ranking for the parent: A1 and A2 are the two that matter, and they are one fix apart.** Both are three-line changes inside `cc_*` surfaces (`clear_campus_block()` early return; the `$kind` expression at `:1008`), neither touches the five existing syncs, and together they close the unattended-override hole that Finding 2 was about. A3 is the next most likely to be hit in practice, since the volume guard is the guard an operator actually meets.

---
