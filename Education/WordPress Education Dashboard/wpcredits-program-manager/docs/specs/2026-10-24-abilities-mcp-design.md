# Program answers for Claude through the Abilities API: the design

A few read-only actions of the WPCredits Program Manager, offered to Claude through WordPress's Abilities API and the official MCP adapter, so an Administrator can ask a question in plain words and get the answer from the site's own records instead of an SSH command or an Airtable lookup. Design of 24 October 2026, for plugin main at 1.123.28 (1.123.29 is in flight and separate), WordPress 7.1.3 on wordpresseducation.org and MCP Adapter 0.7.1. Status: Phase A approved by the product owner on 24 October 2026 and planned as plugin 1.123.30 in docs/plans/2026-10-24-plugin-1-123-30-abilities-phase-a.md; Phase B waits for the decisions still open in section 11.

## 1. Why, and what is settled

Today a question such as "which track is this student on", "how many mentor applications are waiting" or "how many students does this university have this semester" is answered in one of three ways: an Administrator opens the right screen, someone runs a command over SSH (`wp wpcredits mentor`, `wp wpcredits mentor_engagement --summary`, `wp wpcredits outcome_figures`, or a one-off `wp eval`), or someone reads the Airtable base through its connector. The second needs SSH, which one person holds; the third reads Airtable, which the program is leaving. This design lets Claude ask the site instead, through a small set of named actions that return the same numbers and rows the dashboards print. It is a step on the Airtable exit: the site's record is what answers.

**Facts checked on 24 October 2026:**

- wordpresseducation.org runs WordPress 7.1.3 and already serves the core Abilities API (REST namespace `wp-abilities/v1`). Neither the live site nor the Docker copy has an MCP adapter. The plugin registers no abilities.
- The official adapter is WordPress/mcp-adapter. Its latest release is 0.7.1 of 16 October 2026, published the same day as 0.6.2, a security release for the older line; 0.7.1 limits the initialization data an MCP session stores to 4 KiB and clears oversized sessions. 0.7.0 was the first release in the WordPress.org plugin directory and deprecated bundling the adapter as a Composer library: it is installed as a plugin. It requires WordPress 6.9 and PHP 7.4.
- The wordpress-mcp Claude plugin installed on the product owner's Mac declares one HTTP server whose address is `WP_MCP_URL` and whose header is `Authorization: Basic` followed by `WP_MCP_AUTH`. It fails today because `WP_MCP_URL` is unset. Its README links the adapter to a `developer-wordpress-org/wp-mcp-adapter` repository that does not exist (GitHub answers 404); the adapter is WordPress/mcp-adapter.
- The Two Factor plugin on the site (0.16.0 on the Docker copy) lets a request signed with an application password through without the second step: its `two_factor_user_api_login_enable` filter defaults to true exactly when an application password authenticated the request.

**Settled by the product owner (24 October 2026):**

1. Read-only first. Nothing in this design writes, sends or deletes.
2. Administrators only, one application password per person, each revocable on its own.
3. Only the plugin's own abilities are offered, never core's or another plugin's.
4. The Docker copy first; the live site only on the product owner's yes, through the usual release rules.
5. The product owner sets the credential on the Mac. A Claude session never handles it.
6. The standing rules apply: no em or en dash, US English, full product names, no person named in this document or anything mirrored, a version bump and a changelog line per release, the guides republished when they change.
7. **Phase A goes first, on its own** (section 4.1); Phase B is decided after Phase A has run for a few weeks. The seven abilities stay as section 4 lists them. This design is kept on main and reaches the mirror with the next release (24 October 2026, the product owner's answer to section 11's decisions 1 and 5).

## 2. What the site holds today

Every source below is a copy the site already keeps, with the time it was last read. None needs a call to Airtable or WordPress.org at the moment of a question.

| Source | What it holds | Written by |
| --- | --- | --- |
| The roster index (`WPCPM_Roster_Index`) | One option per institution plus one for students with no institution: per Students row the name, email, status (the track or the state), institution, cohort, start, end, mentor's name, contribution team, hours, reports, username and account id. Never the accessibility answer or notes: `clean()` drops them at every write. Also the counts per institution per cohort, and the read time. | the students sync, every three hours |
| Student accounts | `wpcpm_student_program`, `wpcpm_student_mentor`, `wpcpm_student_course` (the course kept while a student waits to graduate), `wpcpm_student_institution`, the active flag and the record id | the students sync |
| Mentor accounts | the active flag, each mentor's list of students, the sign-in and visit record behind the Engagement tab (views: active, never signed in, quiet, idle, no hours, staff) | the mentors sync, sign-ins |
| Mentor applications | post type `wpcpm_ment_app`, states `new`, `held`, `spam`, `info`, `second`, `selected`, `active`, `declined`, `lapsed`, with the answers, the guard's signals, the consent evidence and notes. The public feed `wpcpm/v1/mentor-applications` already publishes counts only. | the form and the review queue |
| The institutions index (`WPCPM_Institutions_Index`) | the Institutions table's rows, by pipeline stage | the institutions sync |
| Sponsors (`WPCPM_Sponsors_Index`) | every sponsor on the site since the sponsors' move, with its tier and the test flag | the site itself |
| The Administrator Dashboard's attention strip | `WPCPM_Administrators_Cards::counts()` over `collect()`, and the syncs' `health()`: what waits for an Administrator, and when each sync last ran | read on request |
| Outcome figures (`WPCPM_Outcome_Figures`) | the graduation share and the two survey shares, with their counts and read time | weekly |

The plugin's floor stays at WordPress 6.5. The abilities register only where `wp_register_ability()` exists (6.9 and later) and the adapter is active.

## 3. The architecture, in eleven decisions

1. **The abilities live in the plugin, under one category.** A category `wpcredits-program` is registered on `wp_abilities_api_categories_init`, the abilities on `wp_abilities_api_init`, each named `wpcpm/<name>` (the plugin's prefix, as its REST namespace `wpcpm/v1` uses it). Each declares an input schema, an output schema naming every field it can return, and the annotations read-only, not destructive, idempotent. The adapter turns `wpcpm/find-student` into the tool name `wpcpm-find-student`.
2. **None of them is public.** No `meta.public`, no `meta.show_in_rest`, no `meta.mcp.public`. On WordPress 7.1 `meta.public` also opens an ability on the core REST route `wp-abilities/v1`, and the adapter's default server lists every ability marked public. Left unset, the abilities are reachable only through the plugin's own server (decision 3) and from PHP.
3. **A server of the plugin's own.** On `mcp_adapter_init` the plugin calls `create_server()` with the id `wpcredits`, the route namespace `mcp` and the route `wpcredits`, so the address is `/wp-json/mcp/wpcredits`; the HTTP transport only; the plugin's abilities listed one by one as its tools, and nothing else; and a transport permission callback (decision 6). A server built this way lists exactly the tools it is given, so no core ability and no other plugin's ability can appear on it, whatever they declare.
4. **The adapter's default server is turned off.** The plugin answers the adapter's `mcp_adapter_create_default_server` filter with false. That server lists every public ability of core and of every other plugin (the site has Jetpack, MailPoet and others installed, and any of them may register abilities), and the site has no use for it. Its address, `/wp-json/mcp/mcp-adapter-default-server`, then answers 404, and the adapter's three discovery tools do not exist on the site.
5. **One switch, and it closes everything.** A setting, "Claude access (MCP)" on Settings > Security, default off. Off, the plugin registers no ability and no server, so `/wp-json/mcp/wpcredits` answers 404. The default server stays off and the password fence (decision 7) stays on whatever the switch says, so turning it off never opens anything. Beside it on the tab: the accounts allowed to connect (empty means nobody), the ceilings (decision 8), the log's retention (decision 9), and the latest log rows. The tab writes every one of its fields on save, so the switch is changed one way at a time: a Security tab opened before a command-line change and saved afterwards writes the old value back. Read it back with `WPCPM_Settings::get_value()` after any change.
6. **Who may connect is checked at the door and again in each ability.** The transport permission callback says yes only when all four hold: the request is signed with an application password that the fence recognizes (decision 7); the account holds `wpcpm_manage_program`, the Administrators' capability; the account is on the allowed list; the switch is on. Otherwise it refuses, and WordPress answers 401 to a request with no account and 403 to a signed-in one. It never throws: the adapter's guide says an exception in that callback lets any signed-in account through, and although its 0.7.1 code denies instead, the plugin does not lean on either. Each ability's own permission callback checks the capability and the switch again, so an ability reached any other way, from PHP or a future server, is still closed.
7. **The password fence.** An application password whose name begins with "Claude" opens the plugin's MCP address and nothing else: a request signed with one (`rest_get_authenticated_app_password()` gives its id, the account's list gives its name) to any other REST route answers 403, and since 1.123.32 its sign-in fails in every request that is not a REST request: XML-RPC, and any request a plugin opens to application passwords, as Jetpack opens its VideoPress AJAX actions in wp-admin. Since 1.123.33 "a REST request" is judged by the request's address (the path at or under the REST prefix), not the `REST_REQUEST` constant, which this program's WordPress.com Atomic host sets true in requests it does not serve as REST; a Claude client reaches the server at its `/wp-json/` address. The other way round, the MCP address refuses every other way of signing in: a browser session, or an application password with another name. Why: an Administrator's application password otherwise opens every REST route that Administrator can use (users, plugins, settings, posts, the private records), and it passes the second factor by design. Behind the fence, a leaked Claude password reads these few read-only answers and does nothing else. The name is a label its holder types, not a secret, and the rule only narrows: a password named otherwise simply does not work on the MCP address, so a mistake fails closed.
8. **Ceilings, through `WPCPM_Ceiling`.** Per account, 120 calls an hour and 600 a day across all abilities, and 40 an hour for the abilities that name people (section 4.2), all three in Settings. A call over a ceiling gets an error saying which ceiling and when it reopens, and is logged. The adapter's own session limits stay at its defaults (32 sessions per account, idle after a day, kept in the user meta key `mcp_adapter_sessions`).
9. **A log row per call.** Every call, answered, refused or failed, writes a private row of the audit post type (`wpcpm_audit_entry`) under a key of its own, as the sponsors' rows and the program's own rows already share that type: when, the account, the application password's name and id (never the password), the ability, the input as given (it records whom someone looked up, which is the point of the log), the outcome (answered, refused and on which ground, or failed), how many rows went out and their record ids (never their content), and the time taken. No IP address, as the log's rule is. A daily job deletes these rows after the number of days a setting holds (90 by default, never fewer than 7), and touches no other row. The Security tab lists the latest 100; a command, `wp wpcredits mcp_log`, lists more.
10. **Answers read the site only.** No ability calls Airtable, WordPress.org or any other address while it answers. Every answer carries the read time of each source it used, so Claude can say "as of" and an Administrator knows an answer is never fresher than the last sync. A burst of questions costs local reads and nothing else.
11. **Small answers with fixed fields.** No ability returns a whole row, a whole meta array or anything its output schema does not name. Lists stop at 25 rows (10 for a lookup of people) and say `more` when cut. Every short text a person typed (a name, an institution's name) is cut to 120 characters, with control and zero-width characters removed. Free text never goes out (section 6).

## 4. The abilities

Two phases. Phase A answers with counts and names no person. Phase B names people and waits for the product owner's decision on personal data (section 11). Every ability checks `wpcpm_manage_program`.

### 4.1 Phase A: counts, no personal data

| Ability | Input | Output | Reads | Personal data |
| --- | --- | --- | --- | --- |
| `wpcpm/program-counts` "Program counts" | none: the figures are the Administrator Dashboard's, for the current semester (one institution, and other semesters, are `wpcpm/institution-summary`'s) | per track the students in progress and signed up this semester, and the students finished this semester, as the Programs card counts them; active mentors; institutions per pipeline stage; sponsors per tier as the Sponsors card counts them, with how many of the approved are test sponsors; mentor applications open, and selected and waiting for the course; the outcome figures the homepage may show, with their counts; each source's read time | the Programs and Sponsors cards' readers, the engagement reader, the institutions index, the mentor application feed's counts, the published outcome figures | no |
| `wpcpm/waiting-for-decision` "What waits for an Administrator" | none | the attention strip's tiles as label, count and the screen to open (institution applications, Collaboration Agreements to review and overdue, semester reports to review and due for drafting, requests from institutions, mentor applications, the sponsor queues); per sync its last run, next run and whether it needs a look (true or false), never the error's text | `WPCPM_Administrators_Cards::counts()` and `health()`, the call the Overview screen makes, so Claude's number and the strip's cannot differ | no |
| `wpcpm/mentor-engagement-summary` "Mentor engagement" | optional date to count as of | the count per Engagement view, as `wp wpcredits mentor_engagement --summary` prints it | the engagement reader | no |
| `wpcpm/institution-summary` "One institution" | an institution: a record id, or three or more characters of its name (up to five matches); optional cohort (a semester, as the Institution Dashboard's filter names it) | per match: its name, pipeline stage, country, the Collaboration Agreement's state and dates, how many members it has (no member's name or address), its students per group and per cohort, the roster's read time | institutions index, roster index, agreement records | no: an institution is an organization, and its contacts are left out |

### 4.2 Phase B: people, read-only

The product owner's decision of 25 October 2026 (section 11, item 2): Phase B answers give people's record IDs, never their names. The table below is the design as first written; Phase B's own plan rewrites each Output to carry record IDs where it now names a person.

| Ability | Input | Output | Never returned | Personal data |
| --- | --- | --- | --- | --- |
| `wpcpm/find-student` "A student's tracks" | an email address, a WordPress.org username or profile address, or three or more characters of a name; optional institution | up to 10 people; per person the name, the WordPress.org username, whether a site account exists, and every Students row the person holds, one per track (the product owner's rule of 18 October 2026: many tracks per person, list them all): the status (track or state), institution, cohort, start and end, mentor's name, contribution team, hours, how many reports, the course kept while waiting to graduate; the roster's read time | the email address (an input may hold one, no answer echoes it), the accessibility answer, notes, survey answers, every other Airtable column | yes: a student's name, progress, institution and mentor |
| `wpcpm/mentor-students` "A mentor's students" | the mentor: a username, an email address or a WordPress.org profile address, matched as `wp wpcredits mentor` matches it | the mentor's name, whether active, their students as the Mentor Dashboard lists them (name, status, hours, start, the latest report's month), the dates of the last sign-in and the last visit and the Engagement view the mentor is in; read times | addresses, notes, call records, sign-in times finer than the date | yes: a mentor's and their students' names and progress |
| `wpcpm/mentor-applications` "Mentor applications" | the state: open (`new`, `held`, `info`, `second`; the default), selected (waiting for the course) or lapsed; how many, up to 25 | per application its reference, received date, state, days in the state, the applicant's name, WordPress.org username, country, languages, hours a week, contribution areas, the teacher answer, whether the address is confirmed, the duplicate and held flags as codes; the totals per state | the email address, the free-text answer, notes, the guard's signals, the consent evidence | yes: an applicant's name and answers. The form is not open on live yet, so live holds few or none until its launch. |

### 4.3 What it replaces

| Question | Today | With this design |
| --- | --- | --- |
| How many students, mentors, institutions, sponsors? | the dashboards, or Airtable | `wpcpm/program-counts` |
| What needs me today? | the Administrator Dashboard | `wpcpm/waiting-for-decision` |
| How engaged are the mentors? | `wp wpcredits mentor_engagement --summary` over SSH | `wpcpm/mentor-engagement-summary` |
| How is this university doing? | the Institutions screen, or Airtable | `wpcpm/institution-summary` |
| Which track is this student on? | the Students screen, or Airtable | `wpcpm/find-student` |
| What does this mentor's page list? | `wp wpcredits mentor <who>` over SSH | `wpcpm/mentor-students` |
| Which mentor applications wait? | the Mentors screen's Waiting for review tab | `wpcpm/mentor-applications` |

### 4.4 Not offered

Anything that writes (a note, a decision, an email, a sync run, a setting); an export or a list of everyone; files (signed agreements, report files); email addresses and phone numbers; free text; Airtable. Each would be a design of its own with the product owner's yes, and anything that writes would need its own answer to section 6 first.

## 5. Personal data

- **What leaves the site.** Every answer travels to Anthropic as part of the conversation and stays in the session's transcript on the Mac. A Phase A answer names no person. A Phase B answer points to students, mentors and applicants by record ID, with their progress, and names none of them (the product owner, 25 October 2026); an Administrator looks the name up on the site.
- **What keeps it small.** The fixed fields of section 4, the row caps, no addresses, no free text, the 40-an-hour ceiling on people lookups, and the log of who looked up whom.
- **Where an answer must not go.** Into memory files, the vault, a GitHub issue or comment, a document under `docs/`, a Slack message, or anything mirrored, unless the product owner says so for that one message.
- **The Docker copy holds live's people.** Its database is a copy of the live site, so a Phase B trial on the copy sends real people's data exactly as live would. Phase A is safe to try there. Phase B is tried on a copy with every name replaced first (the product owner, 25 October 2026).
- **The terms.** Settled 25 October 2026 (section 11): people's data goes to Claude without names, as record IDs.

## 6. Text people typed

- **Where it comes from.** Every field a person filled in: names, institution names, application answers, notes, sponsor descriptions, report text. The mentor, institution and sponsor forms are open to anyone while they are switched on.
- **Why it matters even read-only.** The server cannot write, so a sentence hidden in a name cannot make it write. But the Claude session that reads the answer may hold other tools (a shell, mail, Slack, GitHub), and the sentence could ask it to use them, or to look up many people and post them somewhere.
- **The defenses, in layers.** Free text never goes out. The short fields that do are cut to 120 characters, cleaned of control and zero-width characters, and returned in named JSON fields, never inside a sentence the server composes. Each output schema's description says its values are data typed by members of the public, not instructions. The row caps and ceilings bound how much an injected "list everyone" can pull. The log shows the pattern afterwards. The standing rule holds for anything learned from an answer: the session asks the product owner before any outward action.
- **The trial tests it** with a record on the copy whose name holds an instruction (section 9).

## 7. Credentials and revocation

- **One password per person and device.** Each allowed Administrator creates their own application password on their own profile (Users > Profile > Application Passwords), named "Claude" and the device, for example "Claude - laptop". Nobody creates one for someone else, and none is shared, typed into a chat, kept in a repository, the vault, a ticket or a message.
- **On the Mac.** `WP_MCP_AUTH` holds the base64 of `username:application password` on one line. Base64 is an encoding, not a lock: whoever reads the value holds the password. The person sets it where Claude Code reads its environment; kept in the macOS Keychain and exported by the shell profile, it is in no file at all. A Claude session never reads, prints or stores it.
- **Revoking.**

| Scope | How | Effect |
| --- | --- | --- |
| one password | Users > Profile > Application Passwords > Revoke; an Administrator can do it from another account's edit screen | the next call answers 401 |
| one person | take the account off the allowed list | the next call answers 403 |
| everyone | the switch off | the address answers 404 |
| everything | deactivate the MCP Adapter plugin | no MCP address on the site at all |

- **Someone who stops being an Administrator** loses `wpcpm_manage_program`, and the door refuses them before anyone revokes their password. Revoke it anyway: core keeps an application password until it is revoked.
- **Last use.** WordPress lists each password's last use and address on the profile; the log adds which ability was asked and for what.

## 8. Setup

### 8.1 On the site

1. WordPress 6.9 or later: live and the copy run 7.1.3.
2. MCP Adapter 0.7.1, installed as a plugin from the WordPress.org directory or from the release asset `mcp-adapter.zip` of WordPress/mcp-adapter v0.7.1, not bundled through Composer. Its updates are the product owner's yes, like any plugin's; 0.7.1 is the floor because of the session fix.
3. The plugin release that carries this design. Without the adapter active it registers nothing, and the Security tab says the adapter is missing.
4. Application passwords offered: live is served over HTTPS; the copy runs with `WP_ENVIRONMENT_TYPE` set to `local`, which WordPress accepts over plain http, and its `.htaccess` passes the `Authorization` header on to PHP. On live the first signed call shows whether the host passes the header (a 401 with a correct password means it does not).
5. The switch on, and the account on the allowed list.

### 8.2 On the Mac

- The installed wordpress-mcp plugin reads two values. `WP_MCP_URL` is the site's address followed by `/wp-json/mcp/wpcredits`: `http://localhost:8900/wp-json/mcp/wpcredits` for the copy, `https://wordpresseducation.org/wp-json/mcp/wpcredits` for live. Not the `/wp-json/mcp/v1` its README suggests (no such route) and not the default server (off). `WP_MCP_AUTH` is the base64 line of section 7.
- One site at a time: moving from the copy to live is a change of both values (the copy's password is not live's) and a restart of Claude Code.
- The plugin's three skills (content, administration, WooCommerce) describe tools this server does not have. Claude sees only the `wpcpm-` tools; the skills can be ignored or turned off.
- The first question to ask: "Which tools does the wordpress MCP server offer?" The answer should list exactly the abilities of the phase that is on, and nothing else.

### 8.3 Not chosen

- **The default server with public abilities.** Less to build, but it lists whatever core and other plugins mark public (decisions 2 and 4).
- **The adapter's stdio mode over SSH** (`wp mcp-adapter serve --server=wpcredits --user=<account>`). It needs no password at all, but only someone with SSH can use it, which is one person, and it skips the HTTP door the other Administrators would use. It could become the product owner's own route later; it changes nothing on the site.
- **claude.ai's web connectors.** They sign in through OAuth, which neither WordPress nor the adapter provides.

## 9. Trial on the Docker copy (http://localhost:8900)

The copy is closed to the outside, so the site cannot call out; Claude Code reaches it from the Mac. Phase A first; Phase B only after the product owner's decision of section 11.

| Step | What | Expected |
| --- | --- | --- |
| 1 | `bin/snapshot pre-mcp-trial` | a snapshot to come back to |
| 2 | copy `mcp-adapter.zip` 0.7.1 into `release/`, install it with `bin/wp plugin install`, activate | `/wp-json/mcp/mcp-adapter-default-server` exists (it answers, but not with 404) before the next step; this is the door the plugin closes |
| 3 | install the branch's build of the plugin, switch still off | `/wp-json/mcp/wpcredits` and the default server both answer 404 |
| 4 | the product owner creates "Claude - trial" on their own account on the copy, sets the two values, restarts Claude Code; the switch on, that account alone on the list | Claude lists the Phase A tools and nothing else |
| 5 | each Phase A question, compared with the screens | the numbers equal the Administrator Dashboard's strip, the Institutions screen and the Engagement tab, with the read times printed |
| 6 | refusals, through `curl` with the same header, run by the product owner | an account without the capability: 403; an allowed Administrator with a password not named "Claude": 403 on the MCP address; the Claude password on `/wp/v2/users`, `/wp/v2/plugins` and `/wp/v2/settings`: 403; switch off: 404; the account off the list: 403; a revoked password: 401 |
| 7 | the ceilings set low for the test, then put back | the call over the ceiling is refused with the reopening time and logged |
| 8 | the log | a row per call in step 5 to 7, refusals included, with no IP and no answer content; the retention job deletes only these rows |
| 9 | Two Factor, on an account it enforces | the Claude password passes without the second step (expected, section 1), and reaches nothing but the MCP address (the fence) |
| 10 | a test record whose name holds an instruction (Phase B, or Phase A through an institution's name) | the field arrives cut and as data; nothing in the session acts on it |
| 11 | the product owner revokes "Claude - trial" and restores the snapshot, or keeps the copy as it is for Phase B | the copy back to its state, or ready for the next phase |

## 10. Release, live and phases

- **Phase A** is one release: the category, the four abilities, the server, the default server off, the switch and allowed list, the fence, the ceilings, the log and its retention, the Security tab's section, a page in the Administrator guide. **Phase B** adds the three abilities that name people, after the product owner's decision.
- Each phase is planned on its own in `docs/plans/`, bumps the version in every place, gets a changelog line and a zip, and goes to the mirror. Its number is the next free one in the list of release numbers in flight when the plan is written; this design takes none.
- **Tests:** the permission matrix of step 6 as a suite; the fence on and off the MCP address; every output checked against its schema, with a walk that fails on an email address, a free-text field or a field the schema does not name; the ceilings; the log rows and their retention; the switch off answering 404 and registering nothing; the default server off.
- **Live, on the product owner's yes, in this order:** deploy the release through the release script with the switch off; install and activate MCP Adapter 0.7.1; check that `/wp-json/mcp/wpcredits` and the default server both answer 404; the product owner creates their own Claude password; the switch on with the product owner alone on the list; the product owner's first questions compared with the dashboards; other Administrators added on the product owner's word, one at a time.
- **Rollback:** before deactivating the plugin or installing a version below 1.123.30, revoke every application password whose name begins with Claude, or check that there is none, because without the plugin such a password opens every address its account can use and skips the second factor; then the switch off; then the adapter deactivated; then the previous zip.

## 11. The product owner's decisions

1. **Go, and how far.** Settled 24 October 2026: Phase A first; Phase B decided after it has run for a few weeks (section 1, item 7).
2. **People's data to Claude.** Settled 25 October 2026: yes, without names. A Phase B answer about people gives their record IDs, never a name, so Claude can count and point to a person and an Administrator looks the name up on the site. The exclusions of section 5 stand as written.
3. **Who gets access.** Settled 25 October 2026: every Administrator. Each makes their own password (section 7) and is added to the allowed list; Phase A started with the product owner alone.
4. **The trial's data.** Settled 25 October 2026: Phase B is tried on a copy with every name replaced first, so its answers have real shapes and no real names; the script that replaces them is part of Phase B's plan.
5. **The list of abilities.** Settled 24 October 2026: the seven of section 4, as written.
6. **The adapter on live.** Installing MCP Adapter 0.7.1, a plugin from outside the program, on wordpresseducation.org, and who answers for its updates.
7. **Where the credential lives on the Mac.** The Keychain with a shell export, or a Claude Code settings file.
8. **A student's latest report month in `wpcpm/mentor-students`.** Settled 29 October 2026: dropped. The site keeps no date for a report (a report is read live from the program's base when it is opened), so the key could only ever be empty; each student is given with the status, the hours the Mentor Report Card prints and the start.
9. **The name search of `wpcpm/find-student`.** Settled 29 October 2026: accepted for now. Part of a name can confirm a guess that someone is a student, and the lookups-of-people limit (40 an hour by default) bounds how often; allowing name searches only with an institution, or giving them a lower limit of their own, stays open for later.

## 12. The author's calls (for the product owner to check)

- A server of the plugin's own, with the adapter's default server turned off for the whole site.
- No ability is public, so none appears on the core REST route.
- The tool names start with `wpcpm-`, after the plugin's prefix.
- The fence recognizes a Claude password by its name beginning with "Claude".
- No answer carries an email address, a phone number, free text, notes, the accessibility answer or survey answers, even to an Administrator.
- Lists stop at 25 rows, people lookups at 10; typed text is cut at 120 characters.
- Ceilings of 120 calls an hour, 600 a day, and 40 an hour for people lookups, per account.
- The log lives in the existing audit post type under its own key, keeps the input and the record ids, and is deleted after the days of the Keep the log for setting, 90 by default.
- The switch, the allowed list, the ceilings and the retention sit on Settings > Security.
- A sync's trouble in `wpcpm/waiting-for-decision` goes out as whether it needs a look, never as its error's text.

## 13. Not in scope

- Any ability that writes, sends, deletes or runs a sync.
- Abilities for students, mentors, institutions or sponsors themselves: only Administrators connect.
- The core abilities and every other plugin's abilities.
- An OAuth sign-in for claude.ai's web connectors.
- Moving more data off Airtable: the abilities read what the site holds today, and grow as the site's records do.
