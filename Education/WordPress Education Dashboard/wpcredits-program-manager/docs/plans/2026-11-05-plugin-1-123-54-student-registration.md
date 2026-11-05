# Plugin 1.123.54: student registration on the site, implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A student registers for the WordPress Credits Program at /student-registration/ with exactly the Airtable "Student Registration Form"'s thirteen questions and its privacy consent, guarded as the site's other public forms are; the site answers an address that has an account or a registration already by email and writes nothing, and otherwise writes one row to Airtable's Students table with an empty Status, as the Airtable form does; a registration Airtable could not take waits on the site and is tried again every hour; Administrators read every registration on a Registrations tab of the Students screen; and nothing of it is public until a person switches it on.

**Architecture:** Five new classes beside the mentor application's. `WPCPM_Student_Registration` is the record (a private post type, its states, its answers and consent, retention). `WPCPM_Student_Airtable_Row` shapes the Students row by each column's type from the base's schema, finds rows by address and keeps the site's copy of the Languages table. `WPCPM_Student_Registration_Process::process()` is the one step every path runs (the submit, the hourly retry, **Write it now**, **Put back**): the account check, the site's own registrations, the Airtable lookup, the write and the one email its outcome sends, under a lock per address. `WPCPM_Student_Registration_Form` is the page, block, shortcode and submit path, on `WPCPM_Form_Guard` in the mentor form's order. `WPCPM_Student_Registrations_Tab` is the tab and its three presses. The Students columns join `WPCPM_Mentors_Sync::fields()` under `student_*` keys, where the students sync's columns already are.

**Tech Stack:** PHP 7.4+ WordPress plugin `wpcredits-program-manager` (prefix `WPCPM_`); the no-WordPress suites `bin/test-*.php` (run from the plugin root, each ends `ALL PASS` and exits 0) on the shared stand-in `bin/stubs/mentor-applications.php` and, for the lock, `bin/stubs/options-lock-wpdb.php`; the real `WPCPM_Airtable` client over a stubbed transport; the template registry under `includes/mail/templates/`; the Docker copy in `~/Projects/wpcredits-docker`; the release script `~/Projects/wpcredits-release/release.py`.

**Spec:** `docs/specs/2026-11-05-student-registration-design.md` (approved 5 November 2026, committed on main as d76d27e0). It is the binding authority: read all of it before any task. Sections 3 to 7 are what this plan builds, section 8's anti-goals are what its tests guard, section 10 is the suite this plan grows.

## Rulings

Where the design leaves a detail open, or the code does not match the design's words, the plan rules here, once; each task that builds a ruling names it.

1. **The Students columns live in `WPCPM_Mentors_Sync::fields()`** (design vs code). The design names `WPCPM_Students_Sync::fields()`, which does not exist: the one map is `WPCPM_Mentors_Sync::fields()`, and nine of the fourteen columns are already in it under `student_*` keys. The six missing ones are added there.
2. **The import's cleaning rules, not `clean_row()` whole** (design vs code). `WPCPM_Institution_Import::clean_row()` refuses every `example.test` address (its sample-list rule) and drops a failed optional answer silently, where this form must refuse it under its question. The form applies the same rules (no spreadsheet formula, no tab, line break or `|`, a name of 2 to 200 characters, a tutor of at most 120) through three of its private helpers made public unchanged: `text()`, `is_formula()`, `has_control()`.
3. **The lookup's formula is `formula_in( 'Email', array( $email ), true, true )`**, which writes `TRIM(LOWER({Email})) = '<address>'`: the same rows as the design's `LOWER(TRIM({Email}))`, quoted and encoded by the client (a plus sign stays a plus, an apostrophe is escaped).
4. **The languages are the site's own copy of the Languages table** (design vs code). `WPCPM_Mentor_Airtable_Row::link_names()` keeps names lowercased, so it cannot list them. The hourly job reads the Languages table (the one the Students table's `Languages` column links) into an option, record ID to name as the base spells it, once a day, or sooner after Airtable refused a create; the form lists it A to Z and the row resolves the ticked names through it. The public page never reads Airtable.
5. **The form is closed for now** while any of these holds: the switch is off; Airtable is not connected; no institution the site holds is at a stage under **Institutions offered**; the languages have not been read yet. The public reads one closed sentence; an Administrator reads why.
6. **Controls**: the institution and the preferred language are each one field to type and pick (`WPCPM_Dashboards::render_combo()`, the owner's rule for a long list); the field of study a select of its nine; the languages ticked boxes; the dates the browser's date field; no group headings, since the Airtable form has none and the design adds nothing.
7. **The institution posts its record ID** and is linked by the record ID the institutions index holds (the site's copy of the Institutions table, read every three hours); one the index no longer holds when the row is written is left out by its name and the rest is written.
8. **Any failure at step 3 or 4 leaves the registration waiting**: an error, a schema or table that cannot be read, a refusal. The hourly job tries again; a try that fails 72 hours or more after the registration was queued makes it **Could not be written**.
9. **A create whose answer was lost**: the site marks a create as sent before it sends it and clears the mark when Airtable answered with an HTTP status (it wrote nothing). While the mark stands, the next try's lookup takes exactly one row found by the address as the row its own create made: written, with that record, and the received email.
10. **`Status` is not sent at all**; Airtable stores it empty, as the Airtable form leaves it.
11. **Spam is what the guard files as spam**: the honeypot filled, or a dwell token never minted here or used twice. A fast token is a person sending again (the guard's rule) and goes on, marked `dwell-fast`.
12. **Past the site-wide ceiling** (`WPCPM_Form_Guard::PER_DAY`, 40 a day) a registration is neither refused nor spam: it waits, marked `site-ceiling`, and the hourly job writes at most 40 waiting registrations a run, oldest first. A cohort of sixty registering in a day is the form working. (Kept so by the product owner, 5 November 2026.)
13. **The per-actor ceiling is 30 an hour from one network address** for this form alone (the product owner, 5 November 2026: a class registering together on one campus network), `WPCPM_Student_Registration_Form::PER_HOUR = 30` passed to `WPCPM_Form_Guard::claim_actor( $key, self::PER_HOUR )`; the other forms keep the guard's five.
14. **A second registration from an address the site holds as waiting or written** is filed as a short `already` entry: its date, its consent and a link to the first, none of its answers.
15. **The emails' ceiling**: each email claims `WPCPM_Form_Guard::claim_mail()` under the form's own key (200 a day); past it, or refused by the mail layer, it is not sent and not tried again, and the registration says so.
16. **Retention**: spam after **Spam kept for (days)**, every other settled registration after **Rejected kept for (days)**, each counted from when it settled; a waiting registration is never deleted. Retention runs at the end of the hourly job.
17. **The tab**: the Students screen's tabs become Accounts, Registrations, Sync; it still opens on Accounts. Views **All** (every state but spam), **Waiting to be written**, **Could not be written** and **Spam**, each with its count, newest first, 50 a view.
18. **The page**: `/student-registration/`, titled "Student registration", starting with the introduction in three paragraphs (the program's address as a `mailto:` link), then the block with its `thanks` empty; the block's `thanks`, when set, replaces the default sentence.
19. **Lengths**: the name 2 to 200 characters and the tutor at most 120 (refused past them, the import's limits); the accessibility needs cut to 2,000, refused for markup as the mentor form's free text is.
20. **The privacy box** keeps the Airtable form's words with the address as a link to `WPCPM_Form_Guard::policy_url()`, and no second policy line under it; the consent evidence records the sentence as printed.
21. **Institutions offered may be empty**; the form is then closed. It is not one of `WPCPM_Settings::never_blank()`.
22. **The locks**: one per address, `wpcpm_sreg_lock_<first 32 of the address hash>`, 300 seconds; one for the hourly job, `wpcpm_sreg_lock_run`, 900 seconds. A submission whose address lock is held waits for the job.
23. **`Tutor email`'s type**: the design's table gives none, nor did the 9 September read. The fixture records `email`, and the row writes the address into an `email` or a single-line text column alike.
24. **The mail area** is a new one, **Student registrations** (`student-registrations`), after Mentor applications, with three emails to the address given (audience `applicant`).
25. **Write it now** tries once, at once, whatever the 72 hours; a failure leaves the registration Could not be written with the new reason, and the hourly job does not take it up. **Put back** restarts the 72 hours.
26. **The refusals' words** are the plan's, in the site's voice (Task 5); the design quotes none.
27. **A test institution is never offered**: an institution whose name, trimmed, starts with `TEST` in any case is left out of `institutions()` whatever its stage, so a shared test institution made for the Institutions module's testing never reaches the public form (the author's call after the design, added to its section 3.1).

## Global Constraints

- Release **1.123.54**: the plugin header `Version`, `WPCPM_VERSION`, `readme.txt` Stable tag, a `= 1.123.54 =` changelog entry above 1.123.53's, the translation template with `sh bin/make-pot.sh`, and the guides with `php bin/build-docs.php`. Base: main at the plan's commit, holding 1.123.53 (live). Branch `feature/student-registration`. If main moves past 1.123.53 before Task 7, take the next free number wherever this plan says 1.123.54.
- PHP 7.4 compatible, suites included: `array()` syntax, no `match`, no named arguments, no typed properties, no `?->`, no trailing comma in a parameter list or a closure's `use` list, order with `array_multisort()` and an ID tie-break rather than relying on `usort()` keeping equal rows in place; a suite that reaches a private or protected member through reflection calls `setAccessible( true )` first, under `if ( PHP_VERSION_ID < 80100 )` (`bin/test-tooling.php` reads for it).
- WordPress coding standards: tabs, Yoda conditions, spaces inside parentheses, strict `in_array()`, no short ternary. `bash bin/check-standards.sh` ends with the warning count Before Task 1 records and no ` ERROR ` line.
- Every handler checks the capability first, then the nonce keyed to the action and the row; every posted value goes through `wp_unslash()` and a cleaner; every printed value is escaped; every string with a placeholder has a translator comment.
- **No em dash and no en dash anywhere**: plan, code, comments, strings, docs, tests, commit messages. Plain hyphens.
- **US English** in every string a person reads (`php bin/check-spelling.php`); full product names (Student Report Card, Mentor Report Card); never "Education Hub"; "Administrators" for the people who run the program, in every string the plugin draws. The three emails keep the design's words, "a program manager", as the design fixes them.
- **No person's name** in the plan, code, comments, fixtures, docs, tests or commit messages: name roles (the product owner, a program manager, the mentor coordinator, an Administrator). No personal email address anywhere: tests and the trial use `example.test`; the program's public address `education@wordpressfoundation.org` stays where the design puts it. Invented Airtable IDs are the type's prefix and upper case letters and digits only (`recINSTITUTION001`, `tblSTUDENTS000001`); `php bin/test-fixtures.php` walks `bin/`, `docs/` and `includes/` for them, and no fixture holds an address of any kind.
- **Tests first in every task**, in the repository's style (`ck( $label, $got, $want )`, compared with `===`), each shown failing before its fix. **Airtable is always faked**: the suite loads the real `WPCPM_Airtable` and stands in the transport (`wp_remote_request()` and the three `wp_remote_retrieve_*()`), as `bin/test-airtable.php` does; nothing calls the network.
- **What the site writes to Airtable** in this release: one Students row per registration that is neither an account's nor an address's already registered, created and never changed or deleted afterwards. Nothing else is written, updated or deleted there.
- **Nothing on a public request reads Airtable** except the submit's steps 3 and 4 (design, section 4). The page's draw reads the site's copies alone.
- **Nothing is public on live until a person switches it on**: **Registrations from students** is off by default and stays off on live until the product owner's one test registration (Task 10).
- **Hands off**: Tasks 1 to 7 touch no network, no SSH, no Docker and no Airtable; Task 9 alone touches the Docker copy, inside a snapshot; Task 10's SSH, mirror and deploy steps each wait for the product owner's explicit yes. No stash, no push but the release script's.
- Commits: each message starts `Student registration:` and ends, after a blank line, with the Co-Authored-By trailer the implementer's session names for its model.

## Review Focus

1. **A create whose answer is lost** (Airtable stored the row, the request timed out): the registration waits; the hourly try finds the one row by the address, files it written with that record, sends "Registration received" once, and never creates a second row. Pinned in Task 4, step 1.
2. **The same address twice at once, or again while the first waits**: one Students row. A submission whose address lock is held waits for the job, which then files it as a short `already` entry under the first; a second submission after the first is waiting or written is filed so at once. Pinned in Task 4, step 1.
3. **An address typed with capitals, spaces around it, a plus sign or an apostrophe** (`  Ada.O'Brien+wp@Example.test `): the account check, the site's registrations and the Airtable lookup all find the same person; the formula holds `'ada.o\'brien+wp@example.test'` and the request carries `%2B`, never a space. Pinned in Task 4, step 1.
4. **A cohort past the day's site-wide ceiling**: the forty-first registration of the day is stored, waits with `site-ceiling`, reaches Airtable on the next hourly run, and its student saw the same thank-you; a run writes at most forty. Pinned in Task 5, step 1, and Task 4, step 1.
5. **The institution or a language gone between the page and the write**: a posted record ID the form no longer offers (its stage changed) is refused under its question with every other answer kept; an institution the index drops after the registration was stored is left out by its name, and the rest of the row is written and the left-out line shows on the tab. Pinned in Task 5, step 1, and Task 3, step 1.

---

## Files at a glance

| File | What it is responsible for | Task |
| --- | --- | --- |
| `includes/class-wpcpm-settings.php`, `includes/class-wpcpm-settings-screen.php` | The switch, **Institutions offered**, the Student registrations section | 1 |
| `includes/modules/class-wpcpm-mentors-sync.php` | Six `student_*` names in `fields()` | 1 |
| `bin/fixtures/students-registration-columns.json` (new) | The Students columns the row writes, as the live base held them on 5 November 2026 | 1 |
| `includes/modules/class-wpcpm-student-registration.php` (new) | The post type, states, answers, consent, outcome, locks' names, retention | 2 |
| `includes/modules/class-wpcpm-student-airtable-row.php` (new) | The row's cells by column type, the lookup by address, the create, the Languages copy | 3 |
| `includes/modules/class-wpcpm-student-registration-process.php` (new) | Steps 2 to 4, the three emails, the hourly job | 4 |
| `includes/mail/templates/student-registrations.php` (new), `includes/mail/class-wpcpm-mail-catalog.php`, `includes/mail/class-wpcpm-mail-templates.php` | The three emails, their area | 4 |
| `includes/modules/class-wpcpm-student-registration-form.php` (new), `blocks/student-registration/` (new) | The page, block, shortcode, questions, cleaning, draw, submit | 5 |
| `includes/modules/class-wpcpm-institution-import.php` | `text()`, `is_formula()`, `has_control()` made public | 5 |
| `includes/modules/class-wpcpm-student-registrations-tab.php` (new) | The Registrations tab and its three presses | 6 |
| `includes/modules/class-wpcpm-students.php` | Boot, activation, deactivation, uninstall; the third tab | 2, 4, 5, 6 |
| `wpcredits-program-manager.php`, `uninstall.php` | `require_once` of each new class after `class-wpcpm-student-feedback.php`; the lock prefix in the uninstaller's sweep | 2 to 6 |
| `bin/test-student-registration.php` (new) | The design's section 10, one section per task | 1 to 6 |
| `docs/sections/31-admin-settings.md`, `32-admin-tools.md`, `30-admin-wpadmin.md`, `34-admin-operations.md` | The Administrator guide | 1, 4, 6 |

## The checks every task ends with

Run from the plugin root, after the task's own suites pass:

```bash
for f in bin/test-*.php; do php "$f" >/dev/null 2>&1 || echo "FAIL $f"; done; echo battery done
php bin/check-references.php | tail -1
php bin/check-spelling.php | tail -1
php bin/check-dead-annotations.php | tail -1
php bin/check-temp-litter.php | tail -2
bash bin/check-standards.sh 2>&1 | tail -1
```

Expected: `battery done` with no `FAIL` line; references `- all resolve`; spelling `US English throughout.`; `0 dead`; temp litter as found; the standards line Before Task 1 records. A task that changes `docs/sections/` runs `php bin/build-docs.php` before the battery, since `bin/test-handbook.php` and `bin/test-settings.php` read the built guide.

---

## Before Task 1: the base, the branch and the plan

- [ ] **Step 1: The base.** `git -C ~/GitHub/wpcredits-program-manager log --oneline -3` shows the plan's commit above d76d27e0; `grep -n "^ \* Version:\|define( 'WPCPM_VERSION'" wpcredits-program-manager.php` and `grep -n "^Stable tag:" readme.txt` read 1.123.53. Anything else: stop and tell the product owner.
- [ ] **Step 2: The branch.** `git worktree add .worktrees/student-registration -b feature/student-registration main` (superpowers:using-git-worktrees). In it, run the checks above and `php bin/test-fixtures.php`; write their last lines and the suite count in the session's ledger.
- [ ] **Step 3: The plan names nobody and holds no refused word.**

```bash
grep -n -e $'\xe2\x80\x94' -e $'\xe2\x80\x93' docs/plans/2026-11-05-plugin-1-123-54-student-registration.md || echo clean
php -r 'require "bin/refused-strings.php"; var_dump( wpcpm_refused_found( file_get_contents( "docs/plans/2026-11-05-plugin-1-123-54-student-registration.md" ) ) );'
python3 -c 'import re,sys,tomllib; w=tomllib.load(open(sys.argv[1],"rb"))["scan"]["private_words"]; t=open(sys.argv[2],encoding="utf-8").read(); print(sum(len(re.findall(p,t,re.I)) for p in w))' ~/Projects/wpcredits-release/config.toml docs/plans/2026-11-05-plugin-1-123-54-student-registration.md
```

Expected: `clean`, `array(0) {}`, `0`. The third prints a count, never a word of the list.

---

### Task 1: The settings, the Students columns and their fixture

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (`defaults()`; `scopes()['people']`; the guarded flags loop of the save; the list loop beside `institution_active_stages`)
- Modify: `includes/class-wpcpm-settings-screen.php` (`render_tab_people()` calls a new `render_student_registration_settings()` after Landing pages and before `render_mentor_application_settings()`)
- Modify: `includes/modules/class-wpcpm-mentors-sync.php` (`fields()`: six keys after `student_import_key`)
- Create: `bin/fixtures/students-registration-columns.json`
- Create: `bin/test-student-registration.php` (its frame and its first section)
- Modify: `docs/sections/31-admin-settings.md`, then `php bin/build-docs.php`
- Test: `bin/test-student-registration.php`, `bin/test-settings.php`; run `bin/test-students-sync.php` (holds every `student_*` name to `students-table-fields.json`) and `bin/test-fixtures.php`

**Interfaces:**
- Produces the settings `student_registrations_enabled` (bool, default `false`) and `student_registration_stages` (string[], default `array( 'Confirmed' )`), both in `scope_keys( 'people' )` after `mentor_lapse_days`; the flag joins the guarded flags loop, the list joins the `clean_list()` loop with `past_statuses` and `institution_active_stages` (an all-unticked post, `array( '' )`, saves `array()`).
- Produces in `WPCPM_Mentors_Sync::fields()`: `'student_tutor_email' => 'Tutor email'`, `'student_hours' => 'Total hours'`, `'student_languages' => 'Languages'`, `'student_preferred_language' => 'Prefered language'` (one r, as the table spells it), `'student_english' => 'English and proactivity acceptance'`, `'student_privacy' => 'Privacy Policy Compliance'`. The nine it already holds and the row reads: `student_record_name`, `student_email`, `student_institution`, `student_study`, `student_tutor` (`'Tutor '`, with its space), `student_start`, `student_end`, `student_access`, `student_status`.
- Produces the fixture: `{ "_comment": ..., "students_table": "tbla8GZg5x6NY7aWt", "read": "2026-11-05", "columns": { name: type }, "choices": { "Your field of study": [nine] } }` with fifteen columns: Full Name `singleLineText`, Email `email`, Educational Institutions `multipleRecordLinks`, Your field of study `singleSelect`, `Tutor ` `singleLineText`, Tutor email `email` (Ruling 23, said in the comment), Start Date `date`, End Date `date`, Total hours `number`, Languages `multipleRecordLinks`, Prefered language `multipleRecordLinks`, Accessibility needs `multilineText`, English and proactivity acceptance `checkbox`, Privacy Policy Compliance `checkbox`, Status `singleSelect`. The comment says the types are the design's table's (read from the live base on 5 November 2026), the rest the 7 September read's, and that `Tutor ` and `Prefered language` are spelled so on purpose.
- Produces the section anchor `student-registrations`, heading "Student registrations", intro "Whether students can register through the form on this site, and which institutions the form offers."; it reads `WPCPM_Student_Registration_Form::page_url()` under `class_exists()`, as the mentor section does.

- [ ] **Step 1: Write the failing tests.**
  - `bin/test-student-registration.php`'s frame: `require_once __DIR__ . '/stubs/mentor-applications.php';`, the `ck()` and the `ALL PASS` ending of `bin/test-mentor-application-form.php`, and a `WPCPM_Settings` stand-in (`get()`, `get_value()`, `is_connected()`, `airtable_record_url()` copied from the real one) answering `$GLOBALS['settings']` over `students_table` `tblSTUDENTS000001`, `base_id` `appTESTBASE000001`, `api_token` `patTESTNOTAREALTOKEN`, `student_registrations_enabled` true, `student_registration_stages` `array( 'Confirmed' )`, `application_spam_days` 30, `application_rejected_days` 365, `mail_contact` `programs@example.test`. Later tasks add their `require_once` lines and stand-ins to this frame.
  - Section "The columns": every name the row will write, `fields()[ $key ]` for the fifteen keys above, is a key of the fixture's `columns`, `'Tutor '` with its space and `'Prefered language'` among them; the fixture's nine choices equal `WPCPM_Institution_Student_Form::choices( 'field_of_study' )`, in order.
  - In `bin/test-settings.php`: the two defaults; both in the `people` scope and in no other; a `people` save without the box stores `false`, a patch without it leaves it as it was; `student_registration_stages` posted as `array( '', 'Confirmed', 'Student' )` stores `array( 'Confirmed', 'Student' )` and as `array( '' )` stores `array()`, and an empty list is not put back (Ruling 21); the people tab draws the section with **Registrations from students** ("Take student registrations through the form on this site", "A public page anybody can post to.", the page line), and **Institutions offered** as a checklist of `WPCPM_Settings_Choices::institution_stages()` with Confirmed ticked; `$design_sections['people']` gains `'Student registrations' => 'student-registrations'` before `'Mentor applications'`.
- [ ] **Step 2: Run them red.** `php bin/test-student-registration.php; php bin/test-settings.php`: the fixture is missing and the settings unknown.
- [ ] **Step 3: Implement.** The defaults' comment says why off: on means accepting writes into the shared base from anybody on the internet. The switch row's fold: "Each registration is written to the Airtable Students table with an empty Status, as the Airtable form writes it, unless the address belongs to an account on this site or is registered already; the Registrations tab of the Students screen lists every one. While this is off the page shows one sentence saying registrations are closed, and an Administrator a second saying why." then `policy_note()`. **Institutions offered**: help "The Current Stage of the institutions the form lists; an institution at another stage is not offered.", fold "With none ticked, or no institution at a ticked stage, the form says registrations are closed. A new partner appears after the next institutions sync, every three hours.", the typed fallback as the pipeline stages row has it (`WPCPM_Settings_Rows::typed( 'stages', $why )`).
- [ ] **Step 4: Docs.** Guide 31, The Students and mentors tab: "Six sections", **Student registrations** in the list before **Mentor applications**, and a `#### Student registrations` part before `#### Mentor applications` saying the form's address, what the switch does (off by default, the closed sentence, the Registrations tab, nothing written to Airtable for an address with an account or a registration), **Institutions offered** (Confirmed alone by default, may be empty, closing the form) and that the consent box links the WordPress.org privacy policy. `php bin/build-docs.php`, twice; the second changes nothing.
- [ ] **Step 5: Run green, then the checks; commit.** `git commit -m "Student registration: the switch, the institutions offered and the Students columns the row writes"`.

### Task 2: The registration record

**Files:**
- Create: `includes/modules/class-wpcpm-student-registration.php`
- Modify: `includes/modules/class-wpcpm-students.php` (`boot()`: `WPCPM_Student_Registration::init();`; `uninstall()`: `WPCPM_Student_Registration::delete_all();`)
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (the `require_once`; the sweep's prefix list gains `WPCPM_Student_Registration::LOCK_PREFIX`)
- Test: `bin/test-student-registration.php`, `bin/test-uninstall.php`; run `bin/test-roles.php` (measures every `POST_TYPE`)

**Interfaces:**
- Consumes: `WPCPM_Settings::get_value( 'application_spam_days' )`, `get_value( 'application_rejected_days' )`.
- Produces `WPCPM_Student_Registration`:
  - `POST_TYPE = 'wpcpm_stu_reg'`; states `STATE_WAITING = 'waiting'`, `STATE_WRITTEN = 'written'`, `STATE_ACCOUNT = 'account'`, `STATE_ALREADY = 'already'`, `STATE_FAILED = 'failed'`, `STATE_SPAM = 'spam'`; `states(): string[]` in that order.
  - Post meta `META_FIELDS = '_wpcpm_sreg_fields'`, `META_STATE = '_wpcpm_sreg_state'`, `META_EMAIL = '_wpcpm_sreg_email'` (the hash), `META_CONSENT = '_wpcpm_sreg_consent'`, `META_SIGNALS = '_wpcpm_sreg_signals'`, `META_OUTCOME = '_wpcpm_sreg_outcome'` (`record`, `records`, `user`, `left_out`, `error`, `tries`, `tried_at`, `create_sent`, `mail`), `META_QUEUED = '_wpcpm_sreg_queued'` (when the 72 hours run from), `META_SETTLED = '_wpcpm_sreg_settled'`, `META_FIRST = '_wpcpm_sreg_first'`.
  - `LOCK_PREFIX = 'wpcpm_sreg_lock_'`, `LOCK_TTL = 300`, `WAIT_HOURS = 72`.
  - `init()` (the post type on `init`), `register_post_type()`: private, no UI, no REST, no query var, a capability type no role holds (`array( 'wpcpm_stu_reg', 'wpcpm_stu_regs' )`), as the mentor application's.
  - `store( array $fields, $state, array $signals, array $consent, $first = 0 ): int` (0 when WordPress refuses): `waiting`, `spam` or `already` (anything else is `waiting`); private, author 0, title the name or "Registration with no name"; the answers and consent written as a slashed copy; `META_QUEUED` now; `META_SETTLED` now for `spam` and `already`; an `already` entry stores `META_FIRST` and no answers (Ruling 14).
  - `registration( $post_id ): ?WP_Post` (the type checked); `state_of()`, `fields_of()`, `name_of()`, `email_of()`, `consent_of()`, `signals_of()`, `outcome_of()` (every key present, defaults `''`, `array()`, 0), each taking `WP_Post`.
  - `email_hash( $email ): string`: `wp_hash( mb_strtolower( trim( $email ) ) )`, `''` for none.
  - `lock_name( $email ): string`: `LOCK_PREFIX . substr( email_hash( $email ), 0, 32 )`; `RUN_LOCK` is the process's (Task 4) under the same prefix.
  - `held_for( $hash, $exclude_id ): ?WP_Post`: the oldest registration other than `$exclude_id` with that hash that is `written`, or `waiting` with a lower ID.
  - `set_state( $post_id, $state, array $outcome = array() ): bool`: the state, the outcome merged, `META_SETTLED` now for any state but `waiting`; false for a state that is not one.
  - `note_try( $post_id, $error, $now ): void`: `tries` + 1, `tried_at`, `error`.
  - `requeue( $post_id ): bool`: a spam registration to `waiting`, `META_QUEUED` now, `META_SETTLED` and the outcome cleared.
  - `in_states( array $states, $limit = 0, $order = 'DESC' ): WP_Post[]` (by date, then ID), `count_in( array $states ): int`.
  - `purge( $now = 0 ): int` (Ruling 16), `forget_cached( $post_id )`, `delete_all()` (every post of the type).

- [ ] **Step 1: Write the failing tests** (section "The record"):
  - `store()` of a waiting registration: state, answers read back whole with a typed backslash kept, consent, signals, `META_EMAIL` equal to `email_hash( '  Ada@Example.TEST ' )` and to `email_hash( 'ada@example.test' )`, private, author 0; an `already` entry: no `META_FIELDS`, `META_FIRST` set, settled.
  - `held_for()`: finds a written one; finds an older waiting one; passes over a newer waiting one, and `failed`, `spam`, `account`, `already` alike; never the one excluded.
  - `set_state()`, `note_try()` (two tries count 2, the last error kept), `requeue()` (spam to waiting, settled cleared).
  - `purge()` with the defaults: spam settled 31 days ago gone, 29 kept; written, account, already and failed settled 366 days ago gone, 364 kept; waiting queued 400 days ago kept; with both settings 0, nothing.
  - `bin/test-uninstall.php`: a seeded registration and its meta, and an option `wpcpm_sreg_lock_x`, are gone after the uninstall.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement** as the interfaces say, in `WPCPM_Mentor_Application`'s shape (repeating no history: the outcome is one array, replaced as it changes). The class docblock says the record holds a stranger's answers and is reached only through the Students screen.
- [ ] **Step 4: Run green, then the checks; commit.** `"Student registration: the record a registration is kept in, and how long"`.

### Task 3: The Students row

**Files:**
- Create: `includes/modules/class-wpcpm-student-airtable-row.php`
- Modify: `wpcredits-program-manager.php`, `uninstall.php` (`require_once`), `includes/modules/class-wpcpm-students.php` (`uninstall()` deletes `LANGUAGES_OPTION`)
- Test: `bin/test-student-registration.php`, `bin/test-uninstall.php`

**Interfaces:**
- Consumes: `WPCPM_Mentors_Sync::fields()` (Task 1); `WPCPM_Airtable::cached_schema()`, `table_in()`, `fetch_page()`, `fetch_all()`, `formula_in()`, `create_records()`, `is_record_id()`, `flatten()`; `WPCPM_Institutions_Index::rows()`; `WPCPM_Typed_Text::mail_text()`; `WPCPM_Settings::get_value( 'students_table' )`.
- Produces `WPCPM_Student_Airtable_Row`:
  - `ANSWERS`: answer key to `fields()` key, in the row's order: `name` `student_record_name`, `email` `student_email`, `institution` `student_institution`, `study` `student_study`, `tutor` `student_tutor`, `tutor_email` `student_tutor_email`, `start` `student_start`, `end` `student_end`, `hours` `student_hours`, `languages` `student_languages`, `preferred` `student_preferred_language`, `access` `student_access`, `english` `student_english`, `privacy` `student_privacy`. No `student_status` (Ruling 10).
  - `MODES`: answer to column type to mode. `name`, `tutor`: `singleLineText`, `multilineText` to `text`; `email`, `tutor_email`: `email`, `singleLineText` to `text`; `institution`: `multipleRecordLinks` to `institution`; `study`: `singleSelect` to `choice`; `start`, `end`: `date` to `date`; `hours`: `number` to `number`; `languages`, `preferred`: `multipleRecordLinks` to `languages`; `access`: `multilineText`, `singleLineText`, `richText` to `text`; `english`, `privacy`: `checkbox` to `box`.
  - `cells_for( array $answers, array $table, array $links ): array` with `cells` (column name to value) and `left_out` (column name to `why` and `values`). `$answers`: the keys of `ANSWERS` plus `institution_name`; `$table`: the Students table as `fetch_schema()` gives it; `$links`: `institutions` and `languages`, each record ID to name. `text` sends `mail_text()` of the stored answer (as typed, never `&amp;` or `&lt;`); `date` the `Y-m-d`; `number` a float; `box` `true`; `choice` the choice as the column spells it, matched trimmed and in any case; `institution` `array( <record ID> )` while `$links['institutions']` holds it, else left out `unmatched` with the institution's name (Ruling 7); `languages` the record IDs of the names found in `$links['languages']`, in the answer's order, the rest left out `unmatched` by name. A column the table lacks: `no-column`; another type: `type` with the type. An empty answer sends nothing and is not left out.
  - `left_out_sentence( array $left_out ): string`, `''` for none: `%s: the Students table has no such column.`; `%1$s: the column is a %2$s, which the site does not write.`; `%1$s: not in the base: %2$s.` (names joined with `, `), joined by a space.
  - `students_table( WPCPM_Airtable $airtable ): array|WP_Error`: `cached_schema()` then `table_in()` of `students_table`; any error as it came.
  - `find_by_email( $email, WPCPM_Airtable $airtable ): string[]|WP_Error`: `fetch_page( students_table, array( 'formula' => $airtable->formula_in( fields()['student_email'], array( $email ), true, true ), 'fields' => array( fields()['student_email'] ) ) )`, the record IDs found (Ruling 3); an error for an empty address.
  - `create( array $cells, WPCPM_Airtable $airtable ): string|WP_Error`: `create_records( students_table, array( array( 'fields' => $cells ) ) )`, the first ID `is_record_id()` accepts, or the error "Airtable did not answer with a record ID."; any error marks the languages copy stale.
  - `LANGUAGES_OPTION = 'wpcpm_student_languages'` (`read`, `table`, `names`, `stale`), not autoloaded; `languages(): array` record ID to name, A to Z, case-insensitive; `refresh_languages( WPCPM_Airtable $airtable, $now = 0 ): bool|WP_Error`: reads only when the copy is empty, a day old or stale, from the table the Students table's `Languages` column links (`options.linkedTableId`) and its `primary`, with `fetch_all( $linked, array( 'fields' => array( $primary ) ) )`, passing over a row with no name or no record ID and a name held twice; a failed read keeps the old copy and answers the error; true when it read.
  - `institution_links(): array`: every row of `WPCPM_Institutions_Index::rows()`, record ID to its name trimmed.

- [ ] **Step 1: Write the failing tests** (section "The row"). The frame gains `students_table_spec( array $changes = array() )`, the Students table `tblSTUDENTS000001` as `fetch_schema()` gives one table (`name`, `primary` `Full Name`, `columns`), with the fixture's fifteen columns (Your field of study with the nine choices; Languages and Prefered language linking `tblLANGUAGES00001`, primary `Name`), each entry of `$changes` replacing a column or dropping it when null; the transport's schema answer (`{"tables": [...]}`) is built from it and the Languages table; `links()`, institutions `recINSTITUTION001` Example University, `recINSTITUTION002` Sample College, languages `recLANGENGLISH001` English, `recLANGPOLISH0001` Polish, `recLANGSPANISH001` Spanish; `answers( array $more = array() )`, the answers as the form stores them:

```php
$row = WPCPM_Student_Airtable_Row::cells_for( answers(), students_table_spec(), links() );
ck( 'the row a registration writes, column by column: the answers by each column\'s type, Tutor with its space, the two boxes ticked',
	$row['cells'],
	array(
		'Full Name'                          => 'Ada Example',
		'Email'                              => 'ada@example.test',
		'Educational Institutions'           => array( 'recINSTITUTION001' ),
		'Your field of study'                => 'Technology & Engineering',
		'Tutor '                             => 'Tutor Example',
		'Tutor email'                        => 'tutor@example.test',
		'Start Date'                         => '2026-12-01',
		'End Date'                           => '2027-03-31',
		'Total hours'                        => 150.5,
		'Languages'                          => array( 'recLANGPOLISH0001', 'recLANGENGLISH001' ),
		'Prefered language'                  => array( 'recLANGENGLISH001' ),
		'Accessibility needs'                => 'Large text & a screen reader, "please" < 3 steps.',
		'English and proactivity acceptance' => true,
		'Privacy Policy Compliance'          => true,
	) );
ck( 'and Status is not sent, so Airtable keeps it empty', array_key_exists( 'Status', $row['cells'] ), false );
ck( 'and nothing is left out', $row['left_out'], array() );
```

  where `answers()` stores `access` as the form's cleaner leaves it, `Large text &amp; a screen reader, "please" &lt; 3 steps.`, `languages` `array( 'Polish', 'English' )`, `preferred` `English`, `hours` `'150.5'`, `study` `technology & engineering` (matched to the base's spelling).
  - Left out by name, the rest written: a language the copy lacks (`Klingon`); `recINSTITUTION001` once `links()` no longer holds it, named "Example University" (**Review Focus 5**); a choice the column lacks; a column dropped (`no-column`); `Total hours` as a `rating` (`type`); and `left_out_sentence()` of each, word for word.
  - `Tutor email` as a `singleLineText` column takes the address too (Ruling 23); an empty tutor, tutor address and accessibility answer send nothing and are not left out.
  - The transport stand-in (`wp_remote_request()` routing on method and URL to `$GLOBALS['airtable']`, keeping every request in `$GLOBALS['sent']`, with `wp_remote_retrieve_response_code()`, `wp_remote_retrieve_body()` and `wp_remote_retrieve_header()` as `bin/test-airtable.php` has them; `WPCPM_Airtable::for_tests()` with a clock the sleeper moves, as that suite's, so the client's pacing never really sleeps): `find_by_email( '  Ada.O\'Brien+wp@Example.test ' )` sends one GET whose decoded `filterByFormula` is `TRIM(LOWER({Email})) = 'ada.o\'brien+wp@example.test'` and whose URL holds `%2B` (**Review Focus 3**), and answers the IDs; `create()` answers `recSTUDENTROW0001` and sends one POST with no `typecast`; a 422 answers the error and marks the copy stale.
  - `refresh_languages()`: reads the linked table into the copy, A to Z; within the day does not read again; when stale reads again; a failed read keeps the old copy and answers the error.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement** as the interfaces say. The class docblock says the row is created once and is Airtable's afterwards.
- [ ] **Step 4: Run green, then the checks; commit.** `"Student registration: the Students row, shaped by each column's type, and the copy of the Languages table"`.

### Task 4: The steps after the guard, the three emails and the hourly retry

**Files:**
- Create: `includes/modules/class-wpcpm-student-registration-process.php`, `includes/mail/templates/student-registrations.php`
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (`MODULE_STUDENT_REGS = 'student-registrations'`, labeled "Student registrations", after Mentor applications in `modules()`; the three ids), `includes/mail/class-wpcpm-mail-templates.php` (`AREAS` gains `MODULE_STUDENT_REGS => 'student-registrations'`)
- Modify: `includes/modules/class-wpcpm-students.php` (`boot()`: `WPCPM_Student_Registration_Process::init();`; `activate()`: `schedule()`; `deactivate()` and `uninstall()`: `unschedule()`), `wpcredits-program-manager.php`, `uninstall.php`
- Modify: `docs/sections/32-admin-tools.md` (the Emails part: the count of areas in "There are six:", set to the number `WPCPM_Mail_Templates::AREAS` holds after this task, eight; a **Student registrations** (since 1.123.54) item after **Mentor applications**, naming the three emails, that each goes to the address given, with the program's Reply-To when **Contact address in emails** is set; the Log's list of areas gains Student registrations after Mentor applications), then `php bin/build-docs.php`
- Test: `bin/test-student-registration.php`, `bin/test-mail-catalog.php`, `bin/test-mail-templates.php`, `bin/test-uninstall.php`; run `bin/test-emails-screen.php`, `bin/test-emails-templates.php`, `bin/test-handbook.php`

**Interfaces:**
- Consumes: Tasks 2 and 3; `WPCPM_Lock::take()`, `release()`; `WPCPM_Form_Guard::claim_mail()`, `MAIL_PER_DAY`, `PER_DAY`; `WPCPM_Mail::send_to()`, `site_name()`; `WPCPM_Mail_Templates::mail()`, `program_contact()`; `WPCPM_Settings::is_connected()`; `get_user_by( 'email', ... )`; `wp_login_url()`, `wp_lostpassword_url()`.
- Produces `WPCPM_Student_Registration_Process`:
  - `CRON_HOURLY = 'wpcpm_student_registrations_hourly'`, `RUN_LOCK = 'wpcpm_sreg_lock_run'`, `RUN_TTL = 900`, `BATCH = 40`, `MAIL_KEY = 'student-register-mail'`; `MAIL_RECEIVED = 'student-registration-received'`, `MAIL_ACCOUNT = 'student-registration-account'`, `MAIL_ALREADY = 'student-registration-already'`; signals `SIGNAL_SITE_CEILING = 'site-ceiling'`, `SIGNAL_MAIL_CEILING = 'mail-ceiling'`, `SIGNAL_MAIL_FAILED = 'mail-failed'`.
  - `init()`: `add_action( CRON_HOURLY, run_hourly )`, `add_action( 'init', schedule, 20 )`; `schedule()` (hourly, five minutes after the first load, when missing); `unschedule()`.
  - `process( $post_id, $airtable = null, $now = 0 ): string`, one of `written`, `account`, `already`, `waiting`, `failed`, `busy`, `skipped`. Only a `waiting` or `failed` registration is taken (else `skipped`); under `lock_name( email )` (`''` from `take()` is `busy`, nothing read or written); the meta read again under the lock; then, in order:
    1. An account whose email is the address trimmed and lowercased (any role): `account`, with its ID; the account email. No Airtable request.
    2. `held_for( hash, id )`: `already`, `META_FIRST` its ID, the answers deleted (Ruling 14); the already email. No Airtable request.
    3. Not connected: a failed try, "Airtable is not connected.". `find_by_email()`: an error is a failed try; rows found: with `create_sent` set and exactly one row, `written` with it (Ruling 9) and the received email; otherwise `already`, `records` the IDs, the already email.
    4. `students_table()` (an error is a failed try), `cells_for()` with `institution_links()` and `languages()`, `privacy` true; `create_sent` set to now; `create()`. An error whose data holds a `status` clears `create_sent`; any error is a failed try. Written: `written`, `record`, `left_out`, `create_sent` cleared; the received email.
    A failed try is `note_try()`, then `failed` when the registration was `failed` already or `$now - META_QUEUED` is 72 hours or more (Ruling 8), else `waiting`. The lock goes in `finally`.
  - Each email: `claim_mail( MAIL_KEY, MAIL_PER_DAY )` first; `send_to()` with `WPCPM_Mail_Templates::mail( $id, $values, array( 'headers' => <Reply-To: program_contact(), when set> ) )`; the outcome's `mail` is `sent`, `ceiling` (signal `mail-ceiling`) or `failed` (signal `mail-failed`); never sent again (Ruling 15). Values: `student_name`, `institution_name` (the answers'), `site_name`, `login_link` (`wp_login_url()`), `lost_password_link` (`wp_lostpassword_url()`).
  - `run_hourly( $now = 0, $airtable = null ): array` with `tried`, `written`, `settled`, `waiting`, `failed`, `busy` (bool, the run lock held): under `RUN_LOCK`, `refresh_languages()` (its error recorded, the run goes on), then `process()` on the waiting registrations oldest first, at most `BATCH` (Ruling 12), then `WPCPM_Student_Registration::purge( $now )`.
- Produces the three templates (placeholders: `student_name` body only; `institution_name`; `site_name`; `login_link` and `lost_password_link` required in the account email) and the catalog's three: "Student registration received, to the student", "Student registration from an address with an account", "Student registration from an address already registered", each in `student-registrations` for `TYPE_APPLICANT`.

- [ ] **Step 1: Write the failing tests** (section "What happens to a registration"). The frame gains a `WPCPM_Mail` stand-in (`send_to()` keeping what a builder returns, to whom and under which id; `site_name()` "WordPress Credits"), `wp_lostpassword_url()`, the real `WPCPM_Lock` over `bin/stubs/options-lock-wpdb.php`, and `airtable_down()` (the transport answers a `WP_Error` `http_request_failed` with no status) and `airtable_refuses( $code )`.
  - **The account path**: an account with `Ada@Example.test`, as a student, a mentor and an Administrator in turn: `account`, its user ID, no request in `$GLOBALS['sent']`, one email `student-registration-account` to the address as typed.
  - **The existing registration**: a written registration for the address, then another: the second `already`, `META_FIRST` the first, no answers kept, the already email, no request.
  - **The Students row that holds the address**: the search answers `recEXISTINGROW001`: `already`, `records`, the already email, no POST.
  - **The new row**: one POST whose `fields` are Task 3's cells for the same answers; `written`, `record` `recSTUDENTROW0001`; one email, the received one, whose body holds "for Example University"; and nothing else: no PATCH, no DELETE, no `invite-` email, no email to an Administrator or to anybody but the address given, no account made (the users unchanged) (design, sections 6 and 8).
  - **Airtable unreachable**: at the search, `waiting`, `tries` 1, the error kept, no email; at the create, `waiting` with `create_sent` kept; a 422 at the create, `waiting` with `create_sent` cleared.
  - **The 72 hours**: queued 71 hours ago and down, `waiting`; 73 hours ago, `failed`.
  - **Review Focus 1**: the create sent and down; the next `process()` finds one row: `written` with that row, the received email once, one POST in all.
  - **Review Focus 2**: the address lock planted as a row of `$GLOBALS['opts']`: `busy`, still `waiting`, no request; lock gone, two waiting registrations for one address: `run_hourly()` writes the older and files the newer `already` under it, one POST.
  - **Review Focus 3**: an account with `ada.o'brien+wp@example.test` answers `  Ada.O'Brien+wp@Example.test `; a written registration with the address in other capitals answers it too.
  - **Review Focus 4**: 45 waiting registrations at 45 addresses: one `run_hourly()` tries 40, oldest first; the next tries 5.
  - **The hourly run again**: an account made since the registration waited: `account`, no POST; the run lock held: `busy` true, nothing tried; the languages read when absent and not again within the day; `purge()` run at its end.
  - **The mail ceiling** spent: no email, `mail-ceiling`, the outcome still `written`.
  - **The three emails, word for word** (`WPCPM_Mail_Templates::all()`): subjects "Registration received - WordPress Credits Program", "You already have an account - WordPress Credits Program", "You are already registered - WordPress Credits Program"; bodies the design's section 5, each paragraph joined by a blank line:

```php
ck( 'the received email\'s body is the design\'s', $all['student-registration-received']['body'], implode( "\n\n", array(
	'Hi {student_name},',
	'Thank you for registering for the WordPress Credits Program. We have received your registration for {institution_name}.',
	'A program manager will be in touch by email with the next steps, including your mentor and your course.',
	'If you have any questions in the meantime, reply to this email.',
) ) );
ck( 'the account email\'s body is the design\'s', $all['student-registration-account']['body'], implode( "\n\n", array(
	'Hi,',
	'Someone, probably you, just registered for the WordPress Credits Program with this address. You already have an account on {site_name}, so there is nothing more to fill in: sign in here.',
	'{login_link}',
	'If you do not remember your password, set a new one here: {lost_password_link}',
	'If you would like to join another course, reply to this email and a program manager will help.',
	'If this was not you, you can ignore this email.',
) ) );
ck( 'the already email\'s body is the design\'s', $all['student-registration-already']['body'], implode( "\n\n", array(
	'Hi,',
	'Someone, probably you, just registered for the WordPress Credits Program with this address. We already have your registration, so there is nothing more to fill in. A program manager will be in touch by email with the next steps.',
	'If you have any questions, reply to this email. If this was not you, you can ignore this email.',
) ) );
```

  - Each email carries `Reply-To: programs@example.test`; with no contact set, none.
  - `bin/test-mail-catalog.php`: the area's three emails with their labels and audience; `modules()` reads `... 'Mentor applications', 'Student registrations', 'WordPress', ...`. `bin/test-mail-templates.php` holds the new area by its existing walks. `bin/test-uninstall.php`: `CRON_HOURLY` is off the clock and `wpcpm_student_languages` gone.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** Triggers: "When a student registers through the form on the site and the registration is written to Airtable: to the address the registration gives."; "When somebody registers through the student registration form with the address of an account on this site: to that address."; "When somebody registers through the student registration form with an address already registered: to that address." The file's docblock says the three are the design's words and that the name goes in a body alone.
- [ ] **Step 4: Docs, then run green and the checks; commit.** Guide 32 as the Files list says; `php bin/build-docs.php`, twice. `"Student registration: the account check, the registrations already held, the Students row, the three emails and the hourly retry"`.

### Task 5: The form

**Files:**
- Create: `includes/modules/class-wpcpm-student-registration-form.php`; `blocks/student-registration/block.json`, `editor.js`, `editor.asset.php` (the mentor block's three, renamed: name `wpcpm/student-registration`, title "Student Registration Form", description "The public form a student fills in to register for the WordPress Credits Program.", keywords student, registration, register, wpcredits, the attributes `title` and `thanks`, the editor's help "Shown to a student once the form is sent. Leave blank for the plugin's own sentence.")
- Modify: `includes/modules/class-wpcpm-institution-import.php` (`text()`, `is_formula()`, `has_control()` from private to public static, bodies unchanged; Ruling 2)
- Modify: `includes/modules/class-wpcpm-students.php` (`boot()`: `WPCPM_Student_Registration_Form::init();`; `activate()`: `ensure_page()`; `uninstall()`: `delete_option( OPT_PAGE )`), `wpcredits-program-manager.php`, `uninstall.php`
- Test: `bin/test-student-registration.php`; run `bin/test-institution-import.php`, `bin/test-institution-import-form.php`, `bin/test-forms-js.php`, `bin/check-spelling.php` (reads block titles)

**Interfaces:**
- Consumes: Tasks 1 to 4; `WPCPM_Form_Guard` (`claim_actor()`, `actor_key()`, `PER_HOUR`, `honeypot_filled()`, `render_honeypot()`, `token()`, `check_token()`, the `TOKEN_*` answers, `consented()`, `consent_evidence()`, `claim_site()`, `PER_DAY`, `policy_url()`); `WPCPM_Form_Stash::bounce()`, `read()`; `WPCPM_Field_Value::clean()`; `WPCPM_Typed_Text::cleaner_loses()`, `loss_message()`; `WPCPM_Dashboards::render_combo()`; `WPCPM_Institution_Student_Form::choices( 'field_of_study' )`; `WPCPM_Institution_Import::text()`, `is_formula()`, `has_control()`; `WPCPM_Mentor_Application_Form::STYLE` and its stylesheet `assets/css/application.css` (the mentor form's look).
- Produces `WPCPM_Student_Registration_Form`:
  - `SHORTCODE = 'wpcpm_student_registration'`, `BLOCK = 'wpcpm/student-registration'`, `OPT_PAGE = 'wpcpm_student_registration_page_id'`, `SLUG = 'student-registration'`, `ACTION_SUBMIT = 'wpcpm_student_register'`, `DWELL_SCOPE = 'wpcpm-student-registration-dwell'`, `ACTOR_PREFIX = 'student-register'`, `SITE_KEY = 'student-register-site'`, `TRANSIENT_PREFIX = 'wpcpm_sreg_'`, `TRANSIENT_MINUTES = 30`, `PER_HOUR = 30`, `QUERY_STASH = 'wpcpm_sreg'`, `QUERY_OUTCOME = 'wpcpm_sreg_said'`, `FIELD_ANSWERS = 'wpcpm_student_registration'`, `HONEYPOT = 'wpcpm_confirm_campus'`, `TOKEN_FIELD = 'wpcpm_student_registration_token'`, `CONTACT = 'education@wordpressfoundation.org'`, `INTEREST_URL = 'https://airtable.com/appIzQKfwTn5dyPVp/shr6Gf1mMMEAxevw4'`, `MAX_TEXT = 2000`.
  - `init()` (`register` on `init`; the submit on `admin_post_` and `admin_post_nopriv_`; `no_cache` on `template_redirect`), `register()`, `render_block( $attributes )`, `ensure_page(): int` (create, or adopt the page at the slug as it stands, never gated), `starting_content(): string`, `page_url(): string`, `no_cache()`.
  - `institutions(): array` record ID to name trimmed, A to Z, from the index rows whose `stage` is in `student_registration_stages`, less any whose name starts with `TEST` (Ruling 27); `languages(): string[]` the copy's names, A to Z.
  - `fields(): array` keyed `name`, `email`, `institution`, `study`, `tutor`, `tutor_email`, `start`, `end`, `hours`, `languages`, `preferred`, `access`, `english`, `consent`, each `label`, `type`, `required`, and per type `help`, `note` (the institution's line), `maxlength`, `auto`, `min`, `max`, `box`.
  - `clean( $key, $raw ): array{ok,value,problem}`; `is_open(): bool`; `outcomes(): array`; `render( $atts = array() ): string`; `handle_submit()`.
- The stored answers (`META_FIELDS`): the fourteen keys less `consent`, `english` `true`, plus `institution_name`.

- [ ] **Step 1: Write the failing tests** (section "The form"):
  - **The fourteen questions**, in order, with the Airtable form's words (design, section 3):

```php
$fields = WPCPM_Student_Registration_Form::fields();
ck( 'the fourteen questions, in the Airtable form\'s order', array_keys( $fields ),
	array( 'name', 'email', 'institution', 'study', 'tutor', 'tutor_email', 'start', 'end', 'hours', 'languages', 'preferred', 'access', 'english', 'consent' ) );
ck( 'with its labels, its two en dashes written as hyphens', array_values( array_column( $fields, 'label' ) ),
	array( 'Full Name', 'Email', 'Educational Institution', 'Department / Area of Study', 'Tutor Name - The professor or main contact person at your educational institution', 'Your tutor\'s email address', 'Preferred Start Date', 'Estimated End Date', 'Total Expected Hours (Approximate)', 'Languages You Speak', 'Preferred Language', 'Do you have any accessibility needs we should be aware of?', 'Compliance declaration', 'Privacy Policy Compliance' ) );
ck( 'the help of five, in its words, and none under the dates', array_filter( array_map( function ( $spec ) { return isset( $spec['help'] ) ? $spec['help'] : ''; }, $fields ) ), array(
	'tutor'     => 'If your educational institution doesn\'t require you to indicate a tutor, you can skip this question.',
	'hours'     => 'Format: Decimal',
	'languages' => 'Select as many as you speak.',
	'preferred' => 'Select only one.',
	'access'    => 'Optional - Leave blank if none.',
) );
ck( 'every one required but the tutor, the tutor\'s address and the accessibility needs',
	array_keys( array_filter( $fields, function ( $spec ) { return empty( $spec['required'] ); } ) ), array( 'tutor', 'tutor_email', 'access' ) );
ck( 'the two boxes\' words, the policy\'s address its link', array( $fields['english']['box'], $fields['consent']['box'] ), array(
	'I understand that the course is conducted in English, requires written communication, and involves proactive, independent remote work.',
	'By clicking this checkbox, you understand that your information will be used in accordance with our Privacy Policy (<a href="https://wordpress.org/about/privacy/">https://wordpress.org/about/privacy/</a>).',
) );
```

    The Airtable form's date help ("Date format: dd/mm/yyyy") is not there: the browser's date field shows the reader's own format (design, section 3). The institution's `note`: `Is your institution not on the list? Ask a faculty member or your administration office to contact us, or fill in the <a href="https://airtable.com/appIzQKfwTn5dyPVp/shr6Gf1mMMEAxevw4">student interest form</a>.`; the field of study's choices the nine; `hours` `min` 1, `max` 2000.
  - **Each refusal**, with its problem and its words (Ruling 26): name `''` `required` "Please fill this in."; `=HYPERLINK("x")` `formula` "This cannot start with =, +, - or @."; `Ada\tExample` `control` "This cannot hold a tab, a line break or a \"|\"."; `A` and 201 characters `name_length` "Please give your full name, from 2 to 200 characters."; email `not-an-address` `bad_email` "That does not look like an email address."; institution `recINSTITUTION003` (at Agreement Sent) and `nonsense` `bad_choice` "Please choose one of the options offered."; study `Cooking` `bad_choice`; tutor `+Tutor` `formula`, 121 characters `too_long` "Please keep this to 120 characters."; tutor address `nope` `bad_email`; start `2026-02-30` and `05/12/2026` `bad_date` "Please give a date."; an end before the start `end_before_start` on the end, "The end date has to be on or after the start date."; hours `ten` `not_a_number` "Please give a number of hours.", `0.5` `below_min` "Please give at least one hour.", `2000.1` `above_max` "Please give at most 2,000 hours.", `150,55` kept as `150.6`; a language the copy lacks dropped, none left `required`; preferred `Spanish` beside Polish and English `preferred_not_spoken` "Your preferred language has to be one of the languages you ticked above.", `Klingon` `bad_choice`; accessibility with `<b>` `markup` (`loss_message()`), 2,001 characters cut to 2,000; the declaration unticked `unticked` "The program can only take students who can say this, so please tick it.".
  - **The page**: with each of Ruling 5's four reasons, the public reads the `closed` sentence "Student registrations through this form are closed for now." once and no form; an Administrator also reads the reason (the switch: "The student registration form is switched off. Turn on \"Registrations from students\" on the Students and mentors tab of WPCredits Program > Settings when the program is ready to take them."; not connected: "This form is not shown yet: Airtable is not connected, so no registration could be written. Connect it on the Connection tab of WPCredits Program > Settings."; no institution: "This form is not shown yet: no institution the site holds is at a stage ticked under Institutions offered, on the Students and mentors tab of WPCredits Program > Settings. New partners arrive with the institutions sync, every three hours."; no languages: "This form is not shown yet: the languages have not been read from Airtable yet. They are read within the hour."). Open: the institution's combobox offers Example University and Sample College (trailing space trimmed), A to Z, and not Pipeline Institute, nor "TEST - Example institution" and "test college" though both are Confirmed (Ruling 27), with the note under it; the preferred language's combobox; the languages as boxes; `type="date"` twice; `type="number" ... min="1" max="2000" step="0.1"`; the two boxes with their words; the honeypot and the token; no `<legend>`. A redraw after `again` keeps every answer in its box and marks each problem; the editor's REST draw is a static summary.
  - **The submit path**, posted as `bin/test-mentor-application-form.php` posts (`submit()`, `landed()`): the order of checks read off `handle_submit()`'s source (ceiling, nonce, closed, honeypot, token, consent, cleaning, requiredness, site ceiling, store, process); thirty posts in an hour from one network address all taken, the thirty-first `busy`, nothing stored (Ruling 13); a stale nonce `expired` with the answers; closed `closed`; the honeypot filled: one `spam` registration, no request, no email, `sent`; a forged token: `spam`; a fast token: processed, marked `dwell-fast`; consent unticked: `consent`, nothing stored; a refused answer: `again`, nothing stored; a good one: `written` through Task 4, the received email.
  - **The same page on every path**: account, already, written, waiting (Airtable down), spam and a held address lock each land on `QUERY_OUTCOME=sent` with no stash, and the page draws "Thank you. We have received your registration and will email you at the address you gave." (the block's `thanks` in its place when set).
  - **Review Focus 4**: the site ceiling spent: the registration is stored `waiting` with `site-ceiling`, no request, `sent`.
  - **Review Focus 5**: the institution's stage moved off Confirmed between the draw and the post: `again`, `institution` `bad_choice`, every other answer kept.
  - **`ensure_page()`**: makes `/student-registration/`, titled "Student registration", whose content is three paragraph blocks, "Welcome!", "Please fill out this short form to express your interest in joining the program. It only takes a few minutes and helps us better understand your background and availability.", "If you have any questions before submitting your interest, feel free to contact us at `<a href="mailto:education@wordpressfoundation.org">education@wordpressfoundation.org</a>`", then `<!-- wp:wpcpm/student-registration /-->`; adopts a page at the slug as it stands; no access level written; `no_cache()` on that page only.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement**, in `WPCPM_Mentor_Application_Form`'s shape and order. The answers walked from `fields()`, never from the request; the consent never a stored value; `english` kept `true`; the institution's name stored beside its ID; a free text and the name handed back as typed when refused (`hands_back_typing()`'s rule, for `name`, `tutor` and `access`). The thank-you: one `<h3>` in `wpcpm-application__done`. The submit button: "Send this registration", busy text "Sending...", status "Sending your registration.". Outcomes beside `closed`: `again` "Nothing has been sent yet. Please look at the answers marked below and send the form again."; `consent` "Nothing has been sent yet, and nothing has been stored. The program can only take a registration from somebody who agrees to the privacy policy, so please tick the last box."; `stale`, `expired` and `busy` the mentor form's words; `lost` "Something went wrong at our end and the registration was not saved. Please try once more.".
- [ ] **Step 4: Run green, then the checks; commit.** `"Student registration: the public form at /student-registration/, guarded as the other forms are"`.

### Task 6: The Registrations tab

**Files:**
- Create: `includes/modules/class-wpcpm-student-registrations-tab.php`
- Modify: `includes/modules/class-wpcpm-students.php` (`TAB_REGISTRATIONS = 'registrations'`; `TABS` Accounts, Registrations, Sync; its own `tab_labels()` and `render_admin_page()`, as `WPCPM_Mentors` has; `render_tab_registrations()`: `$this->render_notice_from( WPCPM_Student_Registrations_Tab::messages() )`, then `WPCPM_Student_Registrations_Tab::render()`; `boot()`: `WPCPM_Student_Registrations_Tab::init();`), `wpcredits-program-manager.php`, `uninstall.php`
- Modify: `docs/sections/30-admin-wpadmin.md` (the Students row of The audience screens, and the paragraph on its tabs), `docs/sections/34-admin-operations.md` (Pages the plugin owns; Uninstall; the data table's three options), then `php bin/build-docs.php`
- Test: `bin/test-student-registration.php`, `bin/test-students-screen.php`; run `bin/test-admin-menu.php`, `bin/test-handbook.php`

**Interfaces:**
- Consumes: Tasks 2 and 4; `WPCPM_Request::key()`, `posted_id()`, `posted_key()`; `WPCPM_Flash::set()`; `WPCPM_Settings::airtable_record_url( 'students_table', $record )`; `get_edit_user_link()`.
- Produces `WPCPM_Student_Registrations_Tab`:
  - `ACTION_WRITE = 'wpcpm_sreg_write'`, `ACTION_RESTORE = 'wpcpm_sreg_restore'`, `ACTION_DELETE = 'wpcpm_sreg_delete'`, `FIELD_REGISTRATION = 'wpcpm_sreg_id'`, `VIEW_ARG = 'sreg_view'`, `LIST_MAX = 50`, `FLASH = 'students_admin'` (the Students screen's channel).
  - `init()`: the three on `admin_post_` only. `views(): array` (`all`, `waiting`, `failed`, `spam`, each `states` and `label`: "All", "Waiting to be written", "Could not be written", "Spam"); `view_url( $view = 'all' ): string`; `render()`; `messages(): array`; `handle_write()` (a `failed` registration: `process()` at once, Ruling 25); `handle_restore()` (a `spam` one: `requeue()`, then `process()`); `handle_delete()` (a `spam` one: `wp_delete_post( $id, true )`). Each handler: the capability, then `check_admin_referer( $action . '_' . $id )`, then the registration in a state it takes, else `sreg-unknown` or `sreg-state`; back to the view it was pressed on.
  - Each row (`id="wpcpm-sreg-<ID>"`): the date, the name, the address, the institution, and what happened: **Written to Airtable** (the row's link), **Already has an account** (the account's edit link), **Already registered** (the row's link, or "an earlier registration" linking `#wpcpm-sreg-<first>`), **Waiting to be written** ("Airtable could not be reached; tried again every hour.", the tries, the last try and its error), **Could not be written** (the error, and **Write it now**), **Spam** (**Put back** and **Delete for good**, which asks first); the left-out sentence; an email not sent ("The email was not sent: the day's limit on emails was reached." or "The email could not be handed off."); then a `<details>` "Answers and consent" listing the answers by their labels and the consent's sentence, time, truncated address and browser.
- Messages: `sreg-written` (success) "The registration is written to Airtable, and the student is sent the received email."; `sreg-settled` (success) "The registration is settled without a new row: the address has an account or is registered already, and the student is sent that email."; `sreg-waiting` (warning) "Airtable could not take the registration; it waits, and is tried again every hour."; `sreg-failed` (error) "Airtable refused the registration again. The reason is on it."; `sreg-busy` (warning) "Another request is writing a registration for that address at this moment. Reload the page in a minute."; `sreg-deleted` (success) "The registration is deleted for good."; `sreg-unknown` (error) "There is no registration with that ID."; `sreg-state` (error) "That registration is not in a state this press applies to. Reload the page to see where it got to.".

- [ ] **Step 1: Write the failing tests** (section "The tab"): an account without the capability draws nothing, and each handler `wp_die()`s with 403 before reading anything; a wrong nonce stops it; the views and their counts (spam only under **Spam**); newest first, 50 drawn of 53 with "Showing the newest 50 of 53."; each outcome's words and link as above; the `<details>` holds the answers and the consent's sentence; **Write it now** on a failed one: `written` and `sreg-written`; down again: still `failed`, `sreg-failed`; **Put back** on spam whose address now has an account: `account`, the account email, `sreg-settled`; **Delete for good** on spam: gone; on a written one: `sreg-state`, kept. `bin/test-students-screen.php`: the bar holds three tabs, Accounts, Registrations, Sync, Accounts shown from the menu, when asked for and for a tab the screen does not have; `WPCPM_Students::TABS` is the three; the labels translated where the bar prints them; the Registrations tab draws the registrations card and the notice of the press made on it.
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** The card: heading "Student registrations" with the count waiting and could not be written, then one sentence: "Students who registered through the form on the site, newest first. A registration is written to the Airtable Students table unless the address has an account or is registered already; one Airtable could not take waits and is tried again every hour, and after 72 hours shows as Could not be written."
- [ ] **Step 4: Docs.** Guide 30: "Three tabs: **Accounts**, **Registrations**, ... and **Sync**" in the Students row, and a paragraph (not a heading: a heading "Student registrations" in an earlier part would take guide 31's id, `student-registrations`, which the Settings screen links) on the Registrations tab (what is listed, the six outcomes and their links, the four views, the presses and what each does, that the Airtable row is never changed or deleted by the site, and that no email goes to Administrators, since the program manager hears of a new student from Airtable). Guide 34: under Pages the plugin owns, the form's page at /student-registration/ and that activation, or a person, makes it; under Uninstall, the registrations; three rows in the data table, A to Z: `wpcpm_sreg_lock_` (`WPCPM_Student_Registration::LOCK_PREFIX`, the per-address and the hourly job's locks), `wpcpm_student_languages` (`WPCPM_Student_Airtable_Row::LANGUAGES_OPTION`), `wpcpm_student_registration_page_id` (`WPCPM_Student_Registration_Form::OPT_PAGE`). `php bin/build-docs.php`, twice.
- [ ] **Step 5: Run green, then the checks; commit.** `"Student registration: the Registrations tab of the Students screen"`.

### Task 7 (coordinator): The version, the changelog and the whole battery

**Files:** `wpcredits-program-manager.php`, `readme.txt`, `languages/wpcredits-program-manager.pot`, `docs/administrators.md`, `docs/build/administrators.html`

- [ ] **Step 1: The version.** 1.123.54 in the plugin header, `WPCPM_VERSION` and the Stable tag; the block's `version` in `block.json` and `editor.asset.php`; then above `= 1.123.53 =`:

```text
= 1.123.54 =

* Students can register for the WordPress Credits Program on this site, at /student-registration/, with the same questions as the Airtable Student Registration Form, in its words and its order. The form is off until it is switched on, under Student registrations on the Students and mentors tab of Settings, and lists the institutions at the stages ticked under Institutions offered, Confirmed alone by default.
* A registration is written to the Airtable Students table with an empty Status, as the Airtable form writes it, so a program manager assigns the mentor and the track as before and the site makes no account and sends no invitation by itself. An address that belongs to an account on this site, or that the site or the Students table holds already, writes nothing: the student is sent an email that says so instead. Every student sees the same thank-you, whatever happened.
* When Airtable cannot be reached, the registration waits on the site and is tried again every hour; after 72 hours it shows as Could not be written, with Write it now.
* A Registrations tab on the Students screen lists every registration, newest first, with what happened to it and a link to the Airtable row or the account; a registration filed as spam can be put back or deleted for good.
* Three new emails, in a Student registrations area of Tools > Emails: registration received, already has an account, already registered.
```

  Then `sh bin/make-pot.sh` and `php bin/build-docs.php`.
- [ ] **Step 2: The battery.** `for t in bin/test-*.php; do php $t; done`, then the checks every task ends with, then `php bin/test-fixtures.php`, then Before Task 1's step 3 run over the whole diff of the branch (`git diff main --` piped into the three scans in place of the plan). Expected: every suite `ALL PASS`, the counts as recorded plus what the tasks added, the scans clean.
- [ ] **Step 3: Commit.** `"Student registration: 1.123.54, the changelog and the translation template"`.

### Task 8 (coordinator): Main, then the release script's check, build and scan

- [ ] **Step 1: Main holds the branch.** `git checkout main && git merge --no-ff feature/student-registration -m "Merge 1.123.54: students register on the site, written to the Airtable Students table, once a person switches it on"` with the trailer. If main moved since the branch was made, merge main into the branch first and run the battery there. Then the battery on main.
- [ ] **Step 2: Check, build, scan**, from `~/Projects/wpcredits-release`:

```bash
python3 release.py check plugin
python3 release.py build plugin
python3 release.py scan plugin
```

  Expected: the watch report of the public repository's changes since the last push (read it: a change somebody made in our folders on the mirror is ported first), then `PASSED: check plugin 1.123.54 (...) against live 1.123.53.`, `PASSED: build plugin 1.123.54: ...` with the zip's md5, and `PASSED: scan plugin 1.123.54: no finding. ...`. Anything else for the live version: stop and tell the product owner. Rebuild `~/GitHub/wpcredits-program-manager.zip` from the build.

### Task 9 (coordinator): The trial on the Docker copy, Airtable faked

No repository files. The kit is written to `.superpowers/sdd/2026-11-05-plugin-1-123-54-student-registration/trial-kit.md` (under `.superpowers/`, never mirrored), from the mentor applications' kit (`.superpowers/sdd/2026-10-17-mentor-applications-m2/trial-kit.md`): signing in as the copy's local Administrator; a placeholder connection (`api_token` `patTRIALNOTAREALTOKEN`, `base_id` `appTRIALBASE00001`, both invented); and a must-use plugin `zz-sreg-trial-airtable.php` on `pre_http_request` at priority 1, for `https://api.airtable.com/` only, answering the schema (the Students table under the `students_table` setting with the fixture's fifteen columns, the Languages table with English, Polish and Spanish, the Institutions table holding the index's own rows), the Students search (no row, except an address planted in the option `zz_sreg_trial_rows`), the create (`recTRIALROW00000N`, counting up; a 422 while `zz_sreg_trial_refuse` is set; a `WP_Error` with no status while `zz_sreg_trial_down` is set), and logging every method, URL and body to `site/wp-content/zz-sreg-trial-airtable.log`. The copy's mail stays in its own log. Notes and captures go to the session's scratchpad and are backed up under `.superpowers/session-backups/<the day>/student-registration/`.

- [ ] **Step 1: Snapshot and install.** In `~/Projects/wpcredits-docker`: `bin/snapshot before-trial-1.123.54`, `bin/check | tail -1`, `bin/wp plugin get wpcredits-program-manager --field=version`; the zip of the version the copy runs in its `release/` folder; then from `~/Projects/wpcredits-release`: `python3 release.py deploy plugin --site local --version 1.123.54` (its plan, `NOT RUN`, exit 3) and the same with `--yes`. Expected: `PASSED: deploy plugin 1.123.54 on http://localhost:8900 ...`.
- [ ] **Step 2: The page and the switch.** `bin/wp eval 'echo WPCPM_Student_Registration_Form::ensure_page(), "\n";'`; the kit's connection and plugin; `bin/wp eval 'WPCPM_Settings::patch( array( "student_registrations_enabled" => 1, "mail_contact" => "programs@example.test" ) ); echo "set\n";'` and the `people` scope read back; `bin/wp cron event run wpcpm_student_registrations_hourly`. Expected: a page ID; the languages copy holds three names; the page as a stranger shows the introduction and the form, offering only the copy's Confirmed institutions.
- [ ] **Step 3: The paths.** As a stranger: a new registration at `trial-new@example.test`: Written, one POST in the log whose fields are Task 3's for the answers given and hold no Status, the received email in the mail log; `bin/wp user create trial-student trial-student@example.test --role=subscriber`, then a registration at `Trial-Student@Example.test`: Already has an account, no request, the account email; an address planted in `zz_sreg_trial_rows`: Already registered, a search and no POST; `trial-new@example.test` again: Already registered, a short entry linking the first; `zz_sreg_trial_down` set: Waiting to be written; unset it and run the hourly event: Written; `zz_sreg_trial_refuse` set and the registration's `_wpcpm_sreg_queued` moved back 73 hours, the hourly event: Could not be written, then unset and **Write it now**: Written; a submission with the honeypot filled (from the console): Spam, no request, no email; **Put back**, then **Delete for good** on a second spam. On every path the stranger saw the same thank-you, the mail log holds no invitation, and no account was made.
- [ ] **Step 4: Screenshots and spacing.** At 1280 and 375 pixels: the form, a redraw with problems, the thank-you, the Registrations tab in each view, the settings section. Nothing wider than the screen at 375; every gap measured (the owner's spacing rule): the computed margins between questions, help, notes, problems and the button equal the mentor form's at both widths, and the tab's rows the mentor queue's.
- [ ] **Step 5: Put the copy back.** The rollback commands in the release folder's `rollback-local.txt`, then `bin/restore before-trial-1.123.54`, the version read again, `bin/check | tail -1`. Expected: the version Step 1 read, `All checks passed.`. Give the product owner the results, the captures and the log's summary, and stop.

### Task 10 (coordinator): Mirror, deploy and the first live registration, each on the product owner's yes

Every command from `~/Projects/wpcredits-release` unless it says otherwise. Nothing here runs without the product owner's explicit yes for that step.

- [ ] **Step 1: Read the live version first.** `ssh wpcredits-dashboard 'wp plugin get wpcredits-program-manager --field=version'`: `1.123.53`. Anything else: stop and say what it reads.
- [ ] **Step 2: The mirror, on the yes.** `messages/1.123.54.txt`, one plain paragraph with no attribution and no names: "Students can register on the site, at /student-registration/, with the Airtable Student Registration Form's questions, once a person switches the form on: a registration is written to the Airtable Students table with an empty Status, an address that has an account or a registration already writes nothing and is told so by email, a registration Airtable cannot take waits and is tried again every hour, and a Registrations tab on the Students screen lists every one." `python3 release.py mirror plugin --message messages/1.123.54.txt` (`NOT RUN`, exit 3); the product owner is given the version, the md5, the checks' lines, Task 9's results and the message; on the yes, the same with `--yes`. Expected: `PASSED: mirror plugin 1.123.54: origin/trunk is now <the new commit>`.
- [ ] **Step 3: The deploy, on the yes.** `python3 release.py deploy plugin` (its plan, `NOT RUN`), the yes, then `--yes`. Expected: the probes, page 560 republished from `administrators.html`, the caches flushed and purged, `PASSED: deploy plugin 1.123.54 on https://wordpresseducation.org. ...`.
- [ ] **Step 4: Read live back, writing nothing.** `ssh wpcredits-dashboard 'wp eval "\$s = WPCPM_Settings::get(); var_dump( (bool) \$s[\"student_registrations_enabled\"], \$s[\"student_registration_stages\"], count( WPCPM_Student_Registration_Form::institutions() ), count( WPCPM_Student_Airtable_Row::languages() ), (int) get_option( WPCPM_Student_Registration_Form::OPT_PAGE ), \"\" !== WPCPM_Mail_Templates::program_contact(), (bool) wp_next_scheduled( WPCPM_Student_Registration_Process::CRON_HOURLY ) );"'`. Expected: the switch off, `Confirmed`, a count of offered institutions above 0, the languages read (above 0 once the hourly job has run), no page yet (0), a contact set, the job scheduled. No name or address is printed.
- [ ] **Step 5: Before the first write, the product owner reads the Students table's automations in Airtable** (read-only, a person's step): which fire on a new row (the tutor's address filled from the institution's, the "New student registered" notice, the first-contact emails some institutions have), and picks for the test the institution whose automations write to nobody outside the program.
- [ ] **Step 6: The page and the switch, on the yes.** `ssh wpcredits-dashboard 'wp eval "echo WPCPM_Student_Registration_Form::ensure_page();"'`; the product owner reads the page and switches **Registrations from students** on, on a freshly loaded Students and mentors tab, and the `people` scope is read back.
- [ ] **Step 7: The first real write, on the yes.** One registration with the product owner's own test address and the institution of Step 5, sent through the page. Expected: on the Registrations tab, Written to Airtable with the row's link; in Airtable (the product owner reads it) one Students row with an empty Status; the received email at the owner's test mailbox; no account, no invitation. Whether the test row is deleted in Airtable is the product owner's call, made by hand: the site never deletes it. Then the product owner decides whether the switch stays on and when the link replaces the Airtable form's with the institutions and coordinators (design, section 7).
- [ ] **Step 8: The vault and the notes.** The design's first paragraph gains "Shipped as 1.123.54 on <the day>."; the plugin's vault note and the memory note gain the release, the switch's state, and the launch's remaining steps (the link shared, the Airtable form closed later by a person).
