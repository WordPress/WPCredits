## Running it day to day

### The sync

The students and the mentors syncs read Airtable and reconcile accounts: create what is missing,
update what has changed, and apply your *when they are no longer active* setting to the rest. Run
one by hand from the Students or Mentors screen, or leave **Automatic sync** on - students and
mentors every three hours, half an hour apart.

They start half an hour apart on purpose. The student rows carry what students and mentors are
shown on their cards, so a stale copy is a stale card, and it is the expensive half of the pair:
it reads one WordPress.org profile per mentor, cached for twelve hours, while building the mentor
cards. The mentors run reads three Airtable tables and makes no WordPress.org request. Two runs in
the same request would be one long request. A run still going when the next one is due is left to
finish rather than restarted.

**Syncs every three hours (1.98.2).** All four Airtable syncs - students, mentors, institutions and
sponsors - share one three-hour clock, and the first runs are staggered so each starts at its own
point in the cycle: students half an hour in, mentors an hour, institutions an hour and a half,
sponsors two hours. WordPress cron runs each event at or after its scheduled time, so the cadences
drift apart after that and on a busy site two can still land close together, each safe behind its
own lock. Before this only the students sync ran every three hours and the other three ran once a
day, which meant an institution or a sponsor changed in the base was a day old on the site. Nothing
has to be switched off and on again for the change to take: on the first request after the update
each sync sees its own event still on the daily schedule, clears it and puts it back on the
three-hour one. The housekeeping jobs are not syncs and stay daily - the two application retention
runs, the two agreement discards, the reviewer's digest, invitation expiry, the semester report
drafting and the ceiling's sweep - because the settings behind them are in days and a base that is
down must not stop a file being forgotten on time. The Mentor Status Checker stays weekly: it reads
WordPress.org profiles rather than Airtable.

Accounts are matched by WordPress.org username where there is one, and by email otherwise. **No
account is ever deleted by a sync** - the most it will do is remove a role.

The sync report on each screen says what happened rather than only that it finished: created,
updated, skipped and why.

### Invitations

An invitation is a password-reset link. Send them in bulk by switching **Invitation emails** on
before a sync, or one at a time from the Students and Mentors screens - which is the safer habit,
because a first sync creates around ninety accounts at once.

### Pages the plugin owns

Activation creates the Student Report Card and Mentor Report Card pages and gates them. If one goes
missing, re-activating the plugin recreates it; the settings screen warns you when a page it expects
is not there.

### Uninstall

Uninstall removes the settings, the sync state, the access-level meta and the custom roles, and moves
affected accounts back to Subscriber; it also deletes the sponsors' offers, pools and locks, and the
claims meta, and the sponsor applications with the logo files nobody approved. **Accounts are never
deleted**, and their program details in Airtable are untouched.

### When something is wrong

| Symptom | Where to look |
| --- | --- |
| A student or mentor is missing | Their Airtable status, against the status settings. The sync only creates accounts for the statuses you list. |
| Details are stale on a Student Report Card or Mentor Report Card | Run the sync by hand; the page renders from what the last sync stored. |
| A mentor sees the wrong students | The mentor↔student link in Airtable. The page joins on the records, not on names. |
| Nobody can book a call | The mentor has published no availability. Their Mentor Report Card says so. |
| Invitations are not arriving | The **Mail** section on Settings. "Accepted" means the site handed it off; anything else is between the site and its mail service. |
| A gated page is readable by the wrong people | The post's **Program access** control, and the reader's role. Administrators can read every level by design. |

## Semester reports

The site drafts a report for each institution semester once the semester has ended and every student in it is finished, or 45 days after the end (the "Drafting grace" setting) if some are not. The job runs nightly and drafts at most ten reports a run; it never drafts a semester that ended before the feature was installed, so older semesters are drafted by hand with the Draft now button on the Institutions screen. Each draft is mailed to the addresses in "Who reviews reports", or to every program manager when that is empty.

Review a draft on the Institution Dashboard, reached through the switcher from the Semester reports card. Edit the narrative, choose the quotes, then press Approve. The institution sees the report from that moment, with a Download PDF button, and its accounts are mailed; an institution with no account is not mailed and the screen says so, so send the PDF by hand. Reopen takes the report back to a draft and out of the institution's view. Approval is refused while the students' consent answers cannot be read.

## The Administrator Dashboard

The Administrator Dashboard at /administrator-dashboard/ is the page to start the day on. It is gated to program managers and shows, in this order: a strip of twelve counts (institution applications waiting, agreements to review and overdue, reports to review, semesters due for drafting, mentor requests open and overdue, locked accounts, sponsor posts to review, sponsor agreements to review, sponsor applications waiting, offers running low), then one card each for institution applications, Collaboration Agreements, semester reports, mentor requests, sponsor applications, sponsor posts, sponsor Collaboration Agreements, offers running low, new interests, the sponsors' figures, the programs running and the syncs' health. Every decision on the page is the same decision the wp-admin screen offers, posted to the same handler with the same safeguards, and it lands back on the page (Draft now opens the new draft in the editor, and a refusal comes back to the page). What the page does not do: run a sync, change a setting, approve a semester report (that happens in the editor, where you have read it) or provision accounts; those stay on the wp-admin screens the Syncs card links to.

The programs card counts students in progress per track and per institution from the roster index, so its numbers are as old as the last students sync; the read time is printed under the table. "Finished this semester" counts graduates only, not everyone who left the program; a graduate's row no longer says which track they were on, so it is one number rather than one per track. "Signed up this semester" per track counts the students who started in the semester and are still on that track; a student who started and has since paused, graduated or left is in the semester's finished count or in no count. Mentors are counted by distinct name, not by their Airtable record, because a roster row carries no mentor record ID: two mentors who share a name count once.

## Sponsors

The Sponsors screen is where a sponsor's account begins. The sponsors sync (every three hours, two hours into the cycle) reads the Team Members table and the Sponsors table into an index, copies each Approved sponsor's logo into the Media Library, and, only if the setting says so, detaches the accounts of sponsors that are no longer Approved. It never creates an account: a program manager presses Create account on an Approved sponsor's row, which makes an account from the sponsor's Contact Email (or attaches the account that already holds that address), queues the welcome, ticks the Dashboard account checkbox in Airtable and writes a log row. Accounts are attached by address and removed on the same screen; a sponsor's dashboard is opened through the switcher link in the first column. A sponsor whose logo is an SVG is named in the sync report: the site does not take SVG, and the sponsor converts it.

On the Sponsor Dashboard a sponsor can save eight profile fields back to Airtable (website, contact person and email, product type, offer, instructions, more-info link and the free-text field); every save writes an audit row naming the fields changed and nothing else. Interests, and interest in a mentor from the looking-for-a-sponsor list, mail the assigned program manager (the Person of contact in Airtable), or the addresses in the "Interest mail" setting, or every program manager, in that order of preference; five a day per account. What sponsors can never read is a student's name: their mentors card shows mentor names and student counts only.

**Offers, codes and the Tools section (1.94.0).** A sponsor's offers live on the site, not in the base. Each is a pool of one-time codes the sponsor pastes or uploads as a .txt or .csv file on the Sponsor Dashboard, on the new-offer form or on the pool's own box (one per line, or a CSV's first column; a code can be a whole checkout link; all or nothing, refused by line number; a file is read once and never stored, up to a megabyte; up to 5000 codes), or one shared code or link for everyone. Since 1.94.1 the kind is two plain choices and the form shows only the box that belongs to the kind chosen. The first offer is seeded from the base when an account is provisioned (title: the sponsor's name; text, instructions and link from `Offer`, `Brief instructions` and `More info link`; a checkout link that is not the coupon sheet becomes the shared code, the sheet itself is never stored); for sponsors provisioned before 1.94.0 the Sponsors screen has a *Seed the first offer* button. That first offer is the one mirrored back to the three base fields on every save, so managers keep seeing it in the grid; `Coupon code/discount link` is never written, and a manager clears the sheet links by hand once a sponsor is live. An offer goes live only with something to give (a code in the pool, or the shared link); ended is final; the kind cannot change once the pool holds anything.

Codes are encrypted at rest with the site key the stored agreements use and are shown only to the person who claimed one, on their own Student Report Card, Mentor Report Card or the Administrator Dashboard; nothing sends a code by mail. Who may claim is one function with five clauses: the offer is live and not past its last day; the person is a current student (their synced status is one of the student statuses and not a past one), or a mentor when the offer opens to mentors, or a manager when it opens to managers; they have not claimed it; a pool has a code left; a shared offer has something to share. A second press returns the same code. A claim is written under a per-offer lock, so two students pressing together get two codes.

Sponsors read numbers, managers read names. The sponsor's Usage card counts claims by month and offer (and a CSV of the same); the Sponsors screen's *Offers and codes* card lists every offer with its counts and, for support, who claimed from it (name, address, date, the code's last four characters) with a *Void* button: voiding frees the person to claim again and keeps the code void for the count. A claimant's *Report a problem* mails the sponsor's assigned program manager (else `sponsor_notify`, else every manager) the name, the offer and the last four characters, three a day per person. When a pool falls below its threshold, one mail goes to the sponsor's accounts and one to the manager, once, re-armed by adding codes. Nothing about a claim reaches Airtable.

The Tools section is drawn on a person's own Student Report Card (setting *Tools from our sponsors*, on by default), on their own Mentor Report Card (off by default) and on the Administrator Dashboard (every live offer, labeled with its audience). On a manager's view of a student it is one line, "N tools claimed".

**Logo and agreement (1.96.0).** A sponsor uploads its own logo on its dashboard, in color and optionally in white; the site checks the bytes rather than the name (PNG, JPEG or WebP, at least 200 pixels wide, SVG refused), re-saves the image through WordPress's editor, and writes the attachments' public URLs back to Airtable's `Logo`, color first, so the base shows the same picture. Five uploads a day per company. Remove takes the logo out of the site and out of the program records at once, and deletes nothing from the Media Library. The sponsor agreement is optional and is never a gate: a company's dashboard, offers and codes work without one. A sponsor uploads a signed PDF from its dashboard and can withdraw it while nobody has read it; the Agreements card on the Sponsors screen, and the Sponsor Collaboration Agreements card on the Administrator Dashboard, hold the review queue, with the facts, what the PDF scan noticed, a download that is always an attachment, and Accept or Return with a note that is emailed verbatim. An accepted agreement can be taken out of force with a note and put back; a company whose signed copy predates this site is recorded as on file with a link to the program's Drive folder. Each of those writes `Agreement Status`, and acceptance also writes `Agreement Accepted On`; on-file writes `Agreement Document` too. A withdrawn file is deleted the moment it is withdrawn, and a returned one by a daily run after the retention setting; accepted, superseded and revoked ones survive an uninstall and appear in the mailed manifest beside the institutions' files.

**The application form (1.97.0).** A company applies to sponsor the program on this site, at /sponsor-application/, instead of in the Airtable form: the same eight questions in the same order (company name, website, contact person and address, how it would like to support the program, a logo in color and in white, anything else, and the privacy policy confirmation), guarded exactly as the institution form is (a hidden field, a signed token, five submissions an hour per address, forty a day for the whole site before the rest are held, consent as a precondition with what was agreed to recorded, links counted, and a ceiling on acknowledgements). The form is off until "Applications from sponsors" is switched on in the settings and shows nothing to the public without a published privacy policy. Every submission is a private row: the applicant gets an acknowledgement with a reference (SAPP-2026-0007), the program managers named by the "Interest mail" setting (or every manager) get the facts and a link, and the Sponsors screen's *Sponsor applications* card lists what is waiting, oldest first, with a mark for a row the checks held, for another open application naming the same company or address, and for a company the sponsors index already holds under the same name or website. Under that list, *Recently decided* lists the applications somebody has already decided (marked as spam, rejected or approved), newest decision first, so a genuine application the checks filed as spam, or a rejection somebody pressed by mistake, can be opened and put back in the queue rather than waiting for the retention run to delete it. The logo files an applicant sends are kept in the Media Library as private files under a generated name until the application is approved, so nothing a stranger uploaded is listed or reachable before somebody has looked at it; approval publishes both halves and hands them to the sponsor's account. Open one to read the answers, the logo files, what was agreed to, what the base already has, and the six decisions the institution queue has: Approve, Send this question (mailed with your address to reply to), Reject (a short acknowledgement with no reason; your note stays here), Reject as spam (nothing is sent), Put back in the queue, Delete for good. The Administrator Dashboard carries the same six decisions on its own *Sponsor applications* card and counts the queue in its strip; the answers and the base matches are on the Sponsors screen until the next phase. Approve creates the Airtable record with Status Approved, the website, the contact, the sponsorship option, the free text, the two logo files and the Dashboard account checkbox, then in the same press the account (through the same path Create account uses), the company's category, the logo record and the first offer in draft, and queues the welcome; if Airtable refuses, nothing else happens and pressing again starts clean. If a press got as far as the Airtable record and then stopped, the row is marked *approval half done* wherever it is drawn and Reject, Reject as spam and Put back in the queue are refused on it: press Approve again to finish, then decide what you like. A company the base already holds is never merged: reject the application and use Create account on the Sponsors card instead. The retention settings for institution applications govern this form too, the same three settings by the same names on both (spam after 30 days, rejected after a year, approved never, by default), and a held application is now purged on the rejected window on both forms alike. A spam, held or rejected sponsor application's logo files are deleted with it - the institution form keeps no logo files to delete - and an approved one's are the sponsor's logo and stay. After go-live, one manual step: change the form link on the handbook page to /sponsor-application/.

**The guide and the queues (1.98.0).** Sponsors have a guide of their own, composed by `bin/build-docs.php` as the `sponsors` audience from four sections (the dashboard, offers and codes, what a sponsor can and cannot see, posts) and published on the handbook beside the other three; the Sponsor Dashboard's guide button points at it. The Administrator Dashboard gained three sponsor cards after the Sponsor agreements card: *Offers running low* (every live pool under the threshold its sponsor set, the emptiest first, linked to the sponsor's Offers and codes card through the switcher), *New interests* (what sponsors sent from *What else would you like to support?* in the last thirty days, as they wrote it) and *Sponsors* (Approved sponsors, how many hold an account, the offers live today, the claims since the semester began). The *Sponsor applications* card folds the whole application under *Read the application*, the needs-attention strip has a twelfth tile for the pools running low, and the Syncs card lists the sponsors sync. Nothing here is a new option or a new post type: the phase reads what the earlier phases wrote.

**The clean-up (1.98.1).** The two application forms share one stash-and-redirect class, `WPCPM_Form_Stash`. The Sponsors menu bubble is counted once a minute and forgets its count the moment an application, an agreement or a sponsor post changes. A dwell token has one shape. Recently decided reads a decision-time index instead of every decided row. The docs build escapes a section's text, so a literal angle bracket is a character on the page. The institution agreement retries a failed Airtable write on every sync run, as the sponsor agreement has since 1.96.0. A refused image store deletes the copy it made.

**The deep check fixes (1.99.0).** A line-by-line review of 1.98.1 on 7 September 2026 raised fifty findings, and all fifty are closed here. One was serious: a person removed from a sponsor kept the right to write posts and upload files, so they could still sign in to wp-admin, save drafts and put files in the Media Library. Removing somebody now takes those rights away for good, and the first admin page after the update repairs, once and silently, the accounts an earlier removal left holding them. Eight more were worth naming. A pasted list of offer codes was read line by line with a sentence written for every bad line, so a very long list of repeats could exhaust the site's memory; a paste is now measured and refused before it is read. A sponsor withdrawing its Collaboration Agreement wrote `Not started` over the `Returned` a manager had just set; a withdrawal now writes the status that is true at that moment and never an older one over a newer one. Three agreement transitions stored an empty Airtable status in an institution's option, and an empty status reads as an open gate, so the next generate could write over what the base already held; the option never stores an empty status now. The last five were gaps in the checks rather than in the plugin: nothing pinned the sponsor application post type's exposure flags, the PDF scan's stream and byte budgets were asserted as a pair of numbers rather than exercised, three of the five places the content gate is enforced had no assertion at all, and the largest suite printed no summary line, so the documented runner could not tell a pass from a failure. Each of the four has a check of its own now. The fifth was the seed fixture, which published three named people together with the program's private judgment of them: the fixture and every suite under `bin/` are synthetic, with no real record ID, name or address left anywhere on the public mirror. Two smaller changes belong here too: a held application row is purged on the rejected window on both the sponsor and the institution form, so nothing waits for ever; and `bin/check-standards.sh` exits 0 when it is clean, so a build script can read its status.

## Where the plugin keeps its data

Every option the plugin writes, by the name it has in the database and the constant that owns it
in the code. Every option constant is spelled `OPT_` since 1.90.0 (three spellings coexisted
before, which is how a search for one of them missed the others). Options named after a record
or a batch (`wpcpm_roster_<record>`, `wpcpm_agreement_<record>`, the import and report locks)
are formed from the prefix constants listed here. Post meta and user meta keep the keys they had:
renaming a stored key is a migration, and nothing here warranted one.

| Option | Constant |
| --- | --- |
| `wpcpm_administrator_page_id` | `WPCPM_Administrators_Dashboard::OPT_PAGE` |
| `wpcpm_administrator_page_title_fixed` | `WPCPM_Administrators_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_agreement_` | `WPCPM_Institution_Agreement::OPT_PREFIX` |
| `wpcpm_agreement_drift` | `WPCPM_Agreement_Template::OPT_DRIFT` |
| `wpcpm_agreement_on_file_all` | `WPCPM_Institution_Agreement::OPT_ON_FILE_ALL` |
| `wpcpm_agreement_reminded` | `WPCPM_Institution_Agreement::OPT_REMINDED` |
| `wpcpm_application_log` | `WPCPM_Institutions::OPT_APP_LOG` |
| `wpcpm_application_page_id` | `WPCPM_Institution_Application::OPT_PAGE` |
| `wpcpm_claim_` | `WPCPM_Sponsor_Codes::LOCK_PREFIX` |
| `wpcpm_claims` | `WPCPM_Sponsor_Claims::META_CLAIMS` |
| `wpcpm_codes_` | `WPCPM_Sponsor_Codes::OPT_PREFIX` |
| `wpcpm_countries` | `WPCPM_Countries::OPT_NAME` |
| `wpcpm_handbook_model_fixed` | `WPCPM_Handbook::OPT_MODEL_FIXED` |
| `wpcpm_handbook_page_id` | `WPCPM_Handbook_Assistant::OPT_PAGE` |
| `wpcpm_handbook_page_visible` | `WPCPM_Handbook_Assistant::OPT_APPLIED` |
| `wpcpm_import_log` | `WPCPM_Institution_Import::OPT_LOG` |
| `wpcpm_institution_modules_` | `WPCPM_Institutions_Dashboard::OPT_MODULES_PREFIX` (each institution's module order) |
| `wpcpm_institution_page_id` | `WPCPM_Institutions_Dashboard::OPT_PAGE` |
| `wpcpm_institution_page_title_fixed` | `WPCPM_Institutions_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_institutions_index` | `WPCPM_Institutions_Index::OPT_NAME` |
| `wpcpm_institutions_last_error` | `WPCPM_Institutions_Sync::OPT_ERROR` |
| `wpcpm_institutions_last_sync` | `WPCPM_Institutions_Sync::OPT_LAST` |
| `wpcpm_institutions_lock` | `WPCPM_Institutions_Sync::OPT_LOCK` |
| `wpcpm_institutions_report` | `WPCPM_Institutions_Sync::OPT_REPORT` |
| `wpcpm_institutions_state` | `WPCPM_Institutions_Sync::OPT_STATE` |
| `wpcpm_mentor_page_id` | `WPCPM_Mentors_Dashboard::OPT_PAGE` |
| `wpcpm_mentor_page_title_fixed` | `WPCPM_Mentors_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_mentors_field_meta` | `WPCPM_Mentors_Sync::OPT_FIELDS` |
| `wpcpm_mentors_last_error` | `WPCPM_Mentors_Sync::OPT_ERROR` |
| `wpcpm_mentors_last_sync` | `WPCPM_Mentors_Sync::OPT_LAST` |
| `wpcpm_mentors_lock` | `WPCPM_Mentors_Sync::OPT_LOCK` |
| `wpcpm_mentors_lookups` | `WPCPM_Mentors_Sync::OPT_LOOKUPS` |
| `wpcpm_mentors_report` | `WPCPM_Mentors_Sync::OPT_REPORT` |
| `wpcpm_mentors_sponsorship` | `WPCPM_Mentors_Sync::OPT_SPONSORSHIP` |
| `wpcpm_mentors_state` | `WPCPM_Mentors_Sync::OPT_STATE` |
| `wpcpm_notices` | `WPCPM_Notices::OPT_NAME` |
| `wpcpm_notices_migrated` | `WPCPM_Notices::OPT_MIGRATED` |
| `wpcpm_notices_plain` | `WPCPM_Notices::OPT_PLAIN` |
| `wpcpm_offer` | `WPCPM_Sponsor_Offers::POST_TYPE` |
| `wpcpm_privacy_version` | `WPCPM_Privacy_Guard::OPT_VERSION` |
| `wpcpm_private_key` | `WPCPM_Secret::OPT_KEY` (aliased by `WPCPM_Private_Files::OPT_KEY`) |
| `wpcpm_private_probe` | `WPCPM_Private_Files::OPT_PROBE` |
| `wpcpm_report_autodraft_since` | `WPCPM_Semester_Report::OPT_AUTODRAFT_SINCE` |
| `wpcpm_report_epoch` | `WPCPM_Semester_Report::OPT_EPOCH` |
| `wpcpm_report_log` | `WPCPM_Semester_Report::OPT_LOG` |
| `wpcpm_report_state_version` | `WPCPM_Semester_Report::OPT_STATE_VERSION` |
| `wpcpm_roles_version` | `WPCPM_Roles::OPT_VERSION` |
| `wpcpm_roster_` | `WPCPM_Roster_Index::OPT_PREFIX` |
| `wpcpm_roster_counts` | `WPCPM_Roster_Index::OPT_COUNTS` |
| `wpcpm_roster_unlinked` | `WPCPM_Roster_Index::OPT_UNLINKED` |
| `wpcpm_sapp_decided_backfilled` | `WPCPM_Sponsor_Application::OPT_BACKFILL` |
| `wpcpm_sapp_lock_` | `WPCPM_Sponsor_Approval::LOCK_PREFIX` (the per-application approval locks) |
| `wpcpm_settings` | `WPCPM_Settings::OPT_NAME` |
| `wpcpm_settings_version` | `WPCPM_Settings::OPT_VERSION` |
| `wpcpm_sponsor_agr_` | `WPCPM_Sponsor_Agreement::OPT_PREFIX` (the per-sponsor transition locks) |
| `wpcpm_sponsor_application_log` | `WPCPM_Sponsor_Application::OPT_LOG` |
| `wpcpm_sponsor_application_page_id` | `WPCPM_Sponsor_Application::OPT_PAGE` |
| `wpcpm_sponsor_logo_` | `WPCPM_Sponsors_Index::OPT_LOGO_PREFIX` |
| `wpcpm_sponsor_page_id` | `WPCPM_Sponsors_Dashboard::OPT_PAGE` |
| `wpcpm_sponsor_page_title_fixed` | `WPCPM_Sponsors_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_sponsors_attention` | `WPCPM_Sponsors::TRANSIENT_ATTENTION` (a transient, a minute long) |
| `wpcpm_sponsors_index` | `WPCPM_Sponsors_Index::OPT_NAME` |
| `wpcpm_sponsors_last_error` | `WPCPM_Sponsors_Sync::OPT_ERROR` |
| `wpcpm_sponsors_last_sync` | `WPCPM_Sponsors_Sync::OPT_LAST` |
| `wpcpm_sponsors_lock` | `WPCPM_Sponsors_Sync::OPT_LOCK` |
| `wpcpm_sponsors_report` | `WPCPM_Sponsors_Sync::OPT_REPORT` |
| `wpcpm_sponsors_state` | `WPCPM_Sponsors_Sync::OPT_STATE` |
| `wpcpm_sponsors_sync_shrink` | `WPCPM_Sponsors_Sync::OPT_SHRINK` (a read that lost most of the table, waiting for the next run to confirm it) |
| `wpcpm_student_page_id` | `WPCPM_Students_Dashboard::OPT_PAGE` |
| `wpcpm_student_page_title_fixed` | `WPCPM_Students_Dashboard::OPT_TITLE_FIXED` |
| `wpcpm_students_last_error` | `WPCPM_Students_Sync::OPT_ERROR` |
| `wpcpm_students_last_sync` | `WPCPM_Students_Sync::OPT_LAST` |
| `wpcpm_students_lock` | `WPCPM_Students_Sync::OPT_LOCK` |
| `wpcpm_students_report` | `WPCPM_Students_Sync::OPT_REPORT` |
| `wpcpm_students_state` | `WPCPM_Students_Sync::OPT_STATE` |
| `wpcpm_team_members` | `WPCPM_Sponsors_Index::OPT_TEAM` |
