# Emails: templates you can edit and a 30-day log

Design note, 10 October 2026, for the main line from 1.122.18. Status: written for the owner's review; nothing is built yet.

## Why

The owner asked for an **Emails** section in Tools: every email the site sends, listed and editable, and a log of the last month that an Administrator can search and filter, by the module that sent an email and by who received it, to check that a notification really went out.

Today no email can be edited without a code change, and the only record of sending is a list of the newest 100 plugin emails under Settings > Mail. On the live site on 10 October 2026 those 100 entries covered 1 to 9 October only, so the question "did she get the email three weeks ago?" has no answer.

## The owner's decisions (10 October 2026)

1. A new tool, **Emails**, in Tools, for Administrators.
2. Every plugin email has an editable subject and body, and all of them end with one shared **signature and footer** that is edited once (option B).
3. The log records **every email the site sends**, the plugin's and WordPress's own and the Two Factor plugin's (option A).
4. The log keeps **who, what, when and status, never the message text** (option A).
5. The wording moves into a **template registry** (approach 1).
6. Connecting an external email provider, and the delivery status it reports, is the **next version**, designed for here and specified separately.
7. The welcome emails that Airtable automations send today **will move to the site**; when they do, they join the registry and the log like any other.
8. Separately, the domain's duplicate SPF record was removed on 10 October 2026: two `v=spf1` records made any SPF check of wordpresseducation.org itself fail; the combined one stays. The site's own emails were not affected, since their host sends them from its own bounce domain and signs them for wordpresseducation.org; a test after the change passed SPF, DKIM and DMARC and arrived in the inbox.

## What the site does today

- **One sending path.** `WPCPM_Mail::send()` and `send_to()` call a builder, a closure that returns the subject and the body, run in the recipient's language, then `hand_off()` applies the `wpcpm_mail` filter and calls `wp_mail()` (`includes/class-wpcpm-mail.php`). Emails are plain text with no layout and no From header, so they go out from WordPress's default address, `wordpress@wordpresseducation.org`. Some set a Reply-To.
- **44 emails through that path, plus one direct `wp_mail()`** (the inventory sent to the site's address when the plugin is uninstalled). Every subject and body is a translatable string written inside its builder. Invitations are WordPress's new-user email, rewritten in the `wp_new_user_notification_email` filter.
- **Secrets in some bodies:** the invitations carry WordPress's set-password link, the institution invite an accept link, the institution application a signed verify link, and the code-problem report the last four characters of a code. The Two Factor plugin's emails carry a one-time sign-in code and WordPress's password reset a reset link.
- **The small log.** The option `wpcpm_mail_log` keeps the newest 100 plugin emails as time, masked address, context and whether WordPress reported success (`wp_mail_succeeded`, `wp_mail_failed`). Settings > Mail shows 25 of them; its subject column has always been blank, because no subject is stored. The Administrator Dashboard's health card shows the latest one.
- **No translations ship.** The plugin carries only its template file, and the site and its users run in English, so every email is in English in practice.
- **No custom database table exists yet.** Components upgrade by a version stamp in an option and a `maybe_upgrade()` on `init`, because updates arrive by replacing files, not by activation.

## What an Administrator sees

A new tool, **Emails** (`wpcpm-tool-emails`), under the plugin's menu in wp-admin, behind `wpcpm_manage_program` like every tool. Three tabs:

1. **Templates.** The plugin's emails grouped by module: Invitations, Mentor calls, Institutions, Semester reports, Sponsors. Each row names the email, who receives it and what sends it ("Call booked: student copy, to the student, when a student books a call"), its state (**Shipped text**, **Edited**, or **Edited, and the shipped text has changed since**), and who edited it last and when. **Edit** opens the editor.
2. **Signature and footer.** The one block every plugin email ends with, its editor and a preview.
3. **Log.** Every email the site sent in the last 30 days, newest first, with search and filters.

What moves:

- **Settings > Mail.** Its "Email me the ... invitation" buttons become **Send me a test** on every template, and its log card is replaced by a link to the **Log** tab. Settings it holds stay where they are.
- **The Administrator Dashboard's health card** reads its latest email, and any failure, from the new log, and links to it.

What stays out: WordPress's and the Two Factor plugin's emails appear in the Log only, never in Templates. The uninstall inventory is not moved into the registry; the log records it like any other email.

## Templates

### The registry

`WPCPM_Mail_Templates` (`includes/mail/class-wpcpm-mail-templates.php`) holds every plugin email. Each module's emails are defined in their own file under `includes/mail/templates/` (invitations, calls, institutions, reports, sponsors), each returning a list of entries:

| Field | Meaning |
| --- | --- |
| `id` | Stable key, the log context where one exists (`call-booked-student`); a context that covers two emails today gets one id per email. |
| `label` | The name the Templates tab shows. |
| `module` | Invitations, Mentor calls, Institutions, Semester reports or Sponsors. |
| `audience` | Who receives it: Student, Mentor, Institution member, Sponsor member, Administrator, Applicant (no account). |
| `trigger` | One plain sentence: what sends it. |
| `subject`, `body` | The shipped text, written with `{placeholders}`, translatable as today. |
| `placeholders` | Each placeholder's name, what it means, an example value, and whether it is required. |

The shipped text is **today's wording, word for word**. Nothing a recipient sees changes until an Administrator edits a template, except the footer, which arrives with the second release.

### Sending

A builder no longer writes sentences. It gathers values (names, dates, links, a typed note) and calls `WPCPM_Mail_Templates::render( $id, $values )`, which returns the subject and the body: the Administrator's edit when there is one, the shipped text otherwise, with the footer appended.

- **Placeholders** are replaced by their values as plain text; a value is never read as a placeholder itself.
- **Optional details:** a line whose optional placeholders are all empty is left out, as the code leaves out "Topic: ..." today when a call has none. A line that holds a required placeholder is never left out.
- **Counts and lists** reach the template already worded ("3 sessions", one line per date), so the template never needs plural rules. Where a builder needs more than values, its entry may name a small `values` callback that prepares them; the wording itself never lives in code again.
- **Language:** the builder still runs in the recipient's language. An edited template is sent as edited for everyone; an unedited one keeps translating, should translations ship one day.
- **An edit that no longer fits:** if an update removes a placeholder that an edited template still uses, that edit is set aside, the shipped text is sent, and the Templates tab says why ("Edit set aside: it uses {topic}, which this email no longer has").

### The editor

One screen per email: a one-line **Subject**, a plain-text **Body**, the email's placeholders beside them (each with what it means and an example, such as `{student_name}`, Ada Okonjo), and a preview with the example values and the footer. Three buttons:

- **Save.**
- **Send me a test:** the email with its example values, to the address of the Administrator who pressed it only, with "[Test]" before its subject, and marked **Test** in the log.
- **Restore the original:** asks first, then drops the edit.

A save is refused, nothing is stored, and the form comes back as it was typed, when:

- a required placeholder is missing ("This email needs {set_password_link}");
- a placeholder is not one of this email's ("{student_nmae} is not a placeholder of this email");
- WordPress would remove part of the text (the rule every other form follows, `WPCPM_Typed_Text::cleaner_loses()`);
- the subject is longer than 200 characters or the body longer than 10,000.

**When an update changes an email you have edited:** your edit keeps being sent, the state reads **Edited, and the shipped text has changed since**, and the editor shows the new shipped text beside yours. Each edit stores a fingerprint of the shipped text it was made from, which is how the site tells.

### Signature and footer

One block, added after a line holding `-- ` at the end of every plugin email. Its placeholders: `{site_name}`, `{dashboard_link}` (empty for a recipient with no account, so that line is left out) and `{program_contact}`, an address from a new setting, **Contact address in emails**, which the Administrators read (empty leaves its sentence out). The default, for the owner to rewrite in review:

```
WordPress Credits, WordPress Education
{dashboard_link}
You receive this email because you take part in the WordPress Credits program. Questions? Write to {program_contact}.
```

The footer is not added to WordPress's or the Two Factor plugin's emails.

### Storage

One option, `wpcpm_mail_templates`, not autoloaded: for each edited template its subject, body, who saved it, when, and the fingerprint of the shipped text; and the footer with who saved it and when. No revision history in this version.

## The log

### What each entry holds

One entry per recipient (To, Cc and Bcc each get their own), kept 30 days:

| Column | Holds |
| --- | --- |
| When | The time, stored in UTC, shown in the site's time zone. |
| To | The full address, and, when it belongs to an account, the person's name at the time of sending, linked to the account. |
| Recipient type | Student, Mentor, Institution member, Sponsor member, Administrator, Applicant (no account) or Other, from the account at the moment of sending; the template's audience decides for a person who holds several roles. A later change of role does not rewrite it. |
| Module | Invitations, Mentor calls, Institutions, Semester reports, Sponsors, WordPress, Two Factor or Other. |
| Email | The template's name; for WordPress's own the kind, such as "Password reset"; "Other email" when nothing names it. |
| Subject | As sent. |
| Status | **Handed to the mail server**, **Failed** with WordPress's error message, or **Not confirmed** when another plugin took the email over before WordPress could say. Tests are marked **Test**. |
| Delivery | Reserved for the next version's provider, hidden until then. |

Never kept: the message text, attachments, or anything secret. A line under the table says that "Handed to the mail server" means the site passed the email on, not that it reached the inbox.

### How the site records it

- **What it is.** When `WPCPM_Mail` sends, it already names the email (its context); that name now carries the template id. WordPress's own emails are recognized from the filters they pass through (`retrieve_password_notification_email`, `password_change_email`, `email_change_email`, `wp_new_user_notification_email_admin`, `wp_password_change_notification_email`, the update and recovery-mode emails); the Two Factor plugin's from its own email filters, whose names are checked against the version on the site (0.17.0) before the first release relies on them. Anything else is Other.
- **When it is written.** The `wp_mail` filter, run last, notes the recipients and the subject; `wp_mail_succeeded` and `wp_mail_failed` write the entries with their status. A `pre_wp_mail` value from another plugin, which skips both, writes them as **Not confirmed**. (The local copies swallow mail that way, so their trials expect **Not confirmed**.)
- **Where.** A new table, `{prefix}wpcpm_mail_log`: an id; the time; the address (191 characters); the user id, null without an account; the name at sending; the recipient type; the module; the template id; the subject (255 characters); the status; the error (255 characters); a test flag; and two columns for the next version, delivery and its time, null for now. Indexes on the time, the address, the module with the time, the template, and the user id. It is created and upgraded by a version stamp, `wpcpm_mail_log_version`, on `init`, as the plugin's other stores are.
- **How big.** About 330 plugin emails a month were measured (100 from 1 to 9 October 2026), before WordPress's and the Two Factor plugin's are counted; the table stays in the low thousands of rows.
- **Cleanup.** A daily job deletes entries older than 30 days, a thousand at a time; the Log tab runs it too when it has not run for a day.

### Finding an email

- One search box matching the address, the name and the subject.
- Filters: **Module**, **Recipient type** and **Status** as plain lists; **Email** as one field that drops down, takes typing and narrows as you type (the owner's rule for long lists); **When**: today, the last 7 days, the last 30 days, or from and to dates.
- 50 per page, with the count shown ("212 emails match").

### Privacy

- Administrators only.
- Entries older than 30 days are deleted.
- WordPress's **Export Personal Data** and **Erase Personal Data** tools include a person's entries, found by address.
- Uninstalling the plugin drops the table and deletes `wpcpm_mail_log_version` and `wpcpm_mail_templates`.

### The small log's 100 entries

The first release moves them into the table with their masked addresses as stored, their context as the template, and their status, then deletes the option. Everything that read the option (`WPCPM_Mail::failures()`, the health card) reads the table.

## Releases and proof

Four releases on the main line, each reviewed, gated, built, tried on the local copy and deployed on the owner's yes:

1. **1.122.18: the log.** The table, the recording, the Log tab, the cleanup, the health card, the Export and Erase tools, and the move of the 100 entries. First, because it answers "was it sent?" at once and needs no template.
2. **1.122.19: the registry and the editor, with the Invitations** (the four invitations and the test-invitation sample), the **Signature and footer** tab, the **Contact address in emails** setting, and **Send me a test** in place of the Settings > Mail buttons. The footer starts appearing in every plugin email with this release.
3. **1.122.20: Mentor calls and Semester reports**, 12 emails.
4. **1.122.21: Institutions and Sponsors**, 27 emails.

How each step is proved:

- **Word for word.** Before a module's emails move, a test records what each builder writes today for a set of example inputs, including the optional lines left out and the counts; after the move, the registry's shipped text with the same inputs must give the same subject and body byte for byte.
- **Every template:** each placeholder used is declared, each required one is present, none is left unfilled after rendering.
- **The editor:** each refusal above, the kept typing, Restore, the test send going to the presser only.
- **The log:** entries for a sent, a failed and a taken-over email; one entry per recipient; no body stored; the cleanup; the Export and Erase tools; the move of the old entries; the table's creation and upgrade.
- **The local copy:** each module's emails sent into the copy's safety log and read back in the Log tab, with the filters and search exercised.

## Not in this version

- An external email provider and the **Delivery** column it fills: the next version.
- The welcome emails now sent by Airtable automations: they join when they move to the site.
- A revision history of edits, a CSV download of the log, editing WordPress's or the Two Factor plugin's emails, HTML emails.

## Open for the owner's review

- The footer's wording (the default above).
- Which mailbox `{program_contact}` should name.
- The templates' names, which are this design's suggestions.

## Risks

- **Builders that do not reduce to values and lines.** Some builders branch more than the optional-line rule covers. Such an email gets a `values` callback, or two templates where it really sends two different emails; the word-for-word test tells which.
- **The Two Factor plugin's email filters** may differ from what this design expects; then its emails are logged as Other until named.
- **The footer** changes every plugin email in one release; its text is the owner's to approve first.
