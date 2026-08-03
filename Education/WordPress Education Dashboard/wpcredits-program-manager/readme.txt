=== WPCredits Program Manager ===
Contributors: gomp
Tags: airtable, members, roles, education, wordpress-credits
Requires at least: 6.5
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.42.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Runs the WPCredits program on WordPress in five modules — Students, Mentors, Institutions, Sponsors and Administrators — plus a Tools section, with role-based access and Airtable sync.

== Description ==

The plugin is organized as five modules, one per audience:

1. **Students** — the Student role, Airtable account provisioning, and a private page with each student's program details and their assigned mentor. **Built.**
2. **Mentors** — the Mentor role, Airtable account provisioning, and a private page listing each mentor's assigned students. **Built.**
3. **Institutions** — the Institution role.
4. **Sponsors** — the Sponsor role.
5. **Administrators** — the built-in WordPress Administrator role, granted the program capabilities.

Students, Mentors, Institutions and Sponsors each get a custom role cloned from **Subscriber**, plus one marker capability that controls which content they can read. Administrators can read every level.

The Students and Mentors modules are built. Institutions and Sponsors register their roles and reserve their admin screens; Administrators uses the built-in role.

Alongside them is a **Tools** section, for jobs you run *against* the program data rather than parts of the program itself. Keeping the two apart means the modules stay a stable description of the program while tools come and go as needed. It currently holds two tools: **Header notices** and **Mentor Status Checker**.

= Roles and capabilities =

| Module | Role slug | Marker capability |
| --- | --- | --- |
| Students | `wpcpm_student` | `wpcpm_view_student_content` |
| Mentors | `wpcpm_mentor` | `wpcpm_view_mentor_content` |
| Institutions | `wpcpm_institution` | `wpcpm_view_institution_content` |
| Sponsors | `wpcpm_sponsor` | `wpcpm_view_sponsor_content` |
| Administrators | `administrator` | `wpcpm_manage_program` + every marker capability above |

Role slugs are prefixed on purpose. Bare `student` and `teacher` slugs are commonly claimed by LMS plugins, and sharing a role slug means sharing its capability set.

= Tools: Header notices =

One notice per audience — Students, Mentors, Institutions, Sponsors, Administrators. **WPCredits Program → Modules → Header notices** puts them all on one screen, each with its own editor, whether it is showing, and a single Save button under the lot.

**Each notice is a classic `wp_editor()` box, stored as HTML in one option.** Not `teeny`, so the media button and the kitchen-sink row are both there: a notice is written in the same place it is read about, and saving all of them is one press rather than five round trips through a post editor.

Notices were posts on the block editor until 1.22.0. The upgrade migrates each body through `do_blocks()` into the option once — that is the last moment anything knows it was block markup — and leaves the old posts alone; `uninstall.php` removes them with everything else. Nobody's words are deleted as a side effect of an upgrade.

Rendered `wpautop()` → `wp_kses_post()`. Images are capped in height on the front end and buttons scaled down — a notice is read at a glance, and markup that expects a full column of page will not get one.

Empty a notice to stop it showing; there is no separate switch.

An empty notice is off. There is no separate switch, because a switch is one more thing to leave in the wrong position.

**Anyone in two audiences sees both notices.** An administrator who also mentors gets the mentor notice and the administrator one, mentor first — they are reading as a mentor most of the time. The alternative, showing only the "most specific" audience, would withhold a notice from exactly the people holding two roles, who are the likeliest to need it. Audience membership uses the same tests the dashboards do, so an administrator matched to an Airtable mentor record counts as a mentor even though the sync never gives them the role.

Links and simple emphasis survive; scripts and other markup are stripped **on save**, so nothing dangerous is stored rather than merely hidden at render time.

Prepended to the page's content, so a notice appears at the top of the page somebody is on rather than above the site header. A theme that wants them elsewhere can return false from `wpcpm_notices_auto_render` and call `WPCPM_Notices::render()` itself. Filter `wpcpm_current_notices` to change what a given person is shown.

= Content access levels =

Every post and page gets a **Program access** control in the editor sidebar with five options: Public, Student level, Mentor level, Institution level, and Administrators only. A post with no explicit level is public.

Gating is applied in four places: front-end listings (restricted posts are filtered out), direct URL access (logged-out visitors go to the login form, logged-in users get an explanation), the rendered content and excerpt, and the REST API.

= Mentors module =

**Account provisioning.** Every mentor in the Airtable Mentors table holding the status `Active` gets a WordPress account with the Mentor role. The username comes from their WordPress.org profile, so `https://profiles.wordpress.org/clk87/` becomes the username `clk87`. That column is free text and in practice contains full URLs, scheme-less URLs, `@handles`, bare usernames, URLs ending in `/profile/` and at least one misspelled host, so every shape is reduced to its last path segment.

Accounts are created with a random password and **no email is sent**. A first sync provisions around ninety accounts at once, so invitations are opt-in: either tick the setting, or use **Send invite** next to an individual mentor, which emails them a password-reset link.

Existing accounts are matched in order of reliability — the stored Airtable record ID, then email address, then username. An account already linked to a different mentor record is reported as a conflict and left alone. Administrators' roles are never modified, and no account is ever deleted or demoted; when a mentor stops being `Active`, the plugin removes the Mentor role and clears their student list, or leaves the role in place, depending on the setting.

**The mentor page.** Activation creates a page called *My Students* at `/mentor-dashboard/`, gated to Mentor level, containing the *My Students (Mentor)* block. There is one page, not one per mentor: it renders against the logged-in user, so every mentor sees only their own students and no mentor can reach another's list by guessing a URL. Administrators can inspect any mentor's view with the *Viewing as mentor* control. An administrator who is also an Active mentor in Airtable sees their own students by default and appears in that switcher like anyone else — the sync never gives an administrator the Mentor role, so they are recognized by their link to an Airtable mentor record instead.

The mentor's own profile photo and name head the page. Each student then gets a card showing their photo, name and status, and a **table of their details** — each field and its value, with no header row:

* Status (`In Sensei` or `In Sensei 50h`)
* Internship start and end date
* Tutor
* Educational institution
* WordPress.org
* Email address, as a `mailto:` link
* Slack, linked to the program's Slack channel
* Main contribution team
* Personal website URL

Below the table is a **Student report form** button, linking to their prefilled reporting form — the Airtable *Personal link* for `In Sensei`, or *50h personal link* for `In Sensei 50h`, where the button reads **Student report form (50h)**.

= Current and past students =

The page is split in two. **Currently mentoring** lists the students a mentor is working with now, ordered by internship end date — soonest deadline first, unknown dates last. **Past students** is a separate, collapsed section for students whose mentoring has finished, ordered most recently finished first. Their details and notes are kept for reference.

Which is which comes from the student's Airtable status, not from their end date: a student can be past their end date and still being mentored, or have graduated early. Two settings control it — *Currently mentoring* (`In Sensei`, `In Sensei 50h`) and *Past students* (`Graduate`, `Dropped out`). Empty the second box to show only current students. A status listed in both counts as current, so a configuration slip can never quietly archive somebody's live student.

Past students were previously not read from Airtable at all, so **run one sync after updating** for the Past section to appear. Until then everything shows as current, exactly as before.

= Call notes =

Each student carries a running history of notes — one per call, typically — so a mentor can see what was discussed and when. Notes are written and read on the student's own card: type into **Add a note**, press **Save note**, and it appears at the top of the list stamped with the date, time and author. The collapsed row shows a note count, so it is obvious at a glance who has been spoken to and who has not.

A mentor can delete their own notes; a program manager can delete any. Nobody can delete a colleague's record of a call. There is no editing — correct a mistake by deleting and writing again.

**Past students cannot receive new notes.** A student who has graduated is not going to be called again, so the *Add a note* form is not shown for anyone in the Past students section, and a request to add one is refused rather than merely hidden. Their existing history stays readable, and still deletable.

**Who can see them.** Only mentors that student is actually assigned to, plus administrators. The check is made against the mentor's own synced student list on every read and every write, so a mentor cannot reach notes for somebody else's student even by crafting a request.

**Where they live.** In their own private post type, not in the Airtable data or the cached student rows. That matters for two reasons: the sync rewrites the whole cached student list on every run, so a note kept there would be destroyed by the next sync; and notes are WordPress-side records, never written back to Airtable. They are removed only when the plugin is uninstalled.

= Call calendar =

Students book their own mentor calls, from hours the mentor has set. A mentor who has set no availability offers nothing — the calendar is never a guess at when somebody might be free.

**The mentor sets their hours first.** *Your availability for calls*, on the mentor dashboard, takes a weekly pattern — two ranges per day, so a morning can be split from an afternoon — plus the call length, the shortest notice they will accept, how far ahead students may book, how many calls one student may hold at once, days off, and an optional note shown above the slots. The panel says how many slots that opens right now, so a schedule can be checked before anyone books against it.

**Students pick from a month calendar.** Days with something open are marked with a count and lead to that day's times. Booking is one press, with an optional note about what they would like to discuss. Both people get an email, and both can cancel — which puts the slot straight back on the calendar.

**Timezones are the point of most of this.** The program runs across a dozen countries, so a single clock would be wrong for nearly everybody. A mentor's hours are stored in the mentor's own timezone, because that is the clock they mean when they say "mornings". Every time is then shown to whoever is reading it on *their* timezone, chosen once and remembered; a student who has never chosen is offered their device's timezone rather than having it applied silently. A mentor looking at a booking also sees what time it is for the student, so a call is not rescheduled over a misunderstanding.

Daylight saving is handled rather than hoped for. Nothing is offered inside an hour the clocks skip, and on the night an hour repeats a wall-clock time is offered once, not twice — "Sunday 03:00" is one appointment, and two identical buttons would be a coin toss.

**Two students cannot take the same slot.** Booking holds a short database-level lock and re-checks the slot inside it; the insert is then checked against the table for a clash, and the later of two bookings is undone and its owner told to pick again. A slot that is taken, blocked, in the past, or inside the mentor's notice period is never generated in the first place, so no screen has to decide what to gray out and two screens cannot disagree.

**Nothing here needs JavaScript.** Months and days are links and booking is a form post. The one script offers the browser's timezone to somebody who has not set one.

Past students cannot book, for the reason they cannot receive new notes. Program managers can book on a student's behalf, which a student locked out of their account still needs. Calls live in their own private post type, are never written back to Airtable, and canceling keeps the record rather than erasing it.

= Each student collapses =

A mentor with sixty students needs a list they can scan, so each student starts collapsed. The row always shows their photo, name, status, institution and internship end date; opening it reveals the full details table.

It is a native disclosure, so it works with JavaScript switched off, responds to the keyboard, and is announced properly by screen readers. **Expand all** and **Collapse all** buttons appear above the list when scripting is available, and printing opens everything first so a printed list is never half-empty. A mentor with exactly one student gets that student already open, since collapsing a list of one helps nobody.

Note that some browsers do not search inside collapsed sections with Ctrl+F. Use **Expand all** first if you are looking for something specific.

= The mentor's landing page =

Mentors have Subscriber-level accounts, so wp-admin shows them nothing they can use. By default the *My Students* page therefore acts as their dashboard:

* Logging in takes them straight there.
* It replaces the wp-admin Dashboard, so a bookmark or a `/wp-admin/` link lands on it too.
* A **My Students** link sits in the toolbar, so they can get back from anywhere on the site.

Three deliberate exceptions. A mentor who followed a link to a specific page and was sent through the login form still ends up at **that** page, not the dashboard. `profile.php` is left alone, so they can still change their own password and name. And an account that also holds an editor or author role is never redirected, since it has a real reason to be in wp-admin. Administrators are unaffected throughout, and get the same toolbar link for inspecting the page.

Turn the whole behavior off with **Mentor landing page** in the settings.

= Profile photos =

Photos come from the person's WordPress.org profile. WordPress.org serves them through Gravatar, keyed on a hash of the *wordpress.org* account email — which this plugin never sees, so the hash cannot be worked out locally. Rather than scraping each profile page or caching a hash that goes stale the moment someone changes their picture, the plugin uses wordpress.org's own username-to-avatar redirect (`grav-redirect.php`). For anyone with no WordPress.org profile recorded, it falls back to a Gravatar of their program email address.

= Field descriptions come from Airtable =

Every row's Description column shows **the description written on that column in Airtable**, read from Airtable's schema endpoint during each sync. Write or edit a field description in Airtable and it appears on the mentor page after the next sync — no code change.

None of these columns currently carries a description in Airtable, so what shows today is the plugin's own built-in wording ("The school or university the student comes from", and so on). Airtable's description always wins once one exists. Override the built-in text with the `wpcpm_field_descriptions` filter.

Underneath each description is the exact source, for example *Airtable · Students Reports → Slack Name* — which is where to go when a value is wrong or missing. Tutor reads *Airtable · Students → Tutor*, because it is the one field joined from the other table.

Reading descriptions needs the `schema.bases:read` scope on the token. Without it the sync carries on normally, notes it in the report, and shows the built-in descriptions — nothing else is affected.

= Nothing is silently hidden =

Fields are **never dropped when empty**. A blank value shows a muted "Not set" instead of the row disappearing, because a silently dropped row is indistinguishable from the page having forgotten the field.

This surfaces real data problems. For instance, a student whose WordPress.org profile reads `https://someone.blog/` has put a personal blog in that column: there is no WordPress.org username to extract, so the plugin shows "Not set" rather than inventing a handle from the hostname.

= Where the data comes from =

| Shown on the page | Airtable table | Field |
| --- | --- | --- |
| Student name, status | Students Reports | Name, Status |
| Email address | Students Reports | Email |
| Internship start / end | Students Reports | Internship Start Date / Internship End Date |
| Educational institution | Students Reports | Educational institution |
| WordPress.org | Students Reports | WordPress Profile |
| Slack | Students Reports | Slack Name |
| Main contribution team | Students Reports | Main Contribution Team |
| Personal website | Students Reports | Personal Website URL |
| Personal link / 50h link | Students Reports | Personal link / 50h personal link |
| Mentor assignment | Students Reports | Mentor (linked record) |
| **Tutor** | **Students** | **`Tutor `** |

Tutor is the one field that does not exist on Students Reports, so the two tables are joined on email address. Note that the Students column really is named `Tutor ` with a trailing space; dropping it silently returns no tutor at all.

Field *names* are used rather than IDs because Airtable's `filterByFormula` only accepts names. Override them with the `wpcpm_mentors_fields` filter.

= Students module =

**Account provisioning.** Every student in the tracked statuses gets a WordPress account with the Student role, and can read Student-level content and nothing else. Usernames come from the student's WordPress.org profile where Airtable has one and from their email address otherwise — only about seven in ten students have a profile recorded, so the fallback is the common case rather than an edge one. Accounts are matched by their stored record ID and then by email, never by username: a login derived from an email address is far too weak a signal to claim an existing account on.

As with mentors, accounts are created with a random password and **no email is sent**, administrators' roles are never touched, and no account is ever deleted. A student who leaves the program loses the Student role, and with it access to Student-level content, unless you set *When a student leaves the program* to leave it in place.

**The student page.** Activation creates a page called *Student Report Card* at `/student-dashboard/`, gated to Student level. Its sections are *My profile*, *My mentor*, *My course and report form* and *My mentor call*. It renders against the logged-in user, so each student sees only their own. It shows their program (`In Sensei` or `In Sensei 50h`), internship dates, tutor, educational institution, WordPress.org, Slack, contribution team, personal website, and a button to their prefilled report form — the 50-hour form for students on that track.

There is no *Program* column in Airtable, so the program shown is the student's status, which is also what decides which report form applies.

= Reaching the dashboards =

Administrators see a **Dashboards** menu in the toolbar holding every dashboard they can reach. A mentor or student with one page gets a direct link to it instead, and somebody who is both gets both, each labeled as theirs.

An administrator who opens a dashboard before any sync has run is told exactly that, with a link to the screen that runs one — not that they are missing a role.

= The mentor's contact details =

The student page shows the mentor assigned to them, with enough detail to actually reach them: name, email, Slack, WordPress.org, website and GitHub, plus their job line, location and contributor teams.

Airtable's Mentors table holds only a name, an email and a profile URL, so **everything else is read from the mentor's WordPress.org profile** — one request per mentor during the sync, cached for twelve hours and shared by every student assigned to them. Airtable stays authoritative for name and email; the profile only fills what Airtable has no column for and never overwrites a value that is already there.

A mentor whose profile cannot be read keeps their Airtable details and is named in the sync warnings, rather than the run failing.

= Sync behavior =

The sync is a resumable state machine: roughly 90 mentors, 290 student reports and the whole Students table is more than one request can carry, so each tick works to a time budget, saves its position and starts the next one. Phases run in order — provision mentors, review mentors who are no longer active, read student reports, read tutors, assign — because a mentor not yet paged in would otherwise look inactive.

It runs once a day and on demand from **WPCredits Program → Mentors → Sync mentors now**.

= Sync progress =

A run never leaves you guessing whether it is working. Starting a sync returns immediately — no long blank request — and the Mentors screen then shows a live readout: a progress bar, the current step ("Step 2 of 5"), what that step has achieved so far ("91 mentors read · 12 created · 79 linked"), and a clock counting up every second.

The screen both reports the progress *and* drives the work: each poll performs one slice, so the sync advances even where WP-Cron is unreliable, and the numbers move every few seconds rather than every eighteen. Cron remains the fallback if you close the tab, and a lock prevents the two from processing the same page twice. With JavaScript off the page refreshes itself instead and cron does the work.

If a run genuinely stops advancing for more than two minutes, the screen says so rather than spinning forever. `wp wpcredits sync-mentors` prints the same percentage and counts, one line per slice.

= Tools: Mentor Status Checker =

Was the standalone **Credits Program Mentor Checker** plugin; now a tool at **WPCredits Program → Tools → Mentor Status Checker**. Folded in so there is one Airtable connection, one settings screen and one place to look. **If you were running the standalone plugin, deactivate it** — otherwise both schedule their own weekly check and read the same WordPress.org profiles twice. The tool says so on screen if it finds the old plugin still active.

It reads every mentor whose Airtable status is `Vetted - positive`, looks up their WordPress.org contribution history, and moves those who have completed the *WordPress Credits Mentor's Course* to `Active`.

* **Report only** is the default and never writes anything. A separate button promotes, and each row also has its own **Promote**.
* Promotion **re-reads the record immediately before writing**, so a status somebody else changed in the meantime is left alone.
* A mentor whose history is longer than the page cap is reported as *could not check*, never as *not completed* — a false negative would leave them waiting.
* The completion phrase and the course link must appear in the **same** history entry, so a mentor who merely blogged about the course is not counted as having taken it.
* Runs are batched, with a progress bar, live counts and an ETA. The whole queue is listed up front and each row resolves in place, so the screen is never blank while work is happening.
* A weekly automatic run is available, off by default — as is letting that run promote.

Promoting writes to Airtable, so it needs the `data.records:write` scope. Everything else is read-only.

`wp wpcredits check-mentors [--promote]` does the same from the command line.

= Requirements =

An Airtable Personal Access Token on the WPCredits base with:

* `data.records:read` — **required.** Reading mentors, students and tutors.
* `data.records:write` — **required by the Mentor Status Checker.** Changing a mentor's status when you promote them. Without it the tool still runs in report-only mode, but promoting fails with a 403.
* `schema.bases:read` — optional. Reading each column's description from Airtable; without it the built-in descriptions are shown.

The scopes are listed on the settings screen too. They cannot be verified from WordPress without writing to the base, so if a promotion fails with a permissions error the plugin names the scope that is missing rather than passing Airtable's message through unchanged.

The token is stored in the database, is only ever rendered masked, and is never sent to the browser.

== Installation ==

1. Upload and activate the plugin. Activation registers the roles, grants the program capabilities to Administrator, and creates the *My Students* page.
2. Go to **WPCredits Program → Settings** and add your Airtable Personal Access Token. The base and table IDs are pre-filled.
3. Go to **WPCredits Program → Mentors** and choose **Sync mentors now**.
4. Review the sync report, then invite mentors — individually, or by enabling invitation emails before a sync.

== Frequently Asked Questions ==

= Does syncing email anyone? =

No. Accounts are created silently with a random password. Invitations are sent only when you enable the setting or press **Send invite** for a specific mentor.

= What happens to a mentor who is no longer Active in Airtable? =

Their Mentor role is removed and their student list cleared, so they can no longer read Mentor-level content. The account itself is kept. Set *When a mentor is no longer active* to *Leave the role in place* to disable this.

= A mentor has no WordPress.org profile in Airtable. What happens? =

They are skipped and named in the sync report's warnings, because the username is derived from that profile. The same applies to a mentor with no valid email address — WordPress cannot create an account without one.

= Can mentors see each other's students? =

No. The page renders against the logged-in user. Only accounts with `wpcpm_manage_program` — Administrators — can view another mentor's list.

= Does uninstalling delete the mentor accounts? =

No. Uninstall removes settings, sync state, access-level meta and the custom roles, and moves affected accounts to Subscriber. Accounts are never deleted.

== Screenshots ==

1. The module overview.
2. The Mentors screen, with the sync report and mentor list.
3. A mentor's *My Students* page.
4. The Program access control in the editor.

== Changelog ==

= 1.42.0 =
* **Three program guides**, one per audience, in `docs/`: a student guide, a mentor guide that
  contains the whole student guide, and a program manager guide that contains both plus the
  wp-admin walkthrough — every screen, every setting, the access levels, the sync and a
  what-to-check-when table. Each guide is complete on its own because the access levels do not
  nest: a mentor cannot open a Student-level page, so linking to it instead of repeating it would
  send them to the restricted notice.
* **`bin/build-docs.php` assembles them from one set of sections**, so a section used by more than
  one guide is written once. It emits Markdown for reading in the repository and block markup for
  the published pages, resolving each image reference differently for the two.
* The guides are published at `/student-guide/`, `/mentor-guide/` and `/program-manager-guide/`,
  gated to Student level, Mentor level and Administrators only.
* Screenshots are mockups built from the plugin's own markup and the theme's stylesheets with
  invented people, not captures of live Report Cards — real ones carry students' names, emails,
  photos and call notes, and these documents are read by everybody on the program.

**Fixed while writing them**, all three found by documenting what the code actually does:

* The Overview screen said "The program in four modules" and the Modules screen "the four audiences
  above". Both have been wrong since Sponsors was added.
* The Header notices tool described its audiences as "students, mentors, institutions or
  administrators", which has not included sponsors since 1.41.0.
* This readme still described notices as posts edited in the block editor. They have been classic
  editors saving to one option since 1.22.0.

= 1.41.0 =
* **New module: Sponsors**, with a `wpcpm_sponsor` role cloned from Subscriber and a
  `wpcpm_view_sponsor_content` marker capability, wired in exactly like the other audiences —
  it appears in the admin menu, Administrator is granted its capability on activation and loses
  it on uninstall, "Sponsor level" joins the access control in the editor, header notices can be
  aimed at Sponsors, and uninstall moves sponsor accounts back to Subscriber. Like Institutions,
  the module registers its role and reserves its screen; sponsor-facing functionality is not
  built yet.
* **The role schema version moved to 2**, so the new role reaches sites that update by dropping
  in files rather than by re-activating the plugin.
* **Administrator's capabilities are now derived from the roles** instead of being listed by
  hand in two places. Adding an audience meant remembering to grant its marker capability on
  activation *and* remove it on uninstall, and missing the second left the capability behind on
  a site that had removed the plugin.
* Adds `bin/test-roles.php`, which asserts the wiring a new audience needs rather than any one
  behaviour: unique prefixed slugs, one marker capability each, grant and removal reading the
  same list, the module loaded and registered, and the notice audience actually tested. It loops
  over the roles, so the next audience is covered the day it is added.

= 1.40.1 =
* **"Updates" is now "Program updates and announcements"** on both Report Cards. One word left it
  ambiguous next to WordPress's own update notices; the heading now says what the column holds.

= 1.40.0 =
* **Updates now lead the Resources section, on the left.** What is new changes; the guide and
  the assistant do not, and a fixed pair of buttons in the first column trains people to skip
  the half of the section that is different every time they open the page. Swapped in the
  markup rather than turned around in CSS, so what a screen reader hears is what the page
  shows.
* **The sentence under the "Need help?" button follows the button again.** It pinned itself to
  the bottom of its column, which is as tall as the updates beside it — so on a card with a few
  announcements the sentence sat a long way under the buttons it describes and read as
  belonging to nothing.

= 1.39.2 =
* **My course and report form moved above My mentor call** on the Student Report Card. The two
  buttons a student comes to the page to press were at the foot of it, under a section tall
  enough — a month grid beside a day's worth of times — to push them off the screen. Order is
  now My profile and My mentor, then the course and report form, then the calendar.

= 1.39.1 =
* **"Need help?" answers read as prose again.** Providers answer in Markdown whichever way the
  instructions are worded, and `wpautop()` knows nothing about it — so an answer arrived with
  literal `**During weekly syncs:**` around every emphasis and a column of asterisks where a
  list belonged. Bold is now bold and bullets are a real list, in the panel, the block and the
  admin preview alike. Only those two constructs are handled: anything more is a Markdown
  parser, and a wrong one is worse than none.

= 1.39.0 =
* **The Updates column now shows whose card it is, not what the viewer may read.** A program
  manager may read every access level, so mentor announcements were appearing on the Student
  Report Card and student ones on the Mentor Report Card whenever a manager looked at either —
  and a manager had no way of seeing the list a student actually gets. Each card now lists
  Public plus its own audience's level, the same question the "Need help?" button asks.
* The reader's own permission is still checked underneath, so the audience can only narrow the
  list, never widen it: a student sent a mentor-audience render still sees nothing extra.

= 1.38.0 =
* **The Resources section is now two columns: Resources on the left, Updates on the right.**
  Updates lists the newest posts in the *Updates* category, newest first, with a link to the
  category archive when there are more than the column shows.
* **Who sees which update is decided by the post's own "Program access" level.** Public reaches
  everybody; *Student level* reaches students; *Mentor level* mentors; *Institution level*
  institutions; *Administrators only* nobody else. Program managers read every level, as
  everywhere else in the plugin. Enforced twice — by the existing query filter and again on
  every post the column is about to list — so a bypassed query filter cannot leak an
  announcement.
* The example question in the question box is now *"How does the WordPress Credits Program
  work?"*, which any audience could ask, rather than a mentor's onboarding question.
* Shortened the note under the button to "Ask anything about the program or WordPress itself,
  and get an answer."
* No category called *Updates* means an empty column that says so, not a broken one. The plugin
  does not create the category: it does not own the site's taxonomy.

= 1.37.0 =
* The Slack logo in the Resources section is now exactly as tall as the buttons beside it, so
  the three read as one row.

= 1.36.0 =
* The Slack logo in the Resources section is now a plain link rather than a button: no border,
  no fill, just the logo. A box around a logo fights the logo, and the artwork reads as
  somewhere to go on its own.
* Drawn a little larger to make up for having no button around it.

= 1.35.0 =
* The Resources section's Slack button now carries Slack's published logo — the four-colour mark
  and the "slack" wordmark together — taken from Slack's own brand asset rather than drawn here.
* Still exactly the height of the labelled buttons beside it, scaled from the logo's own
  proportions so it is never stretched.

= 1.34.0 =
* The Resources section on each Report Card now opens with a Slack button carrying Slack's own
  mark, before the guide: the students channel on the Student Report Card, the mentors channel
  on the Mentor Report Card.
* Icon only and the same height as the labelled buttons beside it — 54px either way — and
  outlined rather than filled, because Slack's mark keeps its own four colours and needs a
  light background to read against. It is named for screen readers, which get nothing from an
  icon.
* This is the one brand mark the plugin ships. `WPCPM_Icons` otherwise draws its own line
  icons on purpose; Slack's guidelines permit their mark for pointing at Slack, so it is
  reproduced unmodified and kept out of the line-icon set so it cannot be used as one.
* The two dashboards no longer filter the section through `wp_kses_post()`, which strips
  `<svg>` outright and would have left an empty button. A check now fails if either starts
  doing so again.

= 1.33.0 =
* The Resources section on each Report Card now links that reader's own handbook guide, before
  the "Need help?" button: **Student guide** on the Student Report Card and **Mentor guide** on
  the Mentor Report Card. Filled rather than outlined, and first, because the guide is the
  thing to read and the assistant is for when it has not answered the question — the same
  relationship "Open your course" has to "Open your report form".
* The guide shows whether or not an AI provider is configured, and whether or not this
  audience may ask questions. Those govern the button beside it and nothing else; hiding a
  handbook link because an API key is missing would make no sense to anybody looking at the
  page.
* The "Need help?" button requires a provider again, so it can no longer open a panel that
  has nothing to answer with.

= 1.32.0 =
* **Citations now show the actual page, not just the domain.** Google returns an opaque
  redirect and gives only the hostname, so the list read "wordpress.org" four times over.
  Following each redirect one hop — about 0.2 seconds apiece — yields the real address, which
  is now shown under a name taken from the page itself: "Certificate graduation —
  make.wordpress.org", with the full path beneath it. Links go straight to the page rather
  than through Google.
* That also makes the site check stronger: it is now made against where the page actually is,
  rather than against a label the provider supplied. A citation labelled wordpress.org that
  resolves somewhere else is refused. A redirect that cannot be followed still yields a
  citation, named by its host.
* The same page cited twice arrives as two different redirects; it is now shown once.
* The privacy note is prefixed "NOTE:" and set in italic, in the panel and on the page.

= 1.31.2 =
* **Fixes a busy AI service being reported as a retired model.** "This model is currently
  experiencing high demand" was matched on the word "model" and turned into "no longer
  available — change it in the plugin settings", which sent people to change a setting that
  was correct. Failures are now told apart by HTTP status, plus a short list of unmistakable
  phrases for the retired case, because Google has answered 404 for one retired model and 400
  for another.
* A reader now gets the right advice for each: busy means try again in a minute, a refused
  request points at the API key, and only a genuinely unavailable model sends anybody to the
  settings. The provider's own wording is kept alongside either way.
* **Retries once when the service is busy.** "High demand" is transient and fails within a
  second or two, so there is room to try again inside the same request rather than asking
  somebody to press the button again. Only on a fast failure — after a slow one there is no
  time left, and a second attempt would only trip the browser's own limit.

= 1.31.1 =
* **Fixes answers timing out.** The provider was given 25 seconds; measured against this
  site's own key, three ordinary questions took 9.4, 18.7 and 24.1 seconds, so the limit was a
  coin toss rather than a margin and the failure was a cURL 28 with no answer. Raised to 60.
* **The panel shows a progress bar and a running count of seconds while it waits.** A
  ten-to-twenty-five second wait behind a line of static text reads as a page that has
  stopped. Indeterminate on purpose — nothing reports how far through a web search is, and a
  bar that pretended to know would stall at 90%. It also gives up on its own after 75 seconds
  rather than spinning for ever.
* **Corrects settings text that described a design the plugin no longer has.** Four claims
  were false once the local copy was removed: that there is a private copy of the
  documentation, that it works with no provider at all, that nothing you type leaves the site,
  and that there is a daily refresh keeping an index. A check now fails if any of them
  reappears.
* Removes the note about contact details coming from a mentor's WordPress.org profile.

= 1.31.0 =
* **Google AI Studio is the only answer provider.** The OpenAI-compatible path, the endpoint
  field and the table of free services are gone; a stored provider that no longer exists is
  migrated across. Google's is the one that searches the web for free and returns the
  citations this arrangement depends on.
* **Fixes answers arriving with no citations and marked unverified.** The token ceiling was
  800, and Gemini returns `groundingMetadata` *empty* when a grounded answer is cut off — so
  the limit was not shortening answers, it was silently destroying every source. Raised to
  2048.
* **Fixes the citation filter reading a field that does not exist.** The API documents the
  source domain as `web.domain`; it actually arrives in `web.title`, with an opaque redirect
  as the URI. The filter read `domain`, found nothing, and would have refused every real
  citation. It now reads either, and refuses anything it cannot place on one of the four
  sites.
* The default model is `gemini-flash-latest`, an alias. Both `gemini-2.0-flash` and
  `gemini-2.5-flash` were retired during development, each time leaving sites with a refused
  request and no answer; an alias cannot be retired out from under a site. Sites on a retired
  default are moved across automatically, and a model chosen by hand is left alone.
* A provider error mentioning the model now says the model needs changing, instead of
  suggesting you try again — which for a retired model would never have worked.
* **Fixes the Resources section never appearing on the Student Report Card.** Choosing
  "Students and institutions as well" changed nothing, because the section had also been
  removed from that page outright — two mechanisms for one decision, and the setting lost. The
  audience setting is now the only authority.
* Removes the note about contact details coming from a mentor's WordPress.org profile.

= 1.30.1 =
* **Fixes settings that would not save.** The save handler read from a hand-written list of
  field names, and twenty-one settings the form renders were missing from it — including the
  AI provider and its API key, the whole mentor-checker card, the linked-table names, past
  student statuses and the student inactive rule. Those fields rendered, accepted what you
  typed, posted it, and discarded it without a word.
* The list is gone. The handler now derives the fields from the settings defaults, so adding a
  setting can no longer mean forgetting the one place that lets it through.
* New `bin/test-settings.php` drives the real save with a real request and fails if any
  rendered setting does not survive the round trip — plus the awkward shapes: an unticked
  checkbox, the masked API key being posted back, an unknown provider, and a non-http endpoint.

= 1.30.0 =
* **Need help? no longer keeps a copy of the documentation.** The AI provider searches
  wordpress.org, make.wordpress.org, learn.wordpress.org and developer.wordpress.org itself.
  Gone with it: the passage table, the resumable sync, two cron hooks, the progress bar, the
  per-source switches and about 1,500 lines of retrieval code. Nothing is stored, so nothing
  refreshes and nothing goes stale.
* **What that costs, stated plainly.** With no provider configured there is now no answer at
  all — there is nothing left to fall back on — and the screens say so rather than returning
  an empty box.
* **Two safeguards in place of the guarantee.** Google's search tool has no site filter, so
  the sites are named in the system instruction and every citation that comes back is checked
  against them: anything from elsewhere is not shown, and an answer citing nothing from those
  sites is **marked as unverified** in the panel, on the page and on the admin screen. The
  host check matches on the host's tail, so `developer.wordpress.org` counts and
  `wordpress.org.example.com` does not.
* Gemini's default model is now `gemini-2.5-flash`, which grounds noticeably better than
  2.0-flash and is on the same free tier. The OpenAI-compatible provider still works and reads
  citations where the service returns them — OpenRouter's `:online` models and Perplexity do;
  a plain chat model returns none and its answers are marked unverified.
* Uninstall removes everything every version of this module ever created, including the table
  and schedules belonging to classes that no longer exist. A check in `bin/test-handbook.php`
  fails if any of those names is dropped from the removal path.

= 1.29.1 =
* Removing the plugin now removes everything Need help? created. Three things were being
  left behind: the per-person rate counters, the posts an earlier version stored pages in —
  invisible to `get_posts()` because that post type is no longer registered — and the
  assistant's own page. The page is deleted only when nobody has written on it.
* A check in `bin/test-handbook.php` now fails if the module writes an option, transient,
  table or cron hook that the removal path does not name. The mistake it catches is a
  forgotten line, which nothing else would surface until somebody deleted the plugin.

= 1.29.0 =
* **Need help? now answers from all of the WordPress documentation, not just one handbook**:
  make.wordpress.org's team handbooks, Learn WordPress courses and lessons, the
  documentation site, the nine developer handbooks and the wordpress.org pages — including
  wordpress.org/education/. Around 3,200 pages. Each source is switched on or off on its own
  screen, and switching one off removes what it contributed.
* Two things are left out on purpose and can be added if wanted: developer.wordpress.org's
  code reference — 12,230 functions, hooks, classes and methods, four times everything else
  combined — and make.wordpress.org's meeting notes.
* **A real search index.** Passages now live in their own table with a MySQL full-text index,
  which narrows thirteen thousand of them to a couple of hundred candidates before BM25
  re-ranks those in PHP. Holding the whole corpus in memory was right for one handbook and
  an out-of-memory error at this size.
* **A progress bar, because the first read takes minutes.** The read happens in slices and
  is resumable: the browser advances it while somebody is watching, cron advances it when
  nobody is, and it survives leaving the screen. It reuses the plugin's existing progress
  component rather than adding a second one.
* Everything user-facing is called **Need help?**. On the Mentor Report Card it is a
  **Resources** section built from the same markup as "My course and report form".

= 1.28.1 =
* The assistant is called **"Need help?"** everywhere it appears.
* **Mentors and program managers only, by default.** The handbook describes running the
  program rather than being on it, so students no longer see the assistant unless somebody
  widens the audience deliberately — the setting still offers students and institutions, or
  anybody logged in, or managers alone.
* Removed from the Student Report Card entirely. Restricting the audience would have hidden
  it from students anyway, but a manager inspecting a student's card would still have seen it
  on a page that belongs to the student. It lives on the Mentor Report Card.
* The button wears the plugin's own `wpcpm-button` classes, so the companion theme's
  treatment of the course and report buttons covers it rather than there being a second kind
  of button on the same card.

= 1.28.0 =
* **The handbook assistant is no longer a page.** It opens in a slide-over panel from "Ask
  the handbook" in the site header, and from a prompt at the foot of both Report Cards.
  Questions arise while somebody is looking at a student record, not on a page they have to
  remember exists — and the answer now appears beside what they were reading instead of
  replacing it.
* Answers arrive over a REST endpoint, which makes the same access decision the page does:
  logged out, outside the audience, or the assistant switched off all refuse it.
* **A new OpenAI-compatible provider**, so Groq, OpenRouter, Cerebras, Mistral, Together and
  GitHub Models all work with one implementation. Trying a different free service — or a
  different model on the same one — is now two settings and a key rather than new code. The
  settings screen lists four services with a working endpoint and model for each.
* The rules the model must follow are sent as a system message rather than folded in above
  the extracts, which chat models follow noticeably better. Gemini gets them as a
  `systemInstruction` for the same reason.
* No page is created on activation any more. An existing one keeps working and the tool
  screen says so, in case you would rather delete it.
* The inline block submits back to whatever page it is on, so it works anywhere and needs no
  page of its own — and it carries the rest of the query string with it, so asking a question
  from a dashboard does not lose which student was being looked at.

= 1.27.0 =
* Switching the handbook assistant off now removes it from the site rather than leaving a
  page that renders nothing. The page is unpublished, so it drops out of navigation menus and
  the sitemap and has no URL to land on, and the shortcode renders nothing anywhere for
  anybody — a manager included. Switching it back on republishes the same page, with its slug
  and anything written on it intact.
* A page unpublished by hand stays unpublished. Only a *change* of the setting moves it, so
  the plugin never overrules an administrator who did it deliberately, and upgrading never
  moves a page at all.
* **The admin section "Tools" is now called "Modules"** — the menu item, the screen and the
  Overview heading. The page slugs are unchanged, so existing bookmarks still work.

= 1.26.0 =
* **New: the Education Handbook assistant.** A private question box over a synced copy of the
  WordPress Education Handbook, for logged-in people on the program. Ask it something and it
  finds the handbook sections that answer it, quotes them, and links back to make.wordpress.org.
* The handbook is read through the REST API that make.wordpress.org already publishes — no
  scraping — and split at the headings its authors wrote, so a question about one thing gets
  the section about that thing rather than a page covering five. 58 pages become 219
  answerable sections. Refreshed daily; pages that have not changed are skipped.
* Ranking is Okapi BM25, computed in PHP at the moment a question is asked. There is no
  index to keep in step and nothing to go stale, because the corpus is small enough that
  scoring all of it is cheaper than maintaining an index of it.
* **It works with no AI provider at all**, and that is the default: answers are quoted
  straight from the handbook and nothing you type leaves the site. Setting a provider and key
  in Settings has the answer written in prose instead, grounded strictly in the retrieved
  sections and told to admit when the handbook does not cover the question. Google AI Studio
  (Gemini) ships as a provider; `wpcpm_handbook_generate` accepts others.
* Whichever provider is on, the quoted answer is always produced first, so a provider that
  is down, misconfigured, rate-limited or out of quota degrades the answer instead of
  removing it. Per-person hourly and site-wide daily ceilings keep a free tier from being
  spent in an afternoon.
* Switchable on and off in Settings, with its own audience setting: students, mentors,
  institutions and managers by default, or anybody logged in, or managers only. Never anybody
  logged out, whatever the setting says, and the shortcode refuses to draw for a visitor even
  on a public page.
* The tool screen shows what is stored, when it was last read, where people ask, and a
  try-a-question box that shows exactly what somebody on the program would get.

= 1.25.2 =
* Recovers header notices that stopped appearing after 1.24.0. Moving notices off the
  block-editor post type read the post and nothing else, so an audience whose post had been
  created empty lost sight of text that was still sitting in the settings option from before
  any of this existed. The recovery now falls back to that older copy.
* The recovery is a revision counter rather than a one-shot flag, so a site it has already
  half-finished can be revisited — the same lesson the page-title rename learned. Anything
  written in the current editor always wins, so a rewritten notice is never reverted.

= 1.25.1 =
* Fixes mentors and students who are linked to an Airtable record but do not hold the
  Mentor or Student role still landing on the wp-admin dashboard. Both redirects tested the
  role alone, while everything else in the plugin — including the toolbar link to the page —
  counts the record link too. The result was an account the plugin recognised as a mentor
  everywhere except the one place that would have taken them to their page.
* Somebody whose mentoring has ended is still not redirected. Going inactive removes the
  role and leaves the Airtable link in place on purpose, and the page it would send them to
  has nothing on it.
* Administrators are unaffected. Starting from the wider test brings in administrators the
  sync has linked to a mentor record, and the existing capability exclusions keep them in
  wp-admin where they need to be.

= 1.25.0 =
* **Mentors and students are now actually redirected to their Report Card when they log in.**
  They always ended up there, but by way of the wp-admin dashboard: the `login_redirect`
  filter stepped aside whenever a destination had been "requested", and core's login form
  carries a hidden `redirect_to` whose default value is the admin URL — so it stepped aside
  on every single login and never redirected anybody. The `admin_init` fallback covered for
  it one hop later, which is why nothing looked wrong.
* The admin root now counts as "nowhere in particular", so a real destination is still
  honoured: a mentor or student bounced off gated content through the login form still lands
  on the page they asked for, and so does anyone sent to a specific admin screen.
* `WPCPM_Request::is_explicit_redirect()` holds that decision once, rather than the same
  subtle test being written out in both dashboards where one could be fixed and the other
  forgotten.
* New `bin/test-login-redirect.php`, driving the filter with the arguments `wp-login.php`
  really passes rather than the ones that would agree with the old code.

= 1.24.2 =
* Fixes "Email me the mentor invitation" sending the student invitation. The two buttons post
  the audience, and the handler was reading it from the query string, so it fell back to
  `student` whichever one was pressed. `WPCPM_Request` gains a `posted_key()` to go with the
  `posted_id()` it already had.
* `bin/check-references.php` now also reports a `$_GET` read inside a form handler. This one
  could not fail loudly — the read returns the fallback and the feature carries on doing the
  wrong thing — so it is worth a static check rather than a hope.

= 1.24.1 =
* Fixes the sample invitation printing the same address twice. WordPress puts two URLs in
  that email — a one-use reset link carrying a key, then the plain login page — and the
  sample stood the login URL in for both, so it looked like a duplicated link. The stand-in
  now keeps the real shape and is visibly an example. A real invitation was never affected.
* The invitation now says which of those two addresses does what. Unlabelled, a keyed
  reset link followed by a bare login URL reads as the same address twice.

= 1.24.0 =
* **Bookings and cancellations carry a calendar invitation.** Two people agreeing a
  half-hour slot across two timezones previously had to transcribe a date out of an email
  and type it into a calendar by hand, twice, correctly. The cancellation reuses the
  booking's own event ID, so it withdraws the call rather than adding a second one.
* **Mentors can say where the call happens.** A "Where we meet" link beside your
  availability goes into both confirmations and the calendar invitation, so nobody has to
  arrange the video room separately afterwards. A mentor who has not set one is told so in
  their own confirmation, where it is still fixable.
* **A reminder goes out the day before.** A call booked four weeks ahead is a call somebody
  forgets, and the confirmation was read a month earlier. Nothing is sent for a call booked
  inside the reminder window — there the confirmation *is* the reminder.
* **Mail is sent in the recipient's language.** Templates are now built inside
  `switch_to_user_locale()`. They were translated in the site's language before, so a
  student whose profile is Italian was reading English.
* **Replies go somewhere.** Every notification carries a `Reply-To` for the other party, so
  answering "Call booked with Moldir" reaches Moldir instead of `wordpress@`.
* **The invitation email says what it is.** Students and mentors get different copy naming
  the program, what they are to it and what to do first, around the username and reset link
  WordPress generates. A bare "Login Details" from a site you do not recognise reads as
  phishing. It also says how to get a fresh link once the old one has expired.
* Invitations are queued and sent a few at a time instead of ninety inside one sync request,
  where a timeout or a mail limit could swallow an unknown number of them.
* Wording fixes: the student's own confirmation no longer refers to them in the third
  person; a canceled call tells mentors and students each what *they* can do next; a
  cancellation states its timezone and carries a link, as the booking already did; and an
  administrator canceling on someone's behalf is named as "a program manager" rather than
  by a name the student has never seen.
* The cancellation subject now names the date, so a mentor with three booked calls can tell
  which one it is about.
* A call crossing midnight in the reader's timezone states the end date. "11:45 pm – 12:15
  am" previously read as ending fourteen hours before it started.
* New on Settings: send yourself a sample invitation as either audience, and a log of recent
  mail with what the site did with it. "The student says they got nothing" was unanswerable
  before, because every caller discarded what `wp_mail()` reported.
* New filters: `wpcpm_mail` over subject, body and headers together; `wpcpm_call_ics` over
  the calendar file; `wpcpm_call_reminder_lead` over the reminder's lead time.

= 1.23.0 =
* **The mentor page is titled "Mentor Report Card"**, matching the student side. An install
  that still has the "My Students" title the plugin created is renamed once on upgrade; a
  page renamed by hand is left alone. The slug stays `mentor-dashboard` — the theme matches
  its template on it.
* The mentor's name steps down from `<h1>` to a paragraph, the same shape the student card
  uses for the student's name. It was the page's heading while the page had no title of its
  own; now the title is, and two `<h1>`s is not a document outline.
* The toolbar link and the "Mentor landing page" setting name the page by its new title
  rather than pointing at a "My Students" page that no longer exists.

= 1.22.0 =
* **Header notices go back to a plain editor.** Each notice is written in the classic editor
  on the Header notices tool screen — four editors and one Save button — instead of opening
  the block editor on a hidden post. A notice is a paragraph with a link in it; it did not
  need revisions, autosave or a screen with no way back to the tool.
* Notices are stored as markup in one option again. Anything written while they were posts is
  moved into that option once, on upgrade, with its block markup rendered to HTML on the way
  in, so nothing already published is lost. The old posts are left alone rather than deleted.
* The classic editor keeps its media button, so a notice can still hold links, images, lists
  and headings. It is deliberately not the `teeny` editor, which drops that button.
* Saving reports its outcome through the one-shot message store rather than a query
  argument, so reloading the screen no longer claims a save that did not happen.

= 1.21.0 =
* **Coding standards: both projects now pass WordPress Coding Standards with zero
  violations**, down from 194 in the plugin and 11 in the theme. `phpcs.xml.dist` in each
  pins the standard, so `phpcs` with no arguments is the whole check, and
  `sh bin/check-standards.sh` runs it. Every exclusion in those files is a path WordPress
  dictates the name of, or a sniff whose default does not apply here — bounded dashboard
  meta queries, and `numberposts` ceilings that exist so a runaway query cannot hang a page
  — each with the reason recorded. Nothing silences a category of mistake.
* Beyond formatting, the sniffs surfaced eleven real things: a stale docblock missing four
  keys `render_row()` reads, three parameters documented under names the signature no longer
  used, four missing translator comments on placeholder strings, an unparenthesized
  `||`/`&&` expression that was correct but only if you know the precedence table, and — the
  one worth having — **several `phpcs:ignore` annotations that had drifted onto the line
  above the code they described and silently stopped applying.**
* That last one pointed at real duplication: eleven near-identical
  `isset($_GET[…]) ? sanitize(…) : ''` reads, each needing its own annotation. They now go
  through `WPCPM_Request`, which writes the unslash-sanitize-default sequence once and keeps
  the "this is view state, not form data" note beside the three reads it describes. No raw
  `$_GET` access is left outside it.
* Added `VariableAnalysis` to both rulesets, and it immediately earned its place: renaming
  three reserved-keyword parameters (`$class`, `$default`, `$list`) left dangling references
  in the method bodies that `php -l` cannot see and that would have returned null at
  runtime.


* **Header notices are edited in the block editor.** Each notice is now a post, which is
  what makes that possible — blocks are a property of post content and the editor is bound
  to a post, so no settings field can host one. Revisions, autosave and the media library
  come with it. The tool screen lists the four with their status and an Edit link.
* Existing notices are carried over automatically: whatever was written in the old settings
  fields becomes the post's content on first load.
* The post type is private and every capability maps to `wpcpm_manage_program` with
  `map_meta_cap` off — mapping to the `post` type would have let any editor or author on the
  site rewrite a notice shown to every student.
* **Notices now appear at the top of the page's content** rather than above the site
  header. On `wp_body_open` they landed over the chrome, outside the page; a notice belongs
  at the top of the page somebody is actually reading. Rendered once, on the main singular
  query — `the_content` also runs for excerpts, other loops, feeds and REST responses.

= 1.20.0 =

* **Header notices moved to Tools**, at *WPCredits Program → Tools → Header notices*, and
  each notice now has a **WordPress editor** — links, images, lists, emphasis, a
  subheading. Writing a notice is something somebody sits down and does, which is what the
  Tools section is for; the Settings screen is the program's plumbing and nobody visits it
  to talk to students. It also gives four notices room for an editor, which a settings-table
  row does not have.
* Only the screen moved. The notices still live in the same shared option, so nothing
  needed migrating and anything already written is where it was.
* `wp_kses_post()` was already the filter, so images and links needed no change to survive
  — but the front end did: an uploaded image is whatever size it was uploaded at, and a
  full-width screenshot would have turned a one-line notice into a page. Images are capped,
  and lists, headings and blockquotes are styled for a band a few lines tall.
* Two things the tests caught while writing this. The editor was configured `teeny` *and*
  given a toolbar, which cancels both the toolbar and the media button — so images could not
  have been inserted at all. And the notices test had been asserting its own `wp_kses_post`
  stub rather than WordPress's tag list, reporting that images were stripped when they are
  not; the stub now mirrors the post context, and a separate assertion pins the filter the
  plugin actually calls so nobody narrows it later.

= 1.19.1 =
* **Fixed: "That call is canceled and the slot is free again" never went away.** Outcome
  messages travelled as a query argument — `?wpcpm_call=cancelled` — so the argument stayed
  in the address bar and the message came back on every reload, and for anyone the URL was
  shared with. On both dashboards, as reported.
* They are **one-shot messages** now: queued for the user, read and deleted by the page the
  redirect lands on, gone from the next one. The same defect was in three more places and
  all four are converted — saving availability, adding or deleting a note, and saving the
  student's own details. Which mentor or student a manager is inspecting stays in the URL,
  because that is a description of the page and *should* survive a reload.
* The Airtable error detail moves into the message too. It used to be url-encoded into the
  address bar, which made for a URL a screen wide as well as a stale error that reappeared
  on every reload.
* **New: the student's email on their own Student Report Card**, in My profile, where the
  mentor's view of them already had it. Airtable's value when there is one and the account's
  own otherwise — about three in ten students have no email in the program records. Not
  editable there: it is the account's email, and writing it back to Airtable from this page
  would put the two out of step with no way to tell which is right.
* `php bin/test-flash.php` covers the behaviour: shown once, gone on reload, channels
  independent, one user's message invisible to another, and no status argument left in any
  redirect.
* `bin/test-handlers.php` now reads the plugin's require list from the bootstrap instead of
  keeping its own copy. The copy had already drifted — adding the message store broke every
  handler test with "Class not found" until it was noticed.

= 1.19.0 =
* **New: header notices, one per audience.** Students, Mentors, Institutions and
  Administrators each get a notice written under **Settings → Header notices**, shown at
  the top of the site to the people it is for and to nobody else. See *Header notices*
  above.
* Somebody in two audiences sees both, in audience order. Withholding one from a person who
  holds two roles would hide it from the group likeliest to need it.
* Rendered on `wp_body_open` so it works under any theme, with
  `wpcpm_notices_auto_render` to take the placement over. The stylesheet is registered
  always and enqueued only when a notice will actually appear, because `wp_body_open` fires
  after `wp_head` and is far too late to ask for one.
* `php bin/test-notices.php` asserts the targeting across all seven combinations of
  audience, plus that an empty notice is off, that a logged-out visitor sees nothing, and
  that a script tag never reaches the database while a link survives. That last pair is the
  reason notices are filtered on save and not only on output.

= 1.18.1 =
* **My mentor call spans the card**, like My course and report form, instead of sitting in
  the page's right-hand column under the mentor. A month grid wants more width than half a
  card gives it — that column was the reason the calendar squares were as small as they
  were.
* Inside the section it now splits into two of its own: **booked calls on the left, the
  picker on the right** — the calendar, its slots and the timezone control. They answer
  different questions ("when am I speaking to my mentor" and "when could I"), and the one
  that needs room now has it. Back to a single column at 900px, with the divider becoming a
  rule above.
* A student with nothing booked gets *Nothing booked yet* in the left column rather than an
  empty half.
* Four paths through that section finish early — no mentor linked, not bookable, nothing
  open, or a full calendar — and each is now two `<div>`s deep. They share one closing
  helper rather than each closing by hand, because the one that eventually forgets would
  pull the whole card apart.

= 1.18.0 =
* **Pressing a time slot now shows that it is working.** It used to look like nothing had
  happened until the page came back, so students pressed again — and a second press can
  hit the booking lock and be told *"another booking was going through at the same
  moment"*, when their own first press was what was going through. The pressed slot reads
  **Booking…**, the other slots step back, a line under them says *Booking your call — one
  moment*, and a repeat press is swallowed rather than posted.
* The same guard covers every form on the two dashboards: canceling a call, saving
  availability, changing timezone, and the student's inline detail edits.
* One trap worth recording, because getting it wrong would have been much worse than the
  problem being fixed: **a disabled control is not submitted**, and the slot buttons carry
  the value being submitted (`name="start"`). Disabling the pressed button inside the
  submit handler would have posted the form with no slot in it and broken booking outright.
  The pressed button is therefore disabled from a deferred callback, after the browser has
  built the request; only the buttons that carry nothing are disabled immediately.
  `php bin/test-submit-guard.php` asserts exactly that, so it cannot be quietly undone.
* Going back to a booking page from the browser's cache clears the busy state, rather than
  restoring a page where every slot is disabled and one still reads "Booking…".
* All of this is a progressive enhancement: with no JavaScript the form posts as it always
  did, and nothing on screen claims otherwise.

= 1.17.2 =
* **Your availability for calls is now closed unconditionally** — at rest, with nothing
  set, and after a save. 1.15.1 closed the first two but kept it opening on save, because
  the confirmation was rendered inside the panel and a shut panel would have swallowed it.
  The confirmation moved outside the disclosure, so it reads with the panel closed and the
  panel no longer has a reason to spring open at all.

= 1.17.1 =
* **Fixed a fatal error on every booking, cancellation and timezone change.** The redirect
  at the end of those handlers used `self::ANCHOR`, but that constant belongs to
  `WPCPM_Call_Calendar`, not `WPCPM_Mentor_Calls` — so the request died with *Undefined
  constant* instead of returning to the calendar. Present from 1.13.1; **anyone on 1.13.1
  to 1.17.0 should update.** No data was lost: the booking itself was written before the
  redirect, so calls made during that window exist and appear on both dashboards.
* Two checks added under `bin/`, because `php -l` cannot catch this and nothing here was
  executing the handlers:
  * `php bin/check-references.php` — resolves every `self::` and `WPCPM_X::` reference
    against what the class actually declares. An undefined class constant is a runtime
    fatal, so it parses cleanly and then takes the site down on the one request that
    reaches it. 1166 references checked.
  * `php bin/test-handlers.php` — runs every `admin-post` handler against stubbed
    WordPress and fails on a PHP `Error`. A redirect or a `wp_die()` is a pass; both are
    normal. Confirmed to fail on the bug above and pass with it fixed.

= 1.17.0 =
* **Copy hours between days** in the availability editor's Weekly hours: pick a day, pick
  *every day*, *weekdays* or *the weekend*, press Copy. It fills the form in; it does not
  save. The mentor still presses **Save availability**, which means a copy that went to the
  wrong days is undone by not saving — a better escape hatch than an undo button.
* Copying a day with no hours in it is **refused**, with a line saying so. Copying blanks
  across the week is a fair reading of "copy" and a destructive surprise; a mentor reaching
  for this wants to fill days in. Days are still cleared one at a time by hand.
* A half-filled window — a start with no end — is not treated as empty, and copies as it
  stands. The form already tolerates a half-finished row and drops it on save.
* The control is scripted and has nothing to degrade to, so it is rendered hidden and
  unhidden by the script: nobody is shown a button that would do nothing. This is also why
  the calendar script now loads on the mentor page, where its other job — offering the
  browser's timezone — simply finds nothing to do.

= 1.16.2 =
* The page is **Student Report Card**, and the section holding the student's own photo,
  name and details is **My profile**. The toolbar and menu items that point at the page
  follow its title.
* Existing pages are renamed automatically. This is the third title this page has had, and
  the revision counter added in 1.16.0 is what makes it a one-line change: a site on any
  earlier revision converges on the current title exactly once, and a page renamed by hand
  is still left alone.

= 1.16.1 =
* The student's photo and name moved **inside the My program section**, under its heading
  and above its table — built with the same three-element shape as the mentor card in the
  column beside it, so the two columns are a pair rather than merely similar.
* Two things follow from that, and both are changes from how it looked as a page header:
  the portrait is **88px**, matching the mentor's, and the name takes the mentor name's
  type rather than 22px. At page-header size inside a column the left column shouts while
  the right one whispers. Say the word if you want either back.
* The name is a paragraph, not an `<h2>`. It sits inside the section whose `<h3>` is
  directly above it, so the heading it used to be nested a higher outline level inside a
  lower one — an outline error rather than a styling preference.
* The section renders unconditionally now, because it owns the identity card. A student
  waiting on their first sync used to get a page with nobody on it; they now get their own
  name and photo, and a notice where the table would be rather than ten rows of "Not set".

= 1.16.0 =
* The student page is **My Profile**, and its sections are first person: **My program**,
  **My mentor**, **My mentor call**. Only the first of those was asked for; the other two
  follow because "My program" beside "Your mentor" in one card reads as an unfinished edit
  rather than a choice. Each is one string if you want it back.
* **New section: My course and report form**, below the two columns, holding a button to
  the Learn WordPress course for the student's track and the report-form button that used
  to sit under the program table. Those are the only two *actions* on the page —
  everything above them is reference — and a button at the foot of one column reads as
  belonging to that column rather than to the page. The section renders nothing at all
  when neither link exists.
* **Fixed a flaw in the page-rename migration.** It recorded a boolean, so an install that
  had already been renamed once was skipped for ever and would have kept a title two
  revisions old. It records a revision number now, so each rename runs exactly once
  however many there have been — and every past title is listed, so a site on any earlier
  revision converges on the current one. A page renamed by hand is still left alone.

= 1.15.2 =
* The contribution team's icon **labels the row** — before the words "Contribution team",
  where every other row's icon is — instead of sitting in the value beside each team name.
* **A question mark labels the row when no team has been chosen.** That is a different
  fact from an empty value, and the one a mentor scanning a list of students is looking
  for, so it stays at full strength where the other icons dim on an empty row.
* A team that is set but not in the icon map gets a neutral team glyph rather than a
  question mark — one *has* been chosen, and a question mark would say otherwise.
* **Mentors' team badges have no icons.** They share the same renderer as the student's
  team value, and that renderer is back to plain linked names.
* A student on more than one team takes the first recognized one for the label. The label
  has room for one; the value lists them all.

= 1.15.1 =
* **Your availability for calls** starts closed, whether or not anything is set. It used
  to unfold itself for a mentor who had set nothing, on the theory that an empty schedule
  is a job to do — but it sits beside the diary now, and a form that opens on arrival
  every time is a form that has to be closed every time. It still opens after a save,
  because the confirmation renders inside it.
* An empty schedule says so in `#daa39b` rather than the same grey as the rest of the
  supporting text. With the panel closed by default that summary line is the only thing
  that will tell a mentor they are not receiving calls, so it stops reading as a footnote.
  Set on `--wpcpm-attention`, so a theme can restate it in one place.

= 1.15.0 =
* **Contribution teams carry their team icon**, on both dashboards, sized and colored to
  sit with the contact-row icons rather than at the 20px Dashicons ships at.
* These are **Dashicons**, WordPress's own set — *not* icons from make.wordpress.org,
  which does not publish any. Its team list is plain headings, every team site serves the
  same generic WordPress favicon, and the team badges on a wordpress.org profile are text
  chips. So the mapping is authored here, from the icons WordPress itself uses in wp-admin
  for these concepts. GPL like this plugin, and every slug was checked against the shipped
  `dashicons.min.css` — a slug that does not exist renders as a blank box, which is the
  kind of thing nobody notices until a screenshot. Filter:
  `wpcpm_contribution_team_icons`.
* All 25 contribution areas in Airtable get an icon, including the seven that have no
  make.wordpress.org page — BuddyPress, bbPress, GlotPress, Mobile, Tide, Data Liberation
  and DEIB. Those stay unlinked, as before, but are no longer bare text.
* `dashicons` is declared as a dependency of the dashboard stylesheet rather than
  enqueued at each render site, so it cannot be forgotten at one.

= 1.14.3 =
* Shortened the **WordPress.org profile** row label to **WordPress.org**, matching the
  other contact rows, which name the service rather than the kind of page. Applied on
  both dashboards, in the student's own editor, and to the matching column on the Mentor
  Status Checker screen so the two do not disagree.

= 1.14.2 =
* **Edit** now sits at the right edge of the table rather than beside the value: the
  value takes whatever width is left. Opened, the form spans the cell so its fields read
  left to right like any other form.
* Renamed "Slack name" to **Slack**.
* The contact rows carry small icons — Email, Slack, WordPress.org, Website,
  GitHub, and the internship dates — on both the mentor's student details and the
  student's own two tables. They are drawn from primitives and stroked in
  `currentColor`, so they take the colour of the text beside them and cannot come out
  wrong in a theme nobody has seen. None is a brand mark: Slack gets a channel hash,
  GitHub gets code brackets. Shipping a service's own logo would mean redistributing a
  trademark, which this does not need to do.
* The icons are echoed rather than passed through `wp_kses()`, deliberately: every byte
  is a static array in `WPCPM_Icons`, and kses lowercases attribute names — SVG is
  case-sensitive, so `viewBox` would come back as `viewbox` and every icon would lose
  its scaling.

= 1.14.1 =
* **Editing moved into the table.** The four details a student maintains are now edited
  where they are shown: each row in *Your program* carries an **Edit** link that opens a
  one-field form beside the current value. The separate "Your details" section is gone.
  One small form per field rather than one for all four — a save touches only the field
  that was edited, where a single form rewrote all four every time and turned a typo in
  one into a rewrite of the others.
* **Fixed: the student's name and photo appeared at the foot of the page**, under the
  administrator's "view as" control. The two-column grid was declared on the dashboard
  root, so the identity header and that control were unplaced children competing with
  explicitly-placed sections — grid put them in the first *free* row, which was below
  both. The two-column region now has a container of its own, which also means anything
  added to the page later cannot land in the grid by accident.
* **Fixed: the mentor's contact table was indented** under their name instead of lining
  up with their photo. It was inside the card's text column; it is a sibling of the whole
  card now, so it starts at the same left edge the portrait does.
* **The existing page gets renamed.** `ensure_page()` only sets a title when it *creates*
  the page, so 1.14.0's switch to American spelling left "My Programme" on every install
  that already had one. A one-time migration renames it, and only if the title is still
  the one this plugin set — a page renamed by hand is left alone. The slug is untouched.
* The mentor's own contribution teams link to their team sites too, matching the
  student's.

= 1.14.0 =
* **American English throughout**, in the interface and in the code's own comments. "Programme" is "Program" everywhere, including the student page's title and the *My Program* menu item, along with the other British spellings the codebase had picked up (organised, recognised, normalised, behaviour, colour, centred, grey, labelled, travelling, cancelled).
  *One thing this does not change:* the student page's stored title. `ensure_page()` only sets a title when it creates the page, so an existing install keeps "My Programme" until it is renamed under **Pages**. The slug is `student-dashboard` and is untouched, so nothing breaks either way. Identifiers, database keys and query values were deliberately left alone — `_wpcpm_call_cancelled_by` names live data, and renaming it would orphan every canceled booking on disk.
* **Students maintain four of their own details**, and the changes go back to Airtable: WordPress.org profile, Slack name, contribution team and personal website. Airtable is written first and the local cache updated only if that succeeded — the other order would show a student their change and then lose it on the next sync, which is worse than refusing the save. Contribution team is a *linked-record* field in Airtable, so it is a select of the teams the sync has cataloged rather than a text box; an unknown value is refused rather than written. Needs `data.records:write` on the token.
* Airtable's `In Sensei` and `In Sensei 50h` are shown as **WordPress Credits Program 150h** and **WordPress Credits Program 50h** everywhere, with a link to that track's Learn WordPress course. The raw values stay the storage format — the sync still matches on them and the settings still list them. Filters: `wpcpm_program_labels`, `wpcpm_program_courses`.
* **Contribution team names link to their team site.** The list is *read from* the Contributor Team Matcher plugin when it is installed, rather than copied, so it cannot go stale; a bundled fallback covers a site without it. Airtable's list is longer than the matcher's, and a team with no known page stays plain text — guessing a URL from a team name is how somebody ends up linked to a 404. Filter: `wpcpm_contribution_team_links`.
* **Two new fields from the Students table**: *Field of study* on both dashboards, and *Accessibility needs* on the mentor's side only — it is the one field there a mentor may need to act on before a call. Both are joined by email, the same way the tutor already is, because neither exists in Students Reports. **Run one sync after updating** for them to appear.
* Renamed: "Main contribution team" → **Contribution team**, "Internship" → **Internship duration**.
* The mentor dashboard shows **the mentor's name as the page heading**, with the student count and last-updated on one line beneath it. The page's own "My Students" title is gone — it said less than the name does. *Upcoming calls* and *Your availability for calls* now sit side by side.
* The student page leads with a larger portrait and their name, and drops the "Program: In Sensei" line under it — the program is the first row of the table directly below. The mentor's photo is larger too, and booking a call now sits directly under the mentor card, which is what names the person the call is with.
* Matching theme update (WPCredits theme 1.6.0), which also fixes the student page's profile photos: they had their own brand-colored border instead of the site's shared photo mount, which is why they did not look like any other photo on the site.

= 1.13.1 =
Cross-check against the WPCredits theme, plus a hardening pass over the calendar.

* **Fixed: uninstall crashed and cleaned up nothing.** `uninstall.php` builds its own
  dependency list rather than booting the plugin, and 1.13.0 taught the Mentors module to
  call the three new calendar classes without adding them to that list — a fatal in the
  middle of cleanup, which leaves every option, role and record behind and says nothing
  about it.
* Uninstall now also clears the **students** sync cron, which it never did — the mentors
  hooks were cleared and the students ones were not, and a scheduled hook whose callback
  no longer exists fails silently forever. And it sweeps the prefix-named leftovers that
  cannot be removed by listing keys: cached WordPress.org profile reads, booking locks
  from requests that died holding one, and the per-student resolved-mentor cache.

* **New public API: `WPCPM_Mentors_Dashboard::current_mentor()`.** Which mentor's list a
  request is showing was private, so the theme reimplemented it — and the copy tested for
  the Mentor *role*, which an administrator who also mentors never holds, since the sync
  refuses to touch an administrator's roles. The page and the theme's data therefore
  described different mentors. Exposing the real answer removes the duplicate. Anything
  dressing this page should call it rather than guess.
* **Fixed: a student could exceed the bookings-per-student limit by racing.** The limit
  was checked before the booking lock was taken, so two requests could both see zero
  upcoming calls and then book different slots in turn. It is now re-checked inside the
  lock. The slot re-check did not catch this, because the two requests were not competing
  for the same slot.
* **Fixed: paging the calendar dropped a program manager's student view.** The month and
  day links preserved `wpcpm_student`, which is the notes focus on the mentor page and
  holds an Airtable record ID, while the argument that actually selects a student —
  `wpcpm_student_view` — was discarded. Booking and canceling now keep it too.
* Hardened: every value in the availability form is read as a scalar, so a posted array
  can no longer become the literal string "Array" in a schedule; and the names that reach
  a mail *subject* are stripped of the newlines that would turn one into extra headers.
  Both come from outside this code — an Airtable column and a WordPress profile.
* Documented: the `wpcpm_slack_url` filter is now the only place the Slack target is set.
  The theme used to link the handle as well and has dropped its own filter.

= 1.13.0 =
* **New: a call calendar for the Mentors module.** A mentor sets their weekly availability on their own dashboard; the students assigned to them pick a date and time from it. See *Call calendar* above for the whole feature.
* A mentor's hours are stored in the mentor's timezone and shown to everybody on their own, chosen once and remembered — a program running across a dozen countries has no single right clock. Daylight saving is handled: nothing is offered in an hour the clocks skip, and a wall-clock time in a repeated hour is offered once.
* Double-booking is prevented at the database, not in the interface: a short lock, a re-check of the slot inside it, and a clash check on the table afterwards that undoes the later of two bookings.
* Booking and canceling both email the other person. Filter `wpcpm_send_call_mail` to stop that.
* Filter `wpcpm_availability_defaults` sets the program-wide starting point for call length, notice, horizon and bookings per student. Availability itself is deliberately empty until a mentor sets it — a default nine-to-five nobody chose would still take bookings.
* Matching theme update (WPCredits theme 1.5.0), which dresses the calendar, the diary and the availability editor in the dashboard's own grays and control shapes. Without it the calendar works but is styled by the plugin's theme-agnostic fallback.

= 1.12.0 =
* Administrators now get every dashboard in one **Dashboards** toolbar menu — *Student Dashboard* and *Mentor Dashboard* together — instead of separate top-level items. Anyone with a single page still gets a direct link rather than a menu wrapped around one destination, and someone who is both a mentor and a student gets both, labeled as their own.
* Fixed: an administrator opening a dashboard before anything had been synced was told "your account does not hold the Mentor role" — untrue, and no help. They now get the real reason, that no accounts have been synced yet, and a link to the screen that fixes it.

= 1.11.1 =
* The student page now matches the mentor page's layout. Its stylesheet depends on the mentor one and the two share the shell — identity header, "view as" switcher, muted text, avatars, badges and buttons — so a single definition serves both and they cannot drift apart. Detail tables use the same label column and spacing.
* Matching theme update (WPCredits theme 1.4.0): the student page gets the same card, the same 32px insets and the same grays and type as the mentor page, its own template, and a **My Program** link in the header.

= 1.11.0 =
* **The Students module is built.** Student accounts are provisioned from Airtable, hold the Student role, and can read Student-level content only.
* Added the *My Program* page at `/student-dashboard/`, gated to Student level: program, internship dates, tutor, institution, WordPress.org profile, Slack name, contribution team, personal website, and a button to the student's prefilled report form.
* The page shows the mentor assigned to the student with their contact details. Airtable holds only a mentor's name, email and profile URL, so their Slack handle, job line, location, website, GitHub and teams are read from their WordPress.org profile — cached for twelve hours and shared across that mentor's students.
* Usernames come from a student's WordPress.org profile where Airtable has one, and from their email address otherwise.
* Students land on their page at login and in place of the wp-admin Dashboard, with a *My Program* toolbar link. Same exceptions as the mentor side.
* Added a `wpcpm_slack_url` link for student and mentor Slack handles alike, and a `wpcpm_field_descriptions`-style separation so nothing new was hardcoded.

= 1.10.1 =
* The Slack name is now a link to the program's Slack channel, <code>https://wordpress.slack.com/archives/C0959D2M3T8</code>. Slack has no public per-user URL, so every handle points at the channel where a mentor would use it. Override with the `wpcpm_slack_url` filter.
* A student with no Slack handle still shows "Not set" rather than an empty link.
* The WPCredits theme was linking the handle to make.wordpress.org/chat itself; it now takes the plugin's target so the two cannot disagree (theme 1.3.3).

= 1.10.0 =
* Administrators who are also Active mentors in Airtable now see **their own** current and past students on the mentor page. The sync deliberately never gives an administrator the Mentor role, so a role check alone treated them as a non-mentor and showed them the first mentor in the list instead.
* Such an administrator now also appears in the *Viewing as mentor* switcher, so they can inspect a colleague and switch back to themselves. Previously the switcher rejected their own ID.
* Renamed the **Mentor page** link to **Mentor Dashboard**. Anyone with a list of their own — administrators who mentor included — still sees it labeled **My Students**.

= 1.9.0 =
* Removed the **Description** column from the student details table, and the Field/Value header row with it — two columns of label and value need no headings to explain them.
* The Airtable source line under each description went with the column. Field descriptions are still read from Airtable and still available through the `wpcpm_field_descriptions` filter; nothing on the page uses them now.
* Matching theme update (WPCredits theme 1.2.1): the rules for the removed header and description column are gone, and the label column width moved from the header row onto the body, where it now lives.

= 1.8.1 =
* Past students can no longer be given new notes — a graduated student is not going to be called again. The form is hidden for them and the request is refused server-side, not just hidden. Existing history stays readable and deletable.
* Spaced out the **Currently mentoring** and **Past students** headings, so the count no longer sits flush against the label and the heading keeps clear of the cards below it.

= 1.8.0 =
* The My Students page is now split into **Currently mentoring** and **Past students**, the latter in its own collapsed section.
* Past students are read from Airtable for the first time — previously only `In Sensei` and `In Sensei 50h` were fetched, so finished students were not in the data at all. **Run one sync after updating** for the Past section to appear.
* Which section a student falls into comes from their Airtable status, not their end date, and both status lists are editable in the settings. A status listed in both counts as current.
* Current students are ordered by soonest deadline; past students by most recently finished.
* The Mentors admin screen gained a **Past** column, and the sync report now counts current and past reports separately.
* Expand all now also opens the Past students section.

= 1.7.3 =
* Fixed: the **Program access** panel forced a horizontal scrollbar on the editor sidebar. Two causes. The plugin's admin stylesheet is only loaded on its own screens, so the editor got no styling for the control at all; and a select is sized from its widest option and will not shrink below it without `min-width: 0`, which `widefat` does not set.
* Added a small editor stylesheet, loaded on the post editor only.
* The "Public — everyone" option now reads "Public", with the explanation moved to the description under the control.

= 1.7.2 =
* Reverted the 1.7.1 change: the **Student report form** button is inside the student's card again, visible once the card is opened, rather than in an always-visible footer row.
* The empty-`id` fix from 1.7.1 is kept — it was an unrelated correctness fix, not part of the reverted behavior.

= 1.7.1 =
* The **Student report form** button is now visible on a collapsed student, not only once the card is opened.
* It sits outside the disclosure rather than inside the clickable summary row: a link nested in a summary is an accessibility trap, where activating it can toggle the disclosure instead of following the link.
* Fixed: a student row with no Airtable record ID rendered an empty `id` attribute, which repeated on every such card.

= 1.7.0 =
* Added call notes. Each student now has a running history of notes — one per call, typically — written and read on their own card, each stamped with date, time and author. The collapsed row shows a note count.
* A mentor can delete their own notes; a program manager can delete any. Nobody can delete a colleague's note.
* Notes are only visible to mentors that student is assigned to, plus administrators. Every read and write is checked against the mentor's own synced student list.
* Stored in a private post type rather than with the cached student data, which the sync rewrites in full on every run — a note kept there would not survive the next sync. Notes are never written back to Airtable.
* Saving a note returns to that student's card with it already open, without relying on JavaScript.

= 1.6.0 =
* Added the student's **email address** to their details, as a `mailto:` link so a mentor can write to them in one click. It sits with Slack name, since both answer "how do I reach them".
* The email was already being read from Airtable for the Gravatar fallback, so this needs no re-sync — existing student lists show it immediately.
* `mailto:` links no longer carry `target="_blank"`, which would leave an empty tab behind once the mail client takes over. External links are unchanged.

= 1.5.1 =
* Renamed the **Open personal link** button to **Student report form**. The 50-hour track's button reads **Student report form (50h)**, so the pair stays consistent.
* The button's tooltip no longer runs the Airtable column name through translation — it names the actual column to go and edit, which does not change with locale.

= 1.5.0 =
* Each student's details table is now collapsible and starts collapsed, so a mentor with dozens of students gets a list they can scan. The collapsed row still shows photo, name, status, institution and internship end date.
* Built on a native disclosure element, so it works without JavaScript, from the keyboard, and with screen readers.
* Added **Expand all** and **Collapse all** above the list, shown only when scripting is available.
* Printing opens every student first, then restores what was open, so a printed list is never half-empty.
* A mentor with exactly one student sees that student already open.

= 1.4.0 =
* The *My Students* page is now the mentor's dashboard. Mentors land there when they log in, it replaces the wp-admin Dashboard for them, and a **My Students** link sits in the toolbar so they can return from anywhere. Previously a mentor could log in and have no route to their own page at all.
* A mentor sent through the login form from a specific link still arrives at that link, not the dashboard. `profile.php` stays reachable so they can change their password. Accounts that also hold an editor or author role are never redirected, and administrators are unaffected.
* Controlled by **Mentor landing page** in the settings, on by default.
* Fixed: `page_url()` returned a link to the mentor page even when it was in the trash, because `get_post_status()` returns `trash` rather than nothing. It now requires the page to be published — which matters much more now that logging in depends on it.

= 1.3.3 =
* Fixed: **Educational institution** showed "Confirmed" and **Main contribution team** showed a list of student names. Resolving a record ID to a name read every column and took the first text value it found, on the assumption that a table's primary column is returned first. It is not — that picked Institutions → *Current Stage* and Contribution areas → *Students Reports copy*. The primary column is now identified from Airtable's schema and requested by name, which is also a much smaller request.
* Lookup maps written by the previous version held those wrong values, so they are version-stamped and discarded on upgrade. Until you run a sync the two fields read "Not set" and the Mentors screen asks for one — the plugin will not show a name it cannot stand behind.
* A name column that returns nothing is now reported in the sync warnings instead of silently producing an empty map.
* The Institutions and Contribution areas tables, and the column each uses for its name, are now editable on the settings screen.

= 1.3.2 =
* The settings screen now lists the token scopes and what each is for, instead of mentioning only `data.records:read`. The Mentor Status Checker needs **both** `data.records:read` and `data.records:write` to promote a mentor.
* A permissions error from Airtable now names the scope that is missing — write, schema or read — depending on what the request was, rather than passing Airtable's generic message through.

= 1.3.1 =
* Fixed: **Educational institution** and **Main contribution team** still showed raw record IDs after 1.3.0. Two reasons. The mentor page renders student rows cached in user meta, so resolving them only at sync time left every already-stored row unchanged until someone happened to re-sync; and the ID → name maps were held in the sync state, which is deleted when a run finishes, so nothing was left to resolve with afterwards. The maps are now stored in their own option, and the page resolves record IDs as it renders — so existing rows are corrected immediately.
* A record ID that cannot be resolved is left blank rather than printed, so a raw `rec…` never reaches the page.
* The Mentors screen now says when institution and team names have not been read yet, and that a sync will fill them in.

= 1.3.0 =
* Added a **Tools** section, separate from the four modules.
* The standalone **Credits Program Mentor Checker** plugin is now the **Mentor Status Checker** tool. It uses the plugin's shared Airtable connection and settings screen instead of its own. Deactivate the standalone plugin — the tool warns you if it is still active.
* Airtable client gained write support (`data.records:write`), used only to promote a mentor's status.
* Fixed: **Educational institution** and **Main contribution team** showed raw Airtable record IDs such as `recGzpWO43cQnVYEw` instead of names. The REST API returns linked-record fields as bare record IDs, so the sync now reads the Institutions and Contribution areas tables and resolves them. An ID with no matching name is shown as "Not set" rather than printed raw.
* Fixed: the mentor page could render unreadable light-on-light text. Colors were set behind `prefers-color-scheme`, which follows the operating system rather than the theme, so a light theme on a dark-mode machine got near-white muted text on white. Everything is now derived from the theme's own text color.
* `wp wpcredits check-mentors [--promote]` added, with per-mentor progress output.

= 1.2.0 =
* Student details are now laid out as a table with Field, Value and Description columns.
* Field descriptions are read from Airtable itself, via the schema endpoint, so a description written on a column in Airtable appears on the mentor page after the next sync. Built-in descriptions are used until then, and if the token lacks the optional `schema.bases:read` scope.
* Added a `wpcpm_field_descriptions` filter for overriding the built-in descriptions.
* The Airtable source is shown as visible text under each description instead of only as a tooltip.
* Fixed: a description written on the Students `Tutor ` column was never found, because the column name has a trailing space and was matched exactly. Names are now compared trimmed.
* Fixed: a scheme-less URL in Airtable's *Personal Website URL* column linked to a path on this site. Missing schemes are normalized to `https://`.
* Student cards are now full width, since a three-column table does not fit a narrow card, and the table stacks on small screens.

= 1.1.0 =
* Mentor page now shows profile photos — the mentor's own in the page header, and each student's on their card — taken from their WordPress.org profile, with a Gravatar fallback for anyone who has no profile recorded.
* Every field label now carries a tooltip naming the Airtable table and column the value came from, so it is clear where each piece of data originates.
* Empty fields are no longer hidden. A blank value shows a muted "Not set" with a tooltip, so a gap in Airtable is visible as a gap instead of the row silently vanishing — this is what made a missing Educational institution look like a bug in the page.
* Syncs now always show live progress: a progress bar, step counter, running record counts and a per-second elapsed clock.
* Starting a sync no longer blocks the request that started it — previously the click could hang for up to 18 seconds with nothing on screen.
* The admin screen drives the run as it polls, so a sync progresses even where WP-Cron is unreliable; cron stays as a fallback for a closed tab.
* Added a database-level lock so a browser-driven tick and a cron tick can never process the same Airtable page twice.
* A run that stops advancing for more than two minutes is now reported as stalled instead of spinning indefinitely.
* `wp wpcredits sync-mentors` prints percentage and live counts, one line per slice.
* Mentee counts are stored as their own user meta, so the admin list no longer unserializes every mentor's full student array to render a count.

= 1.0.0 =
* Initial release.
* Four-module structure: Students, Mentors, Institutions, Administrators.
* Student, Mentor and Institution roles based on Subscriber, each with its own content-access capability; program capabilities granted to Administrator.
* Per-post access levels enforced on listings, direct access, rendered content and the REST API.
* Mentors module: Airtable sync provisioning Mentor accounts from WordPress.org usernames, collision-safe account matching, opt-in invitation emails, and role revocation when a mentor stops being Active.
* Mentors module: *My Students* page listing each mentor's assigned `In Sensei` and `In Sensei 50h` students, as a block and the `[wpcpm_mentor_dashboard]` shortcode.
* WP-CLI: `wp wpcredits sync-mentors [--dry-run]`, `wp wpcredits mentor <mentor>`, `wp wpcredits roles`.
