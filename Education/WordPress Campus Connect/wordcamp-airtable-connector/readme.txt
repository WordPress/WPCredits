=== WordCamp Airtable Connector ===
Contributors: gomp
Tags: airtable, wordcamp, sync, rest-api, reporting
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.1.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Syncs WordCamps, Meetups, Sessions, Speakers, Sponsors and Campus Connect events from WordCamp.org into an Airtable base.

== Description ==

This plugin mirrors WordCamp.org data into Airtable so you can pivot, chart
and cross-reference it with your own records.

It reads three sources:

* **central.wordcamp.org, public routes.** `/wp-json/wp/v2/wordcamps`
  (~1,500 records) and `/wp-json/wp/v2/meetups` (~720 records). Central
  flattens the wcpt meta onto the REST response, so dates, venue, coordinates,
  organiser and attendee counts all come through.
* **each camp's own site.** `/wp-json/wp/v2/sessions`, `/speakers` and
  `/sponsors`, crawled from the `Site URL` on the camp record.
* **central.wordcamp.org, one authenticated route.**
  `/wp-json/wordcamp-reports/v1/campus-connect-details`, the Campus Connect
  details report. This one needs an account on Central that holds
  `view_wordcamp_reports`; anonymous callers get HTTP 401. It is off until you
  configure that credential, and none of the other five syncs depend on it.

Everything is written with Airtable's upsert endpoint, merging on a stable key,
so re-running a sync updates rows in place instead of duplicating them.

= Campus Connect =

The Campus Connect Events table was populated by hand before this sync
existed, so this sync is deliberately more cautious than the other five:

* It sends a field only when the report carries a usable value for it.
  Airtable's upsert leaves an omitted field untouched, so the connector cannot
  blank or overwrite a curated cell with nothing. A value that is not really a
  number, `n/a` in an attendee count for instance, is treated as no value
  rather than as zero.
* It never writes a `Status` option that does not already exist in the
  Airtable field. A status it cannot place is counted, named in the log, and
  that row's `Status` is left exactly as it was.
* It reads the whole report on every run. The route takes no page and no since
  parameter, so there is no delta to ask for, and that is what makes a run
  that died halfway through safe to simply repeat.
* It refuses a report that has grown implausibly since the last good run.
  There is no delete verb anywhere in this plugin, so a query regression on
  Central that started returning every WordCamp would create hundreds of rows
  that a human would then have to remove by hand.

= What this plugin cannot do =

The WordCamp Reports plugin on Central exposes exactly one live REST route,
`campus-connect-details`, gated on the `view_wordcamp_reports` capability and
answering 401 to anonymous callers. That route is the Campus Connect sync's
only source. The financial reports (Ticket Revenue, Sponsor Invoices, Payment
Activity, Sponsorship Grants) are still admin-only CSV exports with no REST
route of their own, so no financial data can be synced by any API client, this
one included.

Nothing is ever deleted. The connector creates and updates rows; removing one
is always a manual step in Airtable.

= Merge keys =

| Table | Merge field | Value |
| --- | --- | --- |
| WordCamps | `WordCamp ID` | WP post ID on Central |
| Meetups | `Meetup ID` | WP post ID on Central |
| Sessions | `Session Key` | `{WordCamp ID}:{Session ID}` |
| Speakers | `Speaker Key` | `{WordCamp ID}:{Speaker ID}` |
| Sponsors | `Sponsor Key` | `{WordCamp ID}:{Sponsor ID}` |
| Campus Connect Events | `WordCamp ID` | WP post ID on Central |

Session, speaker and sponsor post IDs are only unique within one camp site,
which is why those three are composite.

== Installation ==

1. Install and activate the plugin.
2. In Airtable, create a personal access token with the **data.records:read**
   and **data.records:write** scopes, granted on the target base. Schema scopes
   are not needed, because the tables are created by hand.
3. Go to **WordCamp Sync** in wp-admin, paste the token, confirm the base and
   table IDs, and press **Test connection**.
4. Press **Queue full backfill**, or run `wp wcac sync --full` for a much
   faster one-shot backfill.

= How work actually gets queued =

A cron event runs every five minutes and drains whatever is already pending.
It does not queue anything itself. An incremental sync is queued only when
somebody presses **Sync changes now** or runs `wp wcac sync`, and a full
backfill only from **Queue full backfill** or `wp wcac sync --full`. Nothing in
the plugin schedules either one, so if a job is lost to a fatal error or a
killed request it stays lost until a human queues another. Check the status
screen rather than assuming the connector is keeping itself current.

= Campus Connect, the extra steps =

Steps 6 to 8 are WP-CLI only, and step 7 is a hard gate: the sync skips every
run until a preflight has passed for the configured table, so this one sync
needs shell access at least once. The settings screen reports whether that
preflight has passed and names the command, but cannot run it for you.

1. Run `wp wcac central-check` before creating anything. It reports what
   authentication central.wordcamp.org advertises, whether a stored credential
   authenticates at all, and what the report route answers. If application
   passwords are not available to you there, stop: this design has no
   fallback, because a cookie and nonce cannot be replayed from cron.
2. On central.wordcamp.org, signed in as a user who holds
   `view_wordcamp_reports`, go to **Users > Profile > Application Passwords**
   and create one named for this site. Copy it once; it is not shown again.
   This is an account on Central, and has nothing to do with the WordPress
   administrator account you use on this site.
3. Store it. There are three routes, and the first is preferred:
   * In `wp-config.php`, as `define( 'WCAC_CENTRAL_USER', '<the login name>' );`
     and `define( 'WCAC_CENTRAL_APP_PASSWORD', '<the application password>' );`.
     Constants win over anything stored in the database, and the admin
     fields switch to read-only while they are set. If you saved a credential
     into the database first, the constants hide that copy without deleting
     it: press **Remove stored credential** on the settings screen, or run
     `wp wcac central-credential --forget`, and only then is there no at-rest
     copy in `wp_options`.
   * `wp wcac central-credential --user=<the login name>`, which reads the
     password from STDIN with terminal echo turned off, so it stays out of
     `ps` and out of shell history. On a terminal where echo cannot be
     proved off, the command refuses to prompt rather than print what you
     type; pipe the password in instead, as
     `wp wcac central-credential --user=<the login name> < secret.txt`.
   * The **Central credential** form on the settings screen. It is a separate
     form from the rest of the settings on purpose, so that a browser
     autofilling your own wp-admin login cannot quietly replace it during an
     unrelated save. The stored value is never rendered back to the browser;
     the screen shows a short fingerprint instead, which is what tells you the
     value has changed under you.
4. Tick **Campus Connect events** under **What to sync**, and confirm the
   Campus Connect Events table ID.
5. Press **Test Central access**, or run `wp wcac central-check` again. On a
   401 read the FAQ below: three quite different faults produce it.
6. Run `wp wcac campus-connect --statuses`. It reconciles every status the
   report returns against the Airtable field and exits non-zero if any status
   has nowhere to go. For each one it names: add an option to the `Status`
   field in Airtable with exactly that label, then paste exactly that label
   into **Approved Status options** on the settings screen, one per line.
   Approving a label approves that one label and loosens nothing else.
7. Run `wp wcac campus-connect --preflight`. It proves that every existing row
   has a `WordCamp ID` and that no two rows share one. The sync refuses to run
   until this passes: a blank ID cannot be matched, so instead of failing it
   would quietly add a second row beside the curated one, permanently, since
   nothing here deletes.
8. Run `wp wcac campus-connect --dry-run`. It prints every cell it would
   change, current value beside new value, and writes nothing. Read it. This
   is the only review the hand-imported rows get.
9. Run `wp wcac campus-connect --run`, or press **Sync Campus Connect now**.
   Read the next section before you put either of them in a crontab. Neither
   is quite the line you would guess, and one flag must never be in one.

The five-minute cron keeps running while you do steps 6 to 9. Either press
**Discard queue** first, or accept that the command line and the cron are two
independent throttles against a single Airtable rate limit, and that Airtable
will tell one of them to slow down.

= What is safe to run unattended =

Nothing in this plugin puts a sync on a schedule, so a cadence is a crontab
line you write yourself. Two commands belong in one:

* `wp wcac sync` queues and drains an incremental run of everything that is
  enabled, Campus Connect included. This is the ordinary scheduled line.
* `wp wcac campus-connect --run` runs that one job and nothing else. Also safe
  to schedule, though it is really the command for an operator at a terminal.

Both are safe unattended for the same reason: neither one clears a Campus
Connect block, and while a block stands neither one writes anything to
Airtable. `wp wcac campus-connect --run` prints the reason and exits non-zero,
so cron mails it to you. `wp wcac sync` runs the other five syncs exactly as
usual and logs one line saying the Campus Connect job was skipped. A blocked
sync that nobody has looked at stays blocked, and stays quiet apart from that.

`wp wcac campus-connect --run --clear-block` must never be scheduled.
`--clear-block` is not a retry flag. It is an operator asserting that they
read the block reason and chose to discard it, so the command prints the
reason and asks for confirmation on the terminal. Under cron there is nothing
on standard input to answer with, so it stops without running rather than
assuming a yes. That is the point of it: the Campus Connect Events table has
no delete verb, and a latch discarded by a machine was never really discarded
by anybody.

== Frequently Asked Questions ==

= How long does a full backfill take? =

Through WP-Cron, many hours: it crawls ~1,500 camp sites and each five-minute
tick only spends the configured time budget (20 seconds by default). With
WP-CLI, `wp wcac sync --full` runs the same queue without that pacing.

= Does it delete Airtable rows for cancelled camps? =

No. It only creates and updates. A camp that changes status to `wcpt-cancelled`
has its `Status` cell updated; nothing is ever removed.

= Airtable says I have too many records. =

The full data set is large. Sessions and speakers across every camp run to
tens of thousands of rows. Turn off **Sessions, Speakers and Sponsors** in the
settings, or use a plan whose per-base record cap fits.

= Why does the Campus Connect sync never clear a cell? =

Because the destination rows were imported by hand and hold values the report
does not have. The mapper omits any field it has no usable value for, and
Airtable's upsert leaves an omitted field untouched, so the sync can improve a
row but cannot degrade one. The cost of that guarantee is that it cannot empty
a cell either: correcting a value to blank is a manual edit in Airtable.

= The log says a Campus Connect status was left untouched. =

Two different things cause that, and the log line says which. Either the
report used a status this plugin does not recognise, which means the status
list on Central has changed and the plugin needs updating, or the label it
maps to has no matching option in the Airtable `Status` field yet.

For the second case: add the option in Airtable with exactly that label, then
paste the same label into **Approved Status options** on the settings screen.
The connector will not invent a `Status` option on its own, because a stray
option would split the grouping in every view and report built on that field.

= Why does the Campus Connect sync re-read every event on every run? =

The report route takes no page and no since parameter and returns the whole
set, so there is nothing to page through and no delta to request. That turns
out to be the safest available shape: the job is a full idempotent re-read, so
a run interrupted halfway is repaired exactly by running it again.

It is also the only job in the plugin that cannot be split across cron ticks.
A run is typically 8 to 16 seconds and can approach a minute, so on the default
20-second budget it will routinely take a whole tick to itself.

= Central answers 401. What is wrong? =

Three quite different faults produce a 401, and `wp wcac central-check` tells
them apart. It reports what authentication Central advertises, whether the
stored credential authenticates at all against `wp/v2/users/me`, and what the
report route itself answers.

* The username or the application password is wrong.
* The application password was revoked on Central. Create a new one; you do
  not need to change the account's actual password.
* This site's server strips the `Authorization` header before PHP sees it,
  which some Apache and proxy configurations do. Suspect this when the same
  credential works from `curl` on another machine.

A 403 is a different answer: the account authenticated, but does not hold
`view_wordcamp_reports`.

= Where does the Central credential travel, and who can see it? =

It is sent to central.wordcamp.org in an `Authorization: Basic` header, which
is base64. Base64 is an encoding, not encryption. Any plugin on this site that
hooks `pre_http_request` or `http_api_debug` can read that header, and
outbound-request loggers such as Query Monitor do exactly that. The Airtable
personal access token has always had the same exposure, as a `Bearer` header.

The connector redacts its own log contexts, never prints either secret, and
never puts either in an error message, but it cannot stop another plugin on
the same site from reading an outgoing request. That is why the `wp-config.php`
constants are the documented default: with no copy in the database, the
credential does not travel in database exports or support dumps. Defining the
constants does not by itself remove a copy you saved earlier; it only stops
that copy being used. Press **Remove stored credential** on the settings
screen, or run `wp wcac central-credential --forget`, to delete it. An
application password is also revocable on its own from the profile screen on
Central, which a real password is not.

= Can I point this at a staging copy of WordCamp Central? =

Yes, that is what **Source site** is for. It has to be an `https` URL, written
with its scheme, because the Campus Connect request attaches an
`Authorization` header to whatever host is stored there. An `http` root is
refused and the screen says which setting it refused and why, rather than
saving the page and quietly keeping the previous value.

A staging or local Central that only speaks `http` is supported deliberately:
define `WCAC_ALLOW_INSECURE_CENTRAL` as `true` in `wp-config.php`, then save
the setting. Do not define it anywhere a real credential is used, because it
allows that header to go out in cleartext.

= Campus Connect stopped syncing and the screen says it is blocked. =

The job latches itself off rather than repeat a write it cannot take back. The
reason is on the settings screen, and in `wp wcac status` as `campus blocked`.

Five of the faults that arm it are about access. Four latch the first time
they are seen, because no number of retries fixes any of them: no Central
credential is configured, the source site would have to carry that credential
in cleartext, Central rejected the credential (401), or the account does not
hold `view_wordcamp_reports` (403). The fifth is everything else that fails, a
missing report route and a redirect included, and that one has to fail on five
consecutive runs before it latches. The count is on the status screen the
whole time it is climbing, and a run that succeeds puts it back to zero.

Four more guards are about what would be written rather than about access.
Three latch on first sight: the report grew implausibly since the last good
run, the run created more new rows than the creates limit allows, or every row
in the report arrived without an ID. The fourth latches when the same rows
fail to write on five runs in a row.

The latch has no expiry. It is not a back-off, and it does not lift after an
hour, a day or a week: this connector has no delete verb, so a latch released
by a clock is a latch released onto an unattended run, which is the one run
nobody is watching. It is cleared by a person, and by nothing else.

Routes back, once you have put the cause right:

* Press **Clear the block and sync Campus Connect now**, in the red notice at
  the top of the settings screen. Clears any block, and queues the job.
* Run `wp wcac campus-connect --run --clear-block` at a terminal. It prints
  the reason, asks you to confirm, and then runs. Clears any block.
* Press **Test Central access**, or run `wp wcac test`, and have it succeed.
  Clears one of the five access faults, whether it latched at once or after
  five runs, and nothing else: a credential that works now is evidence about
  the credential, and about nothing else. A volume, creates, partial-write or
  contract block survives it, because a successful ping says nothing about any
  of those. `wp wcac test` says so on its way past, so a nightly run of it
  cannot quietly stand one of them down.
* Save a working Central credential. Same reach as a successful test, for the
  same reason.

Queueing a sync clears nothing, on either the command line or the button.
While a block stands, **Sync Campus Connect now** queues nothing and says so,
and `wp wcac campus-connect --run` writes nothing and exits non-zero. The
other five syncs are unaffected throughout.

The volume guard and the creates guard want one thing more than a cleared
block. Clearing the block lets a run start; it does not tell the connector
that a size it refused is correct. If the report really has grown, or the new
rows really were right, confirm that in Airtable and against the report on
Central first, then run
`wp wcac campus-connect --run --clear-block --accept-growth`, which accepts
the size once and only for that run. There is deliberately no button for
`--accept-growth`.

= Does uninstalling remove the credentials? =

It removes everything this plugin stored, including both secrets, because they
live inside the plugin's own option rows. It does not touch
`WCAC_CENTRAL_USER` or `WCAC_CENTRAL_APP_PASSWORD` if you put them in
`wp-config.php`, and it cannot revoke anything at the far end. Delete the
application password on Central and the Airtable token yourself.

== Changelog ==

= 1.1.9 =

* Fixed: `--preflight` ended with "Preflight stamped; the sync job will now run"
  whatever the toggle said. With the scheduled sync switched off that is simply
  untrue, and it is how a gate gets blamed for silence it is not causing. It now
  says which of the two it has unblocked.

= 1.1.8 =

Switching the Campus Connect sync off now stops the scheduled run without also
stopping an attended import.

While the Central credential cannot read the report, the useful posture is: no
timed runs reaching for a route that answers 403, but a manual `--from-file`
import whenever fresh figures are wanted. That was not possible before, because
one check answered two different questions.

* Fixed: `wp wcac campus-connect` gated every mode on `campus_connect_ready()`,
  which folds together a Central credential, the operator's toggle and a
  destination table. Switching the toggle off therefore refused the file import
  as well, which is the opposite of what switching it off is for. Each mode now
  requires only what it actually uses: the destination table always, and the
  credential and toggle only when the report will be read over the network.
* Fixed: `--preflight` demanded a Central credential and the toggle, although it
  reads the Airtable table and never the report.
* Changed: the status line read a flat "off", which sounded like nothing worked.
  It now says the scheduled run is off and that `--from-file` still works.

The scheduled job builders are unchanged and still gate on
`campus_connect_ready()`, so the toggle stops the cron exactly as before.

= 1.1.7 =

Campus Connect can sync from an exported report, so the data is no longer held
hostage by a permission that has not arrived.

The credential authenticates against Central but the account holds no role on
central.wordcamp.org at all, so the report route answers 403 and no code here
can change that. An operator with a browser session can still run the report
and export it, and that export is now a valid source.

* Added: `--from-file=<path>` on `wp wcac campus-connect`, accepting the tab- or
  comma-separated Campus Connect Details export with its header row. Only the
  source of the rows changes: the preflight gate, the volume and creation
  guards, the never-clobber mapper, the failure latch and the `Synced At` stamp
  all behave exactly as they do for a live read. The override lasts for the one
  command, so a later cron run can never inherit it, and it is refused with
  `--preflight`, which reads Airtable and never the report.
* Added: the export's display column names are translated back to the report's
  own keys. Three differ (`Institution Name`, `City` and `Country` against
  `Venue Name`, `_venue_city` and `_venue_country_name`), and skipping them
  would have blanked a venue, a city and a country on every row.
* Fixed: the report began spelling it "Canceled" where the Airtable choice is
  "Cancelled", which sent 14 rows down the unmapped path and left their Status
  unwritten. A spelling bridge maps the two. It is a rename only: the result
  must still be an existing Airtable choice, so it cannot be used to smuggle in
  a value the destination does not hold, and adding a second spelling to
  Airtable would have split WPCC-Tracker's grouping in two.
* Suite at 34 checks, up from 10, covering the alias, the default-deny it must
  not bypass, the header translation, and the four ways a bad export is refused
  rather than half-imported.

= 1.1.6 =

The Campus Connect rows now carry the date they were written, so the WPCC-Tracker
dashboard can report how old the underlying Central data is instead of only when
it last read Airtable.

* Added: the Campus Connect sync stamps a `Synced At` column on every row it
  writes, bringing it in line with the five older syncs. It is the one value in
  that payload written unconditionally, because it is this plugin's own stamp
  rather than a report value, so there is no hand-curated cell for it to
  overwrite. This needs the `Synced At` dateTime column on the Campus Connect
  Events table, added 7 October 2026.
* Note: `Synced At` is deliberately absent from the `--dry-run` field list. The
  sync rewrites it on every row on every run, so including it would report 142
  changed cells for ever and bury the handful that matter.

= 1.1.5 =

Recorded late: this release shipped on 3 September 2026 without a changelog
entry.

* Fixed: `wp wcac test` reported success while the Campus Connect report route
  was still refusing the credential. The default run now reports only what it
  actually proved, and the full end-to-end check moved behind `--deep`.

= 1.1.4 =

Recorded late: this release shipped on 3 September 2026 without a changelog
entry.

* Fixed: the documented WP-CLI subcommands did not exist. WP-CLI derives a
  subcommand name from the method name verbatim, so the hyphenated names given
  throughout the documentation had never been registered. `campus-connect`,
  `central-check` and `central-credential` now resolve, via `@subcommand`
  annotations.

= 1.1.3 =

A second fix pass over the 1.1.1 Campus Connect release, closing what the first
one left open. The first two are regressions the Campus Connect work introduced
into the five older syncs.

* Fixed: WP-Cron can no longer strand the queue lock for five minutes and stall
  the WordCamps, Meetups and per-camp syncs. WordPress fetches `wp-cron.php`
  over loopback HTTP, so a default cron run is a web request, and the server
  kills it on its own timeout like any other. The long lock is now taken only
  on the command line, where there really is no web request to lose. Every
  other context, that loopback cron request included, takes the same
  sixty-second lock the five original syncs have always used.
* Fixed: a stored **Source site** the plugin cannot parse is no longer wiped by
  an unrelated settings save. The field rendered empty, the empty value was
  saved back over the stored one, the screen said "Settings saved.", and every
  sync then failed with no host to read.
* Fixed: a legacy `user:pass@` source site is now rewritten out of `wp_options`
  on WP-CLI and cron runs as well as on a wp-admin load. A site nobody signs
  into kept that cleartext row indefinitely, through every backup and staging
  clone, even though every reader had already stopped using the credential part
  of it.
* Fixed: that rewrite, and the warning telling you to treat what it removed as
  exposed, now happen only when the stored value really carries userinfo in its
  authority. A trailing slash or a query string used to trigger both, so the
  log could tell you to rotate an application password that had never been
  there. A username with no password beside it now says exactly that.
* Fixed: removing a stored Central credential that the `wp-config.php`
  constants already override no longer releases a Campus Connect block. The
  credential the sync actually uses has not changed, so the failure counter
  must not be reset.
* Fixed: `wp wcac central-credential` no longer quietly writes a database
  credential that the `wp-config.php` constants override, and no longer
  confirms it by printing the constant's fingerprint. The settings screen has
  refused that since 1.1.1; the command did not.
* Fixed: `wp wcac status` reports the same Campus Connect verdict as the
  settings screen. On a site whose preflight had never passed it said `ready`
  for a job that could only skip.
* Fixed: `wp wcac campus-connect --run` counts jobs rather than slices, and
  names any job it ran that was not the Campus Connect one. A Campus Connect
  job that returns immediately, which is what it does until the preflight has
  passed, leaves enough of the budget for one more queued job to run.
* Fixed: the zero-write line is worth reading now. Cells in a chunk that failed
  to write are no longer counted as written, and a field raises a warning only
  when it wrote more zeroes than the run before it did. `Series Event` is
  legitimately 0 on nearly every event, so counting it afresh every run would
  have buried the regression this counter exists to catch; that steady state is
  an info line, and only a rise is a warning.
* One spelling, `(blank)`, on the `--statuses` table too, which 1.1.1 left
  printing `(empty)` while changing the other two surfaces.

= 1.1.2 =

Follow-up to the merge of WordPress/wordcamp.org#1963, the upstream PR that
added the `campus-connect-details` route this sync reads.

* Fixed: a Campus Connect event in **Needs Action** can now have its Status
  written. That slug (`wcpt-needs-action`) is the one status exclusive to
  Campus Connect: `WordCamp_Admin::get_post_statuses()` serves
  `get_campus_connect_statuses()` for a Campus Connect post and unsets
  `wcpt-needs-action` for every other post. The Airtable field was built from
  the WordCamp pipeline and has no such option, so the label resolved to
  `absent` and the cell was left untouched on every run: no error, no write,
  a column that never moved. Approved in code via a new
  `CAMPUS_STATUS_PREAPPROVED`, kept separate from `CAMPUS_STATUS_PRESENT` so
  that const stays a truthful snapshot of the live field.
* Documented: `CAMPUS_STATUS` maps nineteen slugs, but only the nine in
  `WordCamp_Loader::get_campus_connect_statuses()` are reachable for a Campus
  Connect event. The other ten stay deliberately, because the report queries
  `post_status => 'any'` and an event bulk-edited into a WordCamp-pipeline
  status before the Campus Connect list existed must still resolve.
* Documented: the labels in `CAMPUS_STATUS` are wcpt's global ones, which is
  correct because the route emits raw slugs and these strings are only ever
  Airtable option names. They are not what wp-admin shows for a Campus Connect
  post: `get_campus_connect_statuses()` renames four of the nine. Rebuilding
  the Airtable options from that dropdown would put four mappings outside
  `CAMPUS_STATUS_PRESENT` at once.

= 1.1.1 =

A fix pass over the 1.1.0 Campus Connect release. Four of these are
regressions the new sync introduced into the five older ones.

* Fixed: a refused **Source site** edit now says so. An `http` root was
  rejected silently while the screen still reported "Settings saved.", so the
  five original syncs went on reading the previous host. The notice is now an
  error naming the setting, and naming `WCAC_ALLOW_INSECURE_CENTRAL` as the
  supported way to point at an http staging Central.
* Fixed: `wp wcac test` exits 0 again whenever Airtable responds. A Central
  credential that no longer authenticates is now a warning, instead of failing
  a command that monitoring runs to check Airtable.
* Fixed: `wp wcac campus-connect --run` no longer drains up to fifty queued
  WordCamps, Meetups or per-camp jobs. A Campus Connect job that was already
  pending is moved to the head of the queue, so the command runs that job
  first, and says that it moved it.
* Fixed: a Campus Connect job interrupted inside a wp-admin request no longer
  holds the queue lock for five minutes and stalls the other syncs. The longer
  lock was still taken on cron requests, which 1.1.3 corrects.
* Fixed: a `user:pass@` left in the source site by a 1.0.x install is now
  stripped everywhere, not only on the Campus Connect path. It is rewritten out
  of the stored option on the next wp-admin load, and since 1.1.3 on WP-CLI and
  cron runs as well; it is no longer sent as Basic auth by the WordCamps and
  Meetups syncs nor baked into their error messages, and it is no longer
  printed by `wp wcac central-check` or rendered into the settings field.
* Fixed: log context really is redacted before it is stored now, as the FAQ
  above has claimed since 1.1.0.
* Fixed: `wp wcac central-credential` refuses to prompt on a terminal where it
  cannot prove echo is off, rather than printing the application password
  while announcing that it did not.
* Fixed: a credential saved into the database before the `wp-config.php`
  constants were added can be removed from the settings screen again. The
  constants hid that copy but left it in `wp_options`.
* Fixed: the Airtable token field now uses `autocomplete="new-password"`, so a
  browser password manager cannot drop this site's own wp-admin password into
  it and have it sent to Airtable.
* Fixed: Campus Connect writes a boolean `false` as 0 instead of dropping it,
  so `Series Event` and `Actual Attendees` can be corrected downward and not
  only upward.
* Fixed: Campus Connect refuses a calendar-invalid date such as `2026-02-30`
  and counts it as unparsed, instead of letting Airtable roll it forward onto
  a curated cell as a plausible wrong day.
* New: a run that writes a zero, or the string "0", now reports it in the log
  with a count per field. It is the one write that can flatten a curated
  column without anything failing.
* Fixed: a chunk that fails on every run now escalates. Ten rows failing while
  the rest succeed used to reset the failure counter, so the block never
  latched and the screen kept showing an unrelated stale error.
* Fixed: skipping a run because the preflight has not passed is a
  configuration gate, not a run failure. It no longer advances the error
  counter, and it now writes the reason and the command that clears it.
* Fixed: the settings screen shows whether the Campus Connect preflight has
  passed, and stops offering the sync button until it has. `wp wcac status`
  went on reporting the job as ready until 1.1.3.
* Fixed: saving the Central credential form without changing anything no
  longer releases a Campus Connect block. Saving releases it only when the
  stored credential actually differs. Removing a stored copy that the
  `wp-config.php` constants already override still released it; 1.1.3 closes
  that.
* Fixed: **Sync Campus Connect now** no longer reports a fresh job when one
  was already pending.
* `Organizer Name` is no longer a documented required key of the report.
  Nothing maps it, so its absence warned about a column that does not exist in
  the destination table.
* One spelling, `(blank)`, for a blank status in the `--dry-run` gap tables
  and in the gap counts the settings screen renders. The `--statuses` table
  went on printing `(empty)` until 1.1.3.

= 1.1.0 =
* New: Campus Connect events, read from Central's authenticated
  `wordcamp-reports/v1/campus-connect-details` report and upserted into the
  Campus Connect Events table on `WordCamp ID`.
* The Campus Connect mapper emits a field only where the report has a usable
  value, so it cannot overwrite a hand-curated cell with a blank, a coerced
  zero, or a date it failed to parse. Unparsed values are counted and logged
  instead of written.
* A `Status` option is written only when it already exists in the Airtable
  field or has been approved, by exact label, on the settings screen.
* Guards against writing a worse data set: the sync refuses a report that
  returned nothing, refuses one that has grown implausibly, requires a
  preflight proving the merge column is present and unique, and reports when
  the report's own key set changes.
* New commands: `wp wcac campus-connect` (`--statuses`, `--preflight`,
  `--dry-run`, `--run`), `wp wcac central-check` and
  `wp wcac central-credential`.
* The Central credential can live in `wp-config.php` as `WCAC_CENTRAL_USER`
  and `WCAC_CENTRAL_APP_PASSWORD`, keeping it out of the database entirely.
  It has its own admin form, is never rendered back to the browser, and can be
  removed again from the screen or with `--forget`.
* The source site must now be https, and any `user:pass@` in it is stripped,
  because the Campus Connect request attaches an `Authorization` header to
  that host and the request URL appears in error messages. A local development
  site can define `WCAC_ALLOW_INSECURE_CENTRAL` to allow http; do not do that
  anywhere a real credential is used.
* Log context is redacted before it is stored, so nothing credential-shaped
  reaches `wp_options` or a database dump.
* Corrected the documentation above: the `wordcamp-reports/v1` namespace does
  exist on Central, and the report route answers 401 rather than 404.
* Corrected the documentation above: the cron only drains the queue, it never
  fills it, so the connector does not keep itself current unattended.

Known follow-up, deliberately not in this release: the Airtable token is still
stored through `sanitize_text_field()`, which can silently mutate a pasted
token. Fixing it touches the path all five original syncs run on.

= 1.0.0 =
* Initial release: WordCamps, Meetups, Sessions, Speakers and Sponsors synced
  to Airtable via upsert, with a time-budgeted background queue, an admin
  status screen with a rolling log, and WP-CLI commands.
