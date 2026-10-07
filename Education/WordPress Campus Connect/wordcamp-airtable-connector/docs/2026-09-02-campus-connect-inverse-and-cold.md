# Inverse fixes A1-A4 + cold read (2026-09-02)

A1-A4 all CLOSED, each proven against commit 20b2121 as a control that still reproduces them.

The cold read then found three further unattended-clear routes and one stranding loop. Those,
plus one residual introduced by A4's own fix, are addressed in the following commit.

CORRECTION: an earlier audit reported a 20-week scenario reaching table=742 / orphans=600 /
block=no. This verifier could not reproduce those numbers from that input — both variants end at
142/0/blocked. The A2 consequence is real but its demonstration is the six-week scenario in a2b.php
(control 284 rows / 142 orphans; fixed tree 142 / 0).

---

## Lens: Inverse defects A1-A4 (executed vs control)

All four verified by execution, with commit 20b2121 as a control that still reproduces each. Harness (isolated, verified byte-identical to the two git states — only `includes/class-wcac-sync.php` differs between variants): `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/lensA-latchproof-25115/` (`a1.php`, `a2.php`, `a2b.php`, `a3.php`, `a3b.php`, `a4.php`, `a4b.php`; real `WCAC_Sync`/`WCAC_CLI`/`WCAC_Mapper`/`WCAC_Airtable`, stubbed WP, fake base with genuine PATCH-upsert merge semantics, virtual clock).

Note: my first run directory (`scratchpad/L7`) was concurrently overwritten by another agent mid-session — files I wrote vanished and files I did not write appeared. I rebuilt everything in the uniquely-named directory above and re-ran the whole suite there from scratch. All numbers below come from that clean run. (Also: `scratchpad/L6/HEAD` is **665f89d**, not 20b2121, and `L6/WORK` is 20b2121 — do not reuse those labels.)

## A1 — CLOSED (`class-wcac-sync.php:720-733`, early return at :723)

Weekly sync against a report route returning HTTP 500 (rides the counter; the credential itself is valid, so `test`'s identity probe authenticates and reaches `clear_campus_block()`), seven nightly `wp wcac test` runs between each.

| | week 1 | 2 | 3 | 4 | 5 | 6 | 7 | final |
|---|---|---|---|---|---|---|---|---|
| CTRL cc_fails after sync | 1 | 1 | 1 | 1 | 1 | 1 | 1 | **0, never blocked** |
| CTRL after the 7 nightly tests | 0 | 0 | 0 | 0 | 0 | 0 | 0 | |
| WORK cc_fails after sync | 1 | 2 | 3 | 4 | **5 → blocked** | 5 | 5 | **5, blocked (kind `data`)** |
| WORK after the 7 nightly tests | 1 | 2 | 3 | 4 | 5 | 5 | 5 | |

Control reproduces exactly: every nightly test zeroed the counter, so five-in-a-row was unreachable for ever. Working tree reaches 5 on week 5 and `wp wcac test` then prints `Warning: A Campus Connect block is still in force; it was not set by the credential and this command does not clear it.`

No hole introduced by the early `return true`: the only other caller is the credential-changed hook (`wordcamp-airtable-connector.php:90`, return ignored), and `cc_fail()` latches every credential-shaped cause immediately (`:1013-1017`), so `cc_fails > 0` with no block can only mean a 404 or a 3xx — neither of which a credential change answers.

## A2 — CLOSED (`class-wcac-sync.php:1028`, kind = `'' !== $why ? 'access' : 'data'`)

Five consecutive failures of each cause, then one `wp wcac test`:

| cause (×5) | CTRL kind | CTRL after `test` | WORK kind | WORK after `test` |
|---|---|---|---|---|
| Central HTTP 500 | `access` | **stood down** | `data` | survives |
| empty report (0 rows) | `access` | **stood down** | `data` | survives |
| lost ID key (no `ID` column) | `access` | **stood down** | `data` | survives |
| every row dropped (unusable ID) | `data` | survives | `data` | survives |
| Airtable upsert 500, all chunks | `access` | **stood down** | `data` | survives |
| all chunks 422 | `access` | **stood down** | `data` | survives |
| Central HTTP 401 | `access` | stood down | `access` | stood down |
| Central HTTP 403 | `access` | stood down | `access` | stood down |

The split is real, not a blanket relabel: genuine 401/403 still record `access` and are still cleared by a successful credential test in the working tree.

**The 20-week scenario does not reproduce the numbers in the brief.** With a weekly sync, a nightly test and one drifted singleSelect option, *both* variants end at table=142, orphans=0, block standing=YES, cc_fails=0, cc_partial=5, and the reason is the full partial-write sentence, not an empty string. Reason: a drifted option fails one chunk, the run still writes 132 rows, so it lands in `cc_ok()` (`:928`) as a **partial** block, which `cc_block()` records as `data` in 20b2121 too — `wp wcac test` refuses it in both. The `table=742 / orphans=600 / block=no / reason=""` figure cannot be produced by that input; if it came from an earlier round it came from a different scenario.

A scenario that does show A2's consequence in rows (`a2b.php`) — six weeks of report 500s with a nightly test, then the report returns with a drifted ID field:

- **CTRL: 284 rows, 142 permanent orphans.** cc_fails pinned at 1 for six weeks (A1), no latch, so week 7's drifted run executed and created 142 rows in a table with no delete verb.
- **WORK: 142 rows, 0 orphans.** Latched on week 5; week 7 never ran.

## A3 — CLOSED (`class-wcac-sync.php:811, 1082, 1136, 1794` — all four strings)

The command is parsed out of the stored reason at runtime and dispatched through the real `WCAC_CLI`; nothing is typed by hand.

Volume block, banner text (`class-wcac-admin.php:599` prints `cc_block_why` verbatim; `wp wcac status` wraps the same string at `class-wcac-cli.php:1654`):

- **CTRL** says `wp wcac campus-connect --run --accept-growth` → running it: **exit 1**, `Error: Campus Connect is blocked and this command no longer clears it.` Rows 142 → 142, still blocked.
- **WORK** says `wp wcac campus-connect --run --clear-block --accept-growth` → **exit 0**, confirm prompt answered, `Success: 1500 of 1500 row(s) written`, block cleared.

Creates block, same shape: CTRL exit 1 / still blocked; WORK exit 0, `Success: 142 of 142 row(s) written: 0 created, 142 updated`, block cleared.

The fourth string (`:1794`) is logged by a run that *succeeded*, so it names `--clear-block` with no latch standing. Executed with nothing on stdin: exit 0, no confirm prompt, 142 rows written — harmless.

## A4 — CLOSED (`class-wcac-sync.php:690`, `'cc_partial' => 0` in `cc_unblock()`)

Five partial runs (one permanently-rejected cell 422s the first chunk every run), then the acknowledged clear the banner names, then one more partial run:

| step | CTRL cc_partial / blocked | WORK cc_partial / blocked |
|---|---|---|
| partial runs 1-4 | 1,2,3,4 / no | 1,2,3,4 / no |
| partial run 5 | 5 / **YES** | 5 / **YES** |
| `wp wcac campus-connect --run --clear-block` | **6 / YES (exit 1)** | **1 / no (exit 0)** |
| next weekly partial run | 6 / YES | 2 / no |
| further runs | — | 3, 4, 5 → blocks again at 5 |

Control reproduces exactly: the acknowledged clear left `cc_partial` at 5, so the acknowledging run itself pushed it to 6 and re-blocked before the operator's terminal returned — an acknowledged clear was owed after every single run, for ever. Working tree restores the full five-run grace.

---

## One residual, introduced by A4's fix (low)

`cc_unblock()` (`class-wcac-sync.php:677-693`) is reached from **two** places: `queue_campus_now()`'s acknowledgement branch (`:787`) and `clear_campus_block()` (`:738`). Putting `cc_partial` reset inside `cc_unblock()` therefore hands it to `wp wcac test` as well, for any **access**-kind block. Executed (`a4b.php`): four partial runs (cc_partial=4), Central revokes the credential, a 401 latches `access`, the operator restores the credential, the nightly `wp wcac test` runs.

- CTRL: cc_partial stays 4 → the next partial run latches the starvation block.
- WORK: cc_partial **4 → 0** → four more partial runs, i.e. four more weeks, before the same ten rows' starvation is escalated.

This is the exact anti-pattern A2 removed — a counter discarded on evidence that says nothing about it — reintroduced on `cc_partial`. Consequence is bounded (starvation means rows *not* written; no unrecoverable creates), and it needs a genuine access block first, hence low rather than high. The narrow fix is to move `'cc_partial' => 0` out of `cc_unblock()` and into `queue_campus_now()`'s `$ack` branch only, which is the acknowledged surface A4's reasoning actually names.

Minor, pre-existing in both variants (not a regression): `class-wcac-cli.php:1442` gates success on `cc_last_ok > $before['cc_last_ok']` with a strict `>` on a second-resolution timestamp, so a run completing in the same wall-clock second as the previous `cc_ok` reports `Error: The Campus Connect job did not finish cleanly. ... Last error: ` (empty) and exits 1 after writing every row successfully. I hit it in the harness before advancing the virtual clock; identical CLI file in 20b2121, so it is not one of A1-A4.

---

## Lens: Regression

## Verdict

**The four working-tree fixes do not reach the five existing syncs.** This is not an inference from names — it is measured three ways, and all three agree.

## What was run

Workspace `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/L5B/` (three trees: `base/`=740a504, `head/`=20b2121, `work/`=working tree; harness `run5.php`, `interleave.php`, `tracer.php`, `guard.php`, `savecmp.php`, `roots.php`).

1. **Token-level method-body diff** (`extract.php` + `cmpm.py`, comments/whitespace stripped). 20b2121 → working tree: **152 shared method bodies byte-identical**, 0 removed, 0 added, exactly 6 changed — `cc_unblock`, `clear_campus_block`, `cc_fail`, `cc_volume_ok`, `cc_creates_ok`, `campus_connect_run`. None of the five syncs' methods is among them.
2. **End-to-end stub harness**, 9 scenarios (`full`, `incremental`, `central_500`, `airtable_500`, `airtable_422`, `httproot`, `userinfo`, `slashroot`, `double_enqueue`), comparing URLs, table IDs, merge fields, record counts, tallies, camps map, scheduled hooks and log output. 20b2121 → working tree: **9/9 byte-identical** after scrubbing clock jitter. The harness is non-vacuous: it drives all five tables (`tblLBVsO4rU2oj2Ki` wordcamps / `tblbbyWvUsxF9CHRj` meetups / `tblO5yMO8waGwqO1l` sessions / `tbliYsOf6pAZLOzfd` speakers / `tblPkKmjbgGpazI6K` sponsors) with merge fields `WordCamp ID`/`Meetup ID`/`Session Key`/`Speaker Key`/`Sponsor Key`, updated=32 (full) and 38 (incremental).
3. **Runtime tracer** (`tracer.php`): a `WCAC_Sync` subclass overriding `cc_unblock`/`cc_block`/`cc_fail`/`cc_ok`/`campus_connect_run`, running each job kind with `cc_fails=4, cc_partial=3, cc_high_rows=142` seeded. Result for `wc`, `mu` and `kid` (all three child collections): **`cc methods: none`, `cc state: UNCHANGED`**, and none writes `tbld8niqsLWyNbcVF`.

## The two changes you flagged

**`cc_unblock()` now zeroing `cc_partial`** (`includes/class-wcac-sync.php:690`) — does not reach the five syncs. `set_state()` (`:177`) is `update_option(OPT_STATE, array_merge($this->state(), $patch))`, a merge, so the added key touches `cc_partial` only and cannot disturb `created`/`updated`/`errors`/`mode`. Confirmed live: interleaved scenario `blocked_access` shows `cc_partial 3→0` at work vs `3` at 20b2121, with the five-sync tallies, network trace and camps map identical to baseline.

**The new early return in `clear_campus_block()`** (`:722`) — also does not reach them. Its only callers are `wp wcac test` (`class-wcac-cli.php:265`), the admin Central test (`class-wcac-admin.php:274`) and the `wcac_central_credential_changed` action (`wordcamp-airtable-connector.php:90`). The fix works as described: interleaved scenario `nightly_test` (`cc_fails=4`, nothing blocked) preserves `cc_fails=4` at work where 20b2121 zeroed it to `0` — with the five-sync surface identical in both.

## The `cc_unblock()` guard — confirmed, it cannot skip the reset

The guard at `class-wcac-sync.php:680` tests `cc_block`, `cc_block_why`, `cc_block_kind`, `cc_fails` — **not `cc_partial`**. Called directly on a `cc_partial`-only state it *does* skip the reset (`guard.php`: `cc_partial 4 -> 4  <-- SKIPPED`).

It is nevertheless unreachable in that state. Both call sites require `cc_block > 0`, which alone falsifies the guard:
- `clear_campus_block()` reaches `cc_unblock()` only past `if (!self::cc_block_active($state)) return true;`
- `queue_campus_now($ack)` reaches it only inside `if ($this->cc_blocked())`

An exhaustive 12-cell matrix over `{block set, none} × {access, data, none} × {ack yes, no}` confirms it: every row that enters `cc_unblock()` ends `cc_partial=0`; every row that leaves it at `4` never called it. **No acknowledged operator action loses the `cc_partial` reset.**

Two residuals, neither a defect. `--clear-block` with nothing blocked leaves `cc_partial` untouched — harmless, because below 5 nothing is latched and the next clean run zeroes it at `cc_ok()` (`:904`). And the guard is now *incomplete* relative to the five keys it writes; that is masked today by both callers, but it is the kind of thing a third caller would trip over silently. Worth a comment, not a change.

## One genuine finding in scope (base → working tree)

**`root_scheme_allowed()` compares the URL scheme case-sensitively** — `includes/class-wcac-settings.php:516`, `if ( 'https' === $scheme )`. PHP's `parse_url` preserves scheme case (`parse_url("HTTPS://x", PHP_URL_SCHEME) === "HTTPS"`, verified).

- Trigger: an operator saves the settings form with `source_root` = `HTTPS://central.wordcamp.org` (an uppercase scheme is what several copy paths and mail clients produce).
- Consequence: the value is rejected, the previous root is kept, and the warning says *"it must be an https URL unless WCAC_ALLOW_INSECURE_CENTRAL is defined"* (`:338`) — telling an operator who did type https that they did not. At 740a504 the same input was stored and worked. All five syncs are pinned to the old root until it is retyped lowercase.
- Severity **low**: it fails safe (previous value kept, `warn` logged, field flagged in `self::$rejected`), corrupts nothing, and the five syncs keep running on the prior root. A `strtolower()` on the scheme fixes it. Note `WCAC_Source::central_scheme_ok()` has the same case-sensitive test, but it reads an already-validated stored root, so it is not independently reachable.

## Deliberate base → work changes affecting the five syncs (not defects)

- **`userinfo` stripping** — the one remaining base↔work e2e diff. `WCAC_Settings::source_root()` (`:419`) routes every read through `strip_userinfo()` (`:488`), so `https://bob:hunter2@central.wordcamp.org` now sends `https://central.wordcamp.org`. Documented at `class-wcac-source.php:340-352` as covering "the five original syncs as well as the two Campus Connect calls". Correct — `get()` bakes the URL into its `WP_Error` messages, which are logged and printed. `strip_userinfo` preserves port, path and `@` inside a path; it drops a query string, which is meaningless on a root. Operational caveat: an operator who had embedded credentials there loses them silently.
- **`WCAC_Airtable::upsert()`** gained a 4th `$options` param (`class-wcac-airtable.php:179`). All three five-sync call sites (`class-wcac-sync.php:1212`, `:1286`, `:1936`) still pass 3 args, so `typecast=true` / `continue_on_error=false` reproduce baseline exactly; only the Campus Connect site (`:1618`) passes options. The error path now rewraps the `WP_Error` with `status/created/updated/chunk` data, and `error_status()` (`:507`) still resolves `status` — the `airtable_500` and `airtable_422` traces are byte-identical to baseline including log text and `state.errors`.
- **`throttle()`** moved `$this->last_request` → `self::$last_request` (`class-wcac-airtable.php:74`), making the rate limiter shared across instances. Strictly safer; measured throttle time is unchanged within jitter (3.30s full, 3.96s incremental, both trees).
- **`run_slice()`** gained `$only` and a 300s lock extension — every branch is gated on `'cc' === $job['to']`, `$only` defaults to `''`, and the `finally { delete_transient(self::LOCK); }` releases the lock even if a cc job throws, so the five syncs keep the same 60s lock they had at baseline. The budget is still checked only between jobs, so a long cc job can overrun a slice, but queued five-sync jobs are left in place for the next tick rather than dropped — confirmed in the interleaved runs, where all five syncs completed with `updated=38` identical to baseline with Campus Connect enabled.
- **Table IDs and job payload shapes are unchanged.** All five `tbl_*` defaults are byte-identical (only whitespace realignment); `sync_campus_connect` defaults to `0`. The `kid` job payload is `{to, wcid, site}` in both trees, so a queue persisted across the upgrade drains correctly.

---

## Lens: Cold read of the block machinery

Harness: `/private/tmp/claude-501/-Users-maciejpilarski-GitHub/c69d186a-9a95-4438-a5fc-a3c7fe812575/scratchpad/L7/` (real `WCAC_Sync`/`WCAC_CLI`/`WCAC_Admin`/`WCAC_Mapper` from the working tree, namespace-rewritten only; stubbed WP, fake Airtable with genuine performUpsert merge, virtual clock, 142 seeded curated rows). Scenarios `s1_404_nightly_test.php`, `s2_strand.php`, `s3_volume_loop.php`, `s4_cron_flags.php`, `s5_confirm.php`, `s6_growth_cron.php`.

---

## Route inventory (closed by grep, then executed)

`cc_block` is lowered in exactly two places: `cc_unblock()` (sync:686) and `cc_ok()` (sync:885). `cc_unblock()` has two callers — `clear_campus_block()` (sync:739) and `queue_campus_now($ack)` (sync:786). `clear_campus_block()` has three callers: cli:265 (`wp wcac test`), admin:274 (`test_central`), bootstrap:90 (`wcac_central_credential_changed`). `queue_campus_now(true)` has two: admin:228, cli:1360. `cc_ok()` has one: sync:1847, behind the `cc_blocked()` gate at sync:1380. That is the whole surface.

## Q1 — unattended clear/prevent: **yes, three routes**

**1. `wp wcac test` stands down the HTTP 403 block, every night, forever.** `class-wcac-cli.php:265` → `clear_campus_block()`, which clears any block whose kind is `access` (sync:735). `cc_fail()` classifies 403 as `access` at sync:1015. But the 403 reason string is *"Authenticated, but that Central account does not hold view_wordcamp_reports"* (sync:971) — it concedes the credential is fine. The evidence `test()` offers is `central_identity_probe()` → `wp/v2/users/me` (source:262), i.e. proof of exactly the thing the block already conceded. Executed (`s1`), 20 nights of `wp wcac sync` + `wp wcac test`:

```
no capability (403)  report requests=20  peak cc_fails=1  final cc_fails=0
                     block standing=no   cleared by `wp wcac test`=20
```
Consequence: 20 authenticated-but-refused requests to a third party's server instead of the 1 the immediate latch is written to allow; on the morning after, the admin screen shows no block, no reason (`cc_block_why` is `''`), and 0 consecutive failures. `readme.txt:325-330` claims this cannot happen ("a credential that works now is evidence about the credential, and about nothing else... a nightly run of it cannot quietly stand one of them down"). It can.

**2. Same route, HTTP 404 (report route renamed).** `cc_fail()`'s deferred branch writes kind `access` whenever `$why` is non-empty (sync:1028) — and in that branch `$why` is non-empty *only* for 404 and 3xx, the two causes the code itself describes as route/redirect problems, not credentials. A2 replaced a hard-coded `'access'` with a test that still lands on `access` for the two cases it should not. Executed (`s1`):

```
route renamed (404)  report requests=20  peak cc_fails=5  final cc_fails=0
                     block standing=no   cleared by `wp wcac test`=4
```
The five-in-a-row latch armed four times in 20 nights and was stood down four times. Contrast the same run at HTTP 500 (unclassified → kind `data`): `report requests=5, block standing=YES` — the latch holds exactly as designed. So the mechanism is sound; the classifier is one predicate too wide.

**3. `--accept-growth` is not gated at all, and a crontab line carrying it prevents every block, on every run, forever.** `--clear-block` gets `WP_CLI::confirm()` (cli:1352) and an explicit *"must never be scheduled"* in `readme.txt:186`. `--accept-growth` gets neither — no prompt, no warning, and its docblock (cli:296-297) says only "accept a report that has grown past the volume guard", understating it: one flag waives the volume guard (sync:1050), the creates guard (sync:1120) and the zero-write alarm (sync:1794). It is consumed and re-armed per run, so a crontab line re-arms it every time. Executed from cron (STDIN `/dev/null`), report unchanged at 142 rows, Central re-numbers the ID field so every row is a create (`s6`):

```
crontab: --run                    exit=1  table=284  created=142  block=YES
crontab: --run --accept-growth    exit=1  table=284  created=142  block=no
```
Both create 142 permanent orphans beside the 142 curated rows; only the first stops the *next* run. And with a volume regression (`s4`) the same cron line takes the table 142 → 1500 and leaves `cc_high_rows = 1500`, so the volume guard never fires again below 2250 — a block prevented, permanently, that no human ever saw. This is the larger of the two unattended hazards and the one the readme does not mention.

**Sound, and I checked it specifically:** A1's early return holds (`s1`, the 500 row: `cc_fails` stays at 5 across 20 nights of `wp wcac test`). The `--clear-block` confirm genuinely gates cron once a block stands, and does not strand `cc_growth_once` (`s5`: cron `exit=1, table=142, block=YES, growth flag left armed=no`; attended `y` → `table=1500, block=no`). `cc_ok()`'s unconditional clear of `cc_block` cannot wipe a concurrently-set latch: `campus_connect_run()` gates at sync:1380, every mid-run block setter `return`s before reaching sync:1847, `cc_creates_ok()` is deliberately after it, and `job_campus_connect()` holds `CC_LOCK` (sync:1336) inside `run_slice()`'s `LOCK`. Bootstrap:90 fires only on a genuine effective-fingerprint move (settings:241, :280) and only clears `access`. `state()` re-reads the option on every call, so no stale in-object copy can rewrite the latch.

## Q2 — stranding: **yes, one dead end and one loop**

**4. A data block plus `campus_connect_ready() === false` is unclearable from every documented route.** `admin:222` refuses `cc_clear` when the sync is switched off / no credential / no table ID, and sets `$notice = ''` — there is no notice key for it in the `$messages` map (admin:396-410), so the redirect renders *nothing*. `cli:316` refuses **every** mode of `wp wcac campus-connect`, `--clear-block` included, at the same gate. Triggering input: any data block, then the single most natural operator response to *"Campus Connect created 142 rows... those rows are new and permanent"* — untick Campus Connect while investigating. Executed (`s2`):

```
block standing: YES kind='data'
Operator unticks "Campus Connect":
  1. button inside the red notice  -> NO NOTICE AT ALL   block still standing? YES
  2. wp wcac campus-connect --run --clear-block
     Error: Campus Connect is not ready: the Campus Connect sync is switched off...
                                                          block still standing? YES
  3. wp wcac campus-connect --run --clear-block --accept-growth
     Error: Campus Connect is not ready: ...              block still standing? YES
```
The red notice stays on screen telling them to press a button that silently does nothing and to run a command that refuses. The only ways out are re-ticking a checkbox (advised nowhere, and they turned it off on purpose) or editing `wcac_state`. Note the button is *silent*, not merely refusing — it does log a warning, but the operator gets a page reload with no message at all.

**5. A volume block loops forever on the admin screen, reporting success each time.** `admin:605`'s button calls `queue_campus_now(true)` with no growth acceptance, and `grep -c 'accept.growth' includes/class-wcac-admin.php` is **0** — the screen has no accept-growth control. Executed (`s3`), everything configured and ready:

```
press 1 -> notice=queued   then a tick: block=YES
press 2 -> notice=queued   then a tick: block=YES
...   (five for five)
```
The operator is shown a green *"Sync queued. It will run in the background."* every time while the latch silently re-arms on the next tick.

**6. Three surfaces still print the two-flag command A3 fixed in the reason strings.** All three sit beside a volume-block reason that correctly names the three-flag form, so the operator reads two contradictory instructions at once:
- `includes/class-wcac-admin.php:601` — inside the red notice: *'run "wp wcac campus-connect --run --clear-block" at a terminal'*.
- `includes/class-wcac-admin.php:794` — the data-block branch of the status table, which is reached only for volume/creates/partial/contract blocks: same string.
- `includes/class-wcac-cli.php:1654` — `block_text()`, printed by `wp wcac status` and by `--run`'s own summary table. Executed (`s3b`) — one table row carries both:

```
| blocked | yes, since ...: The Campus Connect report jumped from 142 rows to 1500 in one run.
  Check the report on Central, then accept it with: wp wcac campus-connect --run --clear-block
  --accept-growth | Clear it with: wp wcac campus-connect --run --clear-block
```
Following the right-hand half: `exit 1`, `block=YES` — straight back. Following the reason's form: `block=no, table rows=1500`.

**Also worth flagging, though not stranding:** the `cc_block` at `sync:1592` ("Every Campus Connect row was dropped for want of an ID") and the partial-write block at `sync:928` carry no recovery instruction at all, so on the CLI the only advice the operator gets is `block_text()`'s two-flag string from finding 6. For those two kinds it happens to be correct.

---
