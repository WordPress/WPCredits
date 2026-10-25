# Emails E2: the template registry, the Invitations and the signature and footer, Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the plugin's emails editable under WPCredits Program > Tools > Emails, starting with the invitations; end every plugin email with one signature and footer, edited once; add the Contact address in emails setting the footer names; and put Send me a test, on each template's editor and on the footer's tab, in place of the Settings > Mail sample buttons.

**Architecture:** A template registry, `WPCPM_Mail_Templates`, adds to a catalog id what makes an email editable: its shipped subject and body written with `{placeholders}`, what each placeholder means with an example, and one sentence saying what sends the email. Names, areas and audiences stay the catalog's (`WPCPM_Mail_Catalog`). Each area's emails are defined in a file of their own under `includes/mail/templates/`; in this release, the Invitations. `WPCPM_Mail::welcome_email()` stops writing sentences: it chooses the template and works out its values, and `render()` returns the subject and the body, an Administrator's edit while it fits, the shipped text otherwise. One option, `wpcpm_mail_templates`, not autoloaded, holds the edits and the footer's, each with who saved it, when, and a fingerprint of the shipped text it was made from. The footer is added in one place for every email that leaves through `WPCPM_Mail::hand_off()`, and in `welcome_email()` for the invitations WordPress sends itself. A test is an ordinary send whose builder says it is a test, so the mail log's capture marks it Test under the template's own id. The Emails tool gains three tabs (Templates, Signature and footer, Log), an editor with Save, Send me a test and Restore the original, and a Settings section holding Contact address in emails; Settings > Mail loses its sample buttons and their handler.

**Tech Stack:** WordPress 7.1 plugin PHP (7.4 and 8.x), WordPress options, the plugin's `bin/test-*.php` suites (plain PHP with stubs), `WPCPM_Typed_Text` and `WPCPM_Flash` for a refused save and the typing it keeps, `WPCPM_Screen_Tabs` for the tab bar, and the tools' Settings section (`WPCPM_Tool::render_settings()`).

**Spec:** `docs/specs/2026-10-10-emails-tool-design.md` (approved by the owner on 10 October 2026). This plan covers its second release, "1.122.19: the registry and the editor, with the Invitations", and follows the plan rulings of 10 October 2026, E2-1 to E2-10 (`.superpowers/sdd/2026-10-10-emails-e2-plan-rulings.md`). The first release's plan, `docs/plans/2026-10-10-emails-e1-log.md`, is live as 1.122.18.

**Order:** the recording of what the invitations say today, before anything moves (Task 1); the registry and the Invitations' shipped text (Task 2); the invitations built from it, word for word (Task 3); the Administrators' edits, their rules and their states (Task 4); the signature and footer on every email (Task 5); the test send (Task 6); the Emails screen's tabs, the Templates list, the editor and its three buttons (Task 7); Contact address in emails (Task 8); Settings > Mail without its sample buttons (Task 9); the guide, the version, the changelog and the template (Task 10).

**Where the rulings left a choice, this plan chooses:**

- **E2-2, the institution invitation: two templates.** The invitation an institution's member is sent says three different sentences when the institution's Collaboration Agreement is on file (the opening, the next step, and the label above the dashboard's address), so one template with optional lines could say them only by moving wording back into code, which the spec forbids. So `invite-institution` stays the one for an agreement still to sign, and `invite-institution-settled` ("Invitation: institution, agreement on file") joins the catalog in Task 2, where the catalog's suite is brought to it.
- **E2-6, how a test is logged: under the template's own id, with a test mark handed beside the id.** The builder a test hands `WPCPM_Mail::send()` says `'test' => true`; `hand_off()` keeps it beside the context, and the capture takes it with `WPCPM_Mail::take_test()` and marks the rows Test. That is one flag on a path every email already takes, where a second family of ids would have doubled the catalog. The footer's test, which belongs to no template, is the catalog's new `footer-test` (area Other, to an Administrator). The four `test-*` ids stay in the catalog, so the Log goes on naming the rows they left for the 30 days it keeps them; nothing sends them after Task 9, and the catalog's suite pins that.
- **The set-password link.** `{set_password_link}` holds WordPress's own lines, its sentence and the link, kept whole as the code kept them, so a change in how WordPress words them arrives on its own and the shipped text is word for word what 1.122.18 wrote for WordPress 6.5's wording and for 7.1's. `{username}` is the username where those lines do not show it, which on WordPress 7.1 is always.
- **The set-password link goes in the body, never in the subject** (ruling PR-1). It is `'body_only' => true`: a subject is a header, it is never a link a person can follow, and it is the one part of an email the Log keeps, for 30 days, shown to Administrators and handed out by Export Personal Data, so the live key may not reach it. A save that puts it in the subject is refused ("Nothing was saved: {set_password_link} goes in the body, never in the subject."), a stored edit that does is set aside and the shipped text sent, and `fill()` keeps every subject to one line, each line break in a value one space.
- **What "left out" means.** A sentence ends at a full stop followed by a space, so "Questions? Write to {program_contact}." is one sentence and goes whole; a question or exclamation mark ends none, and the full stop of "e.g." followed by a space ends one; a line left with nothing goes; a line ending in a colon directly above a line that went goes with it ("Your Student Report Card:" above an empty `{dashboard_link}`); and a blank line the lines that went would leave doubled, or at the start or the end, goes too. These four rules reproduce every optional line the invitations had, byte for byte, and the footer's two. They read English punctuation, so the editor and the guide say what a sentence is, and the translators comments on the strings they act on say what a translation must keep (ruling PR-5).
- **Contact address in emails is the Emails tool's setting**, `mail_contact` in the tool's own scope (`tool:emails`), drawn by the tool's Settings section at the top of the Signature and footer tab, beside the footer that names it. `WPCPM_Tool::settings_url()` (new, the tool's screen by default) says where a tool's Settings section is, so its Save comes back there and the Connection tab's first line links it.
- **Send me a test sends the email as it is saved**, not as it is typed, so what the test shows is what a recipient gets (ruling PR-3). It is the Save form's second submit button (`name="wpcpm_then" value="test"`), so whatever is in the boxes reaches the handler: pressed with the boxes as the editor drew them, it sends the test; pressed with typing not saved yet, it sends nothing, keeps every byte typed, as a refused save does, and says "Nothing was sent: what you typed is not saved yet. Save it first, then send the test." The form's busy word is "Working", since it carries both buttons. The footer's tab works the same way.
- **The footer is added before the `wpcpm_mail` filter**, so a site's filter sees the email as it goes out.
- **The footer is added once, where the email leaves, and `render()` returns none.** The spec has `render()` return the body "with the footer appended"; here `render()` returns the subject and the body alone, and the footer is added in the two places an email leaves, `WPCPM_Mail::hand_off()` and `welcome_email()`, so a builder that renders a template and sends it through `send()` gets one footer, and no builder adds one itself.
- **A body that already names the recipient's dashboard leaves the footer's address line out** (ruling PR-2): `add_footer()` empties `{dashboard_link}` when the body holds that address, as an invitation does under its last label, by the plugin's own rule that no address appears twice (the login page's line in `welcome_email()`, pinned in bin/test-mail.php).
- **Saving an edit again clears "Edited, and the shipped text has changed since"**: the save stores the fingerprint of the shipped text as it is then. The editor's notice and the guide say so.
- **`all()` is not memoized.** It reads every area's file on each `get()`; E2 has one, a small one. Memoizing it per locale waits for E3, where the second area file arrives (ruling PR-12).
- **No commit between Tasks 5 and 9 is zipped or deployed.** From Task 5 every email gets the footer, while Settings > Mail's old sample handler (`WPCPM_Mail::handle_test()`, which calls `welcome_email()` inside a `send()` builder) is still there until Task 9, and a sample it sent would carry two footers and a "%%" in its subject. Only the release's last commit is built.

## Global Constraints

- Version **1.122.19** in the plugin header (`Version:`), `WPCPM_VERSION` and readme.txt's `Stable tag`, with a changelog entry and the translation template regenerated (`sh bin/make-pot.sh`).
- The footer ships as the owner approved it on 10 October 2026, word for word, as one translatable string (the spec's default with the first sentence of its third line reworded, so it holds for applicants, declined applicants and Administrators too):

  ```text
  WordPress Credits, WordPress Education
  {dashboard_link}
  This email comes from the WordPress Credits program. Questions? Write to {program_contact}.
  ```

  It is added after a blank line and a line holding `-- `, in the line ending of the body it ends, to every email that leaves through `WPCPM_Mail` and to the invitations WordPress sends through `wp_new_user_notification_email`, and never to WordPress's or the Two Factor plugin's own emails. `{dashboard_link}` is empty for a recipient with no account, and for an email whose body already holds that address, so no address appears twice; `{program_contact}` is empty while Contact address in emails is, as it ships. Four suites pin the wording, and change with it: bin/test-mail-templates.php ("the footer ships as the owner approved it"), bin/test-mail.php (`$footer`), bin/test-invitation-text.php (`footer_of()`) and bin/test-emails-templates.php.
- Word for word: the shipped text of every email that moves into the registry is what the code wrote before the move, byte for byte, the footer aside. `bin/test-invitation-text.php` records the invitations from 1.122.18, through the real `WPCPM_Mail::welcome_email()`, before they move (Task 1), and holds them to the recording after (Tasks 3 and 5). The recording is never edited by hand and never made twice: `--record` refuses once the file exists, and the suite pins the file's sha256.
- The catalog stays the one list of names, areas and audiences: a template never repeats a label, an area or an audience, and every template's id is a catalog id.
- US English; plain hyphens only, never an em or en dash, in code, comments, strings and docs.
- Full product names: Administrator Dashboard, Student Report Card, Mentor Report Card, Student Duplicate Finder, Collaboration Agreement; the program's managers are "Administrators".
- No translated string anywhere says "module" (bin/test-admin-menu.php refuses one); the screen word is **Area**. The code keeps `module` as the catalog's key.
- No `!important` in any plugin stylesheet, and every box a stylesheet lets scroll carries a position (bin/test-institution-panel.php refuses both).
- Examples and test data use invented names and `@example.test` addresses, never real people or institutions (bin/test-fixtures.php); no fixture holds an address of any kind. No test or fixture holds a real set-password key: the fakes are `TEST-KEY-NOT-A-REAL-LINK` (the recording) and `EXAMPLE-KEY-NOT-A-REAL-LINK` (the examples a preview and a test are filled with).
- The log never stores message text (E1's rule, unchanged). The registry's option stores the Administrators' wording of the emails, never a recipient's values.
- Administrators only: every screen and every handler checks `WPCPM_Roles::CAP_MANAGE`; the editor's two forms (Save, whose second button is Send me a test, and Restore the original) ask the capability first, then their nonce, as the tools' buttons do.
- Send me a test goes to the address of the account that pressed it, read from the account, never from a field a form posted; and it sends the text as saved: pressed with typing not saved yet, it sends nothing and the form comes back as it was typed.
- A save is refused, stores nothing, and the form comes back as it was typed (`WPCPM_Typed_Text::keep()` and `kept()`), when a box is empty (the signature and footer's included: every email ends with it), a required placeholder is missing from the body, a placeholder that goes in the body is in the subject, a placeholder is not the email's, WordPress would remove part of the text (`WPCPM_Typed_Text::cleaner_loses()`, as every form), or the subject passes 200 characters or the body 10,000. Restore the original asks first.
- An edited template is sent as edited for every recipient; an unedited one is built with `__()` in the recipient's language, as before.
- A choice from a long list is one field that drops down, takes typing and narrows (the owner's rule). This release adds no long list, so no combobox changes: the Templates tab lists five emails, and the Log's Email field is untouched (its suite runs `assets/js/switcher.js` through node, as before, and lists the catalog's two new names).
- Every link to the Log carries `&tab=log`; the tool's own address opens Templates.
- Every method in a new file under `includes/` has a docblock; `sh bin/check-standards.sh` gains no error (main has 2).
- Every task ends with the whole battery: every `bin/test-*.php` in its own process, all passing, and `php bin/check-references.php`, as its last step before the commit shows.
- Never `git stash`. Commit after each task's battery passes.
- Public text (this repository is mirrored publicly) names no unreleased or private work.

## Review Focus

1. **An edited invitation that drops, misspells or misplaces the set-password link: it goes in its body, never the subject.** Save refuses "Nothing was saved: this email needs {set_password_link}." when the body lacks it, and "Nothing was saved: {set_password_link} goes in the body, never in the subject." when the subject holds it, and stores nothing; a misspelled `{set_pasword_link}` is the first refusal, since the link it names is missing, and `{studnet_name}` is "not a placeholder of this email". An edit already stored without the link in its body, or with it in its subject, as an update that made a placeholder required or body-only could leave one, is set aside at send time and the shipped text sent, so no invitation leaves without its link and no subject, which the Log keeps, carries the key; and a subject is kept to one line whatever its values hold. Pinned in Task 4 (and the one-line subject in Task 2), and on the screen in Task 7.
2. **An edit made before a later release changes the shipped text.** Each edit keeps a fingerprint of the shipped text it was made from, built in the site's own language. When an update changes that text, the edit is still the one sent, the state reads "Edited, and the shipped text has changed since", and the editor opens the new shipped text under it to compare, saying that saving the edit again clears the state; an update that takes away a placeholder an edit uses sets the edit aside and says which. Pinned in Task 4 and Task 7.
3. **The footer on every real email on day one: the approved wording, applicants and declined applicants, and no doubled dashboard address.** The owner's wording, word for word, pinned by four suites; an email to an applicant or a declined applicant leaves through `WPCPM_Mail::send_to()` with no account, so `{dashboard_link}` is empty and its line goes with no blank line left behind; while Contact address in emails is empty, "Questions? Write to {program_contact}." goes and the rest of its line stays; an invitation, whose body ends with its dashboard's address, gets no second copy of it in the footer; the body before the footer is kept byte for byte and ends as it ended; and the footer reads nothing it does not print. Pinned in Task 5, every invitation against the recording; the owner reads it on the local copy before the deploy.
4. **A refused save keeping the typing, and Send me a test pressed with typing not saved yet.** The editor comes back with the refusal's sentence and with every byte that was typed in both boxes, a "<", a quote mark and a typed-out `&amp;` among them, each line break as its box counts it, given back once; and a test pressed with typing not saved yet sends nothing, stores nothing and gives the typing back the same way, while one pressed with the boxes as drawn sends one email, To the presser's own address alone, whatever the press posts (`to`, `email`, `cc`, `user_id`). Pinned in Task 7 (the test's recipient in Task 6 too).
5. **The left-out rules on an Administrator's own text: only ". " ends a sentence, plus the colon rule.** A question or exclamation mark ends no sentence, so in "Thanks! Write to {program_contact}." with the contact empty the whole sentence goes, "Thanks!" with it; the full stop of "e.g." followed by a space ends one, so what follows goes on its own; and a line ending in a colon goes with the line under it when that one goes. The rules are pinned in Task 2, said in the editor's placeholder note (Task 7) and the guide (Task 10), and the translators comments on the invitations' labels and the username's sentence tell a translator what to keep.

## Execution

Subagent-driven, strictly sequential in one worktree (`.worktrees/emails-e2`, branch `feature/emails-e2` from main): the tasks share files (`class-wpcpm-mail-templates.php` grows across Tasks 2, 4, 5 and 6, `class-wpcpm-emails.php` across Tasks 7 and 8), so none runs beside another. Every task gets its task review, and the most capable reviewers go to Task 3 (word for word), Task 5 (the footer on every email) and Task 7 (the editor and its handlers).

---

## File Structure

| File | Responsibility |
| --- | --- |
| `bin/test-invitation-text.php`, `bin/fixtures/invitations-1-122-18.json` (new, Task 1) | The word-for-word recording of every kind of invitation, made from 1.122.18 through the real filter, and the suite that holds the registry to it. |
| `includes/mail/class-wpcpm-mail-templates.php` (new, Tasks 2, 4, 5, 6) | `WPCPM_Mail_Templates`: the registry (`all()`, `get()`), filling a text (`fill()`, `render()`), the examples, the edits (`save()`, `restore()`, `state()`, the fingerprint), the signature and footer (`footer_shipped()`, `add_footer()`, its values), and the test send (`send_test()`). |
| `includes/mail/templates/invitations.php` (new, Task 2) | The Invitations area's five emails as shipped, each sentence under the translatable string `welcome_email()` used. |
| `includes/mail/class-wpcpm-mail-catalog.php` (Tasks 2 and 6) | Gains `invite-institution-settled` and `footer-test`. |
| `includes/class-wpcpm-mail.php` (Tasks 3, 5, 6, 9) | `welcome_email()` builds from the registry; `hand_off()` adds the footer and hands the test mark over (`take_test()`); `handle_test()` and `ACTION_TEST` go. |
| `includes/mail/class-wpcpm-mail-capture.php` (Task 6) | Takes the test mark beside the context and writes it. |
| `includes/tools/class-wpcpm-emails.php` (Tasks 7 and 8) | The tabs, the Templates list, the editor and its two handlers (Save, whose second button sends the test, and Restore the original), the Signature and footer tab, the tool's one setting. |
| `assets/css/emails.css` (Task 7) | The Templates list's state chips, the editor and the preview. |
| `includes/class-wpcpm-settings.php`, `includes/tools/class-wpcpm-tool.php`, `includes/class-wpcpm-settings-screen.php` (Tasks 8 and 9) | Contact address in emails (`mail_contact`, scope `tool:emails`), `WPCPM_Tool::settings_url()`, the Connection tab's four tools; the Mail tab without its sample buttons. |
| `wpcredits-program-manager.php`, `uninstall.php` (Tasks 2, 4, 10) | Load the registry; delete its option on uninstall; the version. |
| `bin/test-mail-templates.php`, `bin/test-emails-templates.php` (new); `bin/test-mail-catalog.php`, `bin/test-mail.php`, `bin/test-mail-capture.php`, `bin/test-institutions-accounts.php`, `bin/test-sponsors-accounts.php`, `bin/test-uninstall.php`, `bin/test-emails-screen.php`, `bin/test-submit-guard.php`, `bin/test-settings.php`, `bin/test-admin-menu.php`, `bin/test-handlers.php` | The suites, each in the task that changes what it reads. |
| `docs/sections/30-admin-wpadmin.md`, `docs/sections/31-admin-settings.md`, `docs/sections/32-admin-tools.md`, then `docs/*.md` and `docs/build/*.html` (Tasks 9 and 10); `readme.txt`, `languages/wpcredits-program-manager.pot` (Task 10) | The administrators' guide, the changelog and the template. |

Every suite is plain PHP run as `php bin/<suite>.php`, printing `ok` / `FAIL` lines and a last line `ALL PASS (N checks)` or `N FAILURE(S)`, and exiting non-zero on a failure. A new suite carries the `ck()` helper below, as every suite does (each new suite in this plan is written out whole, helper included):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

and ends with:

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

"The whole battery" is every suite in its own process, then the references check, which every task's last step runs:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the references check ends `- all resolve`.

How each change is written: "Create `path`" gives a new file whole; "In `path`, replace:" gives the exact lines to find, once, and the lines to put in their place; "replace the method `name()`, its docblock included" and "delete the method `name()`" act on the whole method, from the `/**` above it to its closing brace; "add before the class's closing brace" puts a section after the class's last method.

---

### Task 1: The recording: what every invitation says today, word for word

Before anything moves, a suite records what `WPCPM_Mail::welcome_email()` writes for every kind and variant of invitation, from today's code, into a fixture that is committed and never edited by hand; from then on the same suite builds the same invitations from the same inputs and compares them with the recording, byte for byte (ruling E2-3). It changes no code.

**Files:**
- Create: `bin/test-invitation-text.php`
- Create, by the suite's `--record` run: `bin/fixtures/invitations-1-122-18.json`

**Interfaces:**
- Consumes: `WPCPM_Mail::welcome_email( array $email, WP_User $user, string $blogname ): array` as 1.122.18 has it.
- Produces:
  - `bin/fixtures/invitations-1-122-18.json`: `{ "_comment": string, "cases": { "<case>": { "subject": string, "message": string } } }`, 18 cases: `student`, `mentor`, `institution`, `institution-settled` and `sponsor`, each `-7-1` (WordPress 7.1's wording, the dashboard page published), `-7-1-no-page` (no page published) and `-6-5` (WordPress 6.5's wording); then `mentor-and-institution-7-1`, `student-site-name-with-entities` and `student-site-name-decoded`.
  - In the suite: `cases(): array<string,array{login:string,roles:string[],version:string,page:bool,settled:bool,blogname:string}>`, `core_email( string $login, string $version ): array`, `invitation( array $case ): array{subject:string,message:string}`, which later tasks extend.
  - `--record` writes the file once: run again, it refuses ("The recording exists and is never made twice.") and exits 1, and the suite pins the file's sha256.

- [ ] **Step 1: Write the suite**

The inputs are invented (`ada.example` and four more), every address is `example.test`'s, and the key in every link is the fixed fake `TEST-KEY-NOT-A-REAL-LINK`. The dashboard classes and the institution agreement are stand-ins each case sets, so every branch of `welcome_email()` is taken: each kind, the institution's agreement on file or not, a page published or not, WordPress 6.5's username line or not, the mentor's wording for an account that is also an institution's member, a site name with entities in it, and a site name as WordPress hands it over, decoded (ruling PR-7), holding an "&", a "'" and a word in braces.

Create `bin/test-invitation-text.php`:

```php
<?php
/**
 * The invitations, word for word: what every kind of invitation says, as 1.122.18 wrote it.
 *
 * The invitations move into the template registry in 1.122.19, and the move must change nothing a
 * recipient reads but the footer. So before the move this suite recorded, through the real
 * `WPCPM_Mail::welcome_email()`, the subject and the body of every kind and variant of invitation
 * into bin/fixtures/invitations-1-122-18.json; from then on it builds the same invitations from the
 * same inputs and compares them with the recording, byte for byte.
 *
 * The inputs: each of the four kinds, the institution's twice (its Collaboration Agreement on file
 * or not), each with its dashboard page published and without one, each as WordPress 7.1 words its
 * new-account email (the keyed link alone) and as WordPress 6.5, the plugin's floor, does (the
 * username, the keyed link, the plain login page); an account holding two roles; a site name
 * holding entities; and a site name as WordPress hands it over, decoded. The names are invented,
 * the addresses are example.test's, and the key in every link is a fixed fake.
 *
 * The recording is made once: `--record` refuses when the file is there, and the suite pins the
 * file's sha256, so a recording made again from later code can never stand in for 1.122.18's.
 *
 * Run from the plugin root:  php bin/test-invitation-text.php
 * Record (once, against 1.122.18, before the move):  php bin/test-invitation-text.php --record
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

$GLOBALS['opts'] = array();

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id, $login, array $roles ) {
		$this->ID         = $id;
		$this->user_login = $login;
		$this->user_email = $login . '@example.test';
		$this->roles      = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_action() {}
function add_filter() {}
require_once __DIR__ . '/stubs/specialchars.php';

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** As above. */
class WPCPM_Sponsors_Dashboard {
	public static $url = '';
	public static function page_url() { return self::$url; }
}
/** Which institution an account belongs to: every institution account here belongs to one. */
class WPCPM_Institution_Members {
	public static function institution_of( $user ) { return 'recSEED000000016'; }
}
/** Whether that institution's Collaboration Agreement is on file, as a case says. */
class WPCPM_Institution_Agreement {
	public static $settled = false;
	public static function is_settled( $record ) { return self::$settled; }
}

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * What WordPress hands the new-account filter for an account, as one version words it.
 *
 * @param string $login   The account's username.
 * @param string $version '7.1' for the keyed link alone, '6.5' for the username, the link and the login page.
 * @return array
 */
function core_email( $login, $version ) {
	if ( '6.5' === $version ) {
		$message = "Username: $login\r\n\r\nTo set your password, visit the following address:\r\n\r\nhttps://example.test/wp-login.php?action=rp&key=TEST-KEY-NOT-A-REAL-LINK&login=$login\r\n\r\nhttps://example.test/wp-login.php\r\n";
	} else {
		$message = "To set your password, visit the following address:\r\n\r\nhttps://example.test/wp-login.php?login=$login&key=TEST-KEY-NOT-A-REAL-LINK&action=rp\r\n";
	}

	return array(
		'to'      => $login . '@example.test',
		'subject' => '[%s] Login Details',
		'message' => $message,
		'headers' => '',
	);
}

/**
 * Every case: the account, its roles, WordPress's wording, whether its dashboard page is published,
 * whether its institution's agreement is on file, and the site's name as WordPress hands it over.
 *
 * @return array<string, array>
 */
function cases() {
	$s = WPCPM_Roles::ROLE_STUDENT;
	$m = WPCPM_Roles::ROLE_MENTOR;
	$i = WPCPM_Roles::ROLE_INSTITUTION;
	$p = WPCPM_Roles::ROLE_SPONSOR;

	$out = array();

	foreach ( array(
		'student'             => array( 'ada.example', array( $s ), false ),
		'mentor'              => array( 'ben.example', array( $m ), false ),
		'institution'         => array( 'cy.example', array( $i ), false ),
		'institution-settled' => array( 'cy.example', array( $i ), true ),
		'sponsor'             => array( 'dee.example', array( $p ), false ),
	) as $kind => $row ) {
		foreach ( array( '7-1' => array( '7.1', true ), '7-1-no-page' => array( '7.1', false ), '6-5' => array( '6.5', true ) ) as $variant => $shape ) {
			$out[ $kind . '-' . $variant ] = array(
				'login'    => $row[0],
				'roles'    => $row[1],
				'version'  => $shape[0],
				'page'     => $shape[1],
				'settled'  => $row[2],
				'blogname' => 'WordPress Credits',
			);
		}
	}

	// A mentor who is also an institution's member is sent the mentor's invitation.
	$out['mentor-and-institution-7-1'] = array(
		'login'    => 'eli.example',
		'roles'    => array( $m, $i ),
		'version'  => '7.1',
		'page'     => true,
		'settled'  => false,
		'blogname' => 'WordPress Credits',
	);

	// A site name holding entities, which the subject reads back.
	$out['student-site-name-with-entities'] = array(
		'login'    => 'ada.example',
		'roles'    => array( $s ),
		'version'  => '7.1',
		'page'     => true,
		'settled'  => false,
		'blogname' => 'Ada &amp; Ben&#039;s Credits',
	);

	// The site's name as WordPress hands it over: decoded before the filter runs, so an "&" and a
	// "'" arrive as typed; and a word in braces in it, which is the site's name and nothing more.
	$out['student-site-name-decoded'] = array(
		'login'    => 'ada.example',
		'roles'    => array( $s ),
		'version'  => '7.1',
		'page'     => true,
		'settled'  => false,
		'blogname' => "Ada & Ben's {Credits}",
	);

	return $out;
}

/**
 * One case's invitation, through the real filter.
 *
 * @param array $case One of `cases()`.
 * @return array subject and message.
 */
function invitation( array $case ) {
	$page = $case['page'];

	WPCPM_Students_Dashboard::$url     = $page ? 'https://example.test/student-report-card/' : '';
	WPCPM_Mentors_Dashboard::$url      = $page ? 'https://example.test/mentor-report-card/' : '';
	WPCPM_Institutions_Dashboard::$url = $page ? 'https://example.test/institution-dashboard/' : '';
	WPCPM_Sponsors_Dashboard::$url     = $page ? 'https://example.test/sponsor-dashboard/' : '';
	WPCPM_Institution_Agreement::$settled = $case['settled'];

	$user  = new WP_User( 70, $case['login'], $case['roles'] );
	$email = WPCPM_Mail::welcome_email( core_email( $case['login'], $case['version'] ), $user, $case['blogname'] );

	return array(
		'subject' => $email['subject'],
		'message' => $email['message'],
	);
}

$fixture = __DIR__ . '/fixtures/invitations-1-122-18.json';

if ( in_array( '--record', $argv, true ) ) {
	// Made once, from 1.122.18: a recording made again would be made from whatever the code says
	// then, and would prove nothing.
	if ( file_exists( $fixture ) ) {
		fwrite( STDERR, "The recording exists and is never made twice.\n" );
		exit( 1 );
	}

	$recorded = array(
		'_comment' => 'What each invitation said in 1.122.18, recorded through WPCPM_Mail::welcome_email() by bin/test-invitation-text.php --record before the invitations moved into the template registry. Invented names, example.test addresses and a fake key. Never edit by hand: the registry is held to these bytes.',
		'cases'    => array(),
	);

	foreach ( cases() as $name => $case ) {
		$recorded['cases'][ $name ] = invitation( $case );
	}

	file_put_contents( $fixture, json_encode( $recorded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	printf( "Recorded %d invitations in %s\n", count( $recorded['cases'] ), basename( $fixture ) );

	exit( 0 );
}

$recorded = is_file( $fixture ) ? json_decode( (string) file_get_contents( $fixture ), true ) : null;
$cases    = cases();

ck( 'the recording is there, and holds every case', is_array( $recorded ) && isset( $recorded['cases'] ) ? array_keys( $recorded['cases'] ) : null, array_keys( $cases ) );
ck( 'the recording is the one made from 1.122.18', is_file( $fixture ) ? hash_file( 'sha256', $fixture ) : null, 'd6174c852747000d5164f20d25cc42d4568ce442f16224913673eea18622e5f1' );

foreach ( $cases as $name => $case ) {
	$want = isset( $recorded['cases'][ $name ] ) ? $recorded['cases'][ $name ] : array( 'subject' => null, 'message' => null );
	$got  = invitation( $case );

	ck( $name . ': the subject, byte for byte', $got['subject'], $want['subject'] );
	ck( $name . ': the body, byte for byte', $got['message'], $want['message'] );
}

ck( 'and the recording holds no real key: every link carries the fixed fake', is_file( $fixture ) && preg_match_all( '/key=([^&\s]+)/', (string) file_get_contents( $fixture ), $keys ) > 0 ? array_values( array_unique( $keys[1] ) ) : array(), array( 'TEST-KEY-NOT-A-REAL-LINK' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails, for want of a recording**

Run: `php bin/test-invitation-text.php`
Expected: every check fails against a recording that is not there yet, its hash among them, and the suite ends `39 FAILURE(S) (39 checks)`, exiting 1.

- [ ] **Step 3: Record today's invitations, once**

Run: `php bin/test-invitation-text.php --record`
Expected: `Recorded 18 invitations in invitations-1-122-18.json`. The file is written once, here, against the code as it is before this release, and is never edited by hand. `shasum -a 256 bin/fixtures/invitations-1-122-18.json` prints `d6174c852747000d5164f20d25cc42d4568ce442f16224913673eea18622e5f1`, the hash the suite pins: the recording depends on nothing but the code and the suite, so a different hash means the code or the suite differs from what this plan was proved against, and the task stops there.

Run it again: `php bin/test-invitation-text.php --record`
Expected: `The recording exists and is never made twice.`, exit 1, and the file's hash unchanged.

- [ ] **Step 4: Run it to verify it passes, and that the recording is a fixture the privacy checks accept**

Run, one per command: `php bin/test-invitation-text.php`, `php bin/test-fixtures.php`
Expected: `ALL PASS (39 checks)`; `ALL PASS` (no fixture holds an address of any kind, a refused name or a record ID).

- [ ] **Step 5: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add bin/test-invitation-text.php bin/fixtures/invitations-1-122-18.json
git commit -m "Emails E2: record what every invitation says in 1.122.18, before it moves"
```

---

### Task 2: The registry, and the Invitations' shipped text

The registry and its filling rules, and the Invitations area's five emails written with placeholders, each sentence under the very translatable string `welcome_email()` uses today. Nothing sends from the registry yet: Task 3 moves the invitations onto it.

**Files:**
- Create: `includes/mail/class-wpcpm-mail-templates.php`
- Create: `includes/mail/templates/invitations.php`
- Create: `bin/test-mail-templates.php`
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (`invite-institution-settled` after `invite-institution`)
- Modify: `wpcredits-program-manager.php` and `uninstall.php` (require the registry after the catalog, lines 47 and 52)
- Modify: `bin/test-mail-catalog.php` (the count, and the registry's ids)

**Interfaces:**
- Consumes: `WPCPM_Mail_Catalog::get( string $id ): ?array`, `WPCPM_Mail_Catalog::MODULE_INVITATIONS`, `WPCPM_Mail_Catalog::TYPE_*`; `WPCPM_Mail::site_name(): string`; the dashboards' `page_url(): string`.
- Produces:
  - `WPCPM_Mail_Templates::AREAS` (`array( 'invitations' => 'invitations' )`, area to its file), `PLACEHOLDER` (`/\{([A-Za-z0-9_-]+)\}/`).
  - `all(): array<string,array{id,label,module,audience,trigger,subject,body,eol,placeholders}>`; `get( string $id ): ?array`.
  - `placeholders_in( string $text ): string[]`; `required( array $placeholders ): string[]`; `body_only( array $placeholders ): string[]`, the placeholders that go in the body and never in the subject.
  - `fill( string $text, array $values, array $placeholders, string $eol = "\n", bool $shorten = true ): string`; with `$shorten` false, a subject's, every line break in a value is one space.
  - `render( string $id, array $values ): ?array{subject:string,body:string}` (the shipped text; Task 4 adds the edits). It returns no footer: the footer is added where the email leaves (Task 5).
  - `example_values( string $id ): array<string,string>`; `examples_of( array $placeholders ): array<string,string>` (an example is a string, or a closure called when a preview or a test asks).
  - `page_of_type( string $type ): string`, the dashboard page of a recipient type, '' when none is published.
  - A placeholder entry: `array( 'means' => string, 'example' => string|Closure, 'required' => bool )`, and `'body_only' => true` for one that goes in the body alone (absent means false). The Invitations' placeholders: `site_name`, `set_password_link` (required, body only), `username`, `login_url`, `dashboard_link`.
  - The catalog's `invite-institution-settled`: "Invitation: institution, agreement on file", area Invitations, to an institution member.

- [ ] **Step 1: Write the failing test**

Create `bin/test-mail-templates.php`:

```php
<?php
/**
 * The template registry: what each email says, and how a text is filled.
 *
 * - **The catalog stays the one list of names.** Every template's id is a catalog id, its name, area
 *   and audience are the catalog's, and the Invitations are the four invitations and the institution's
 *   second one, for an institution whose Collaboration Agreement is on file.
 * - **Every template is whole.** Each placeholder its text uses is one it declares, each required one
 *   is in the body, none that goes in the body alone is in the subject, every declared one says what
 *   it means and has an example, and filled with the examples no placeholder is left. The
 *   set-password link is required in every invitation, in its body.
 * - **Filling.** A value is never read as a placeholder; an optional sentence with nothing to say
 *   goes, and so does a line left with nothing, the colon line above it and the blank line it leaves
 *   doubled; only a full stop followed by a space ends a sentence; a required placeholder's line
 *   never goes; a subject is never shortened, and is kept to one line.
 *
 * Run from the plugin root:  php bin/test-mail-templates.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', array $roles = array() ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '.', $name ) );
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's `add_query_arg( $args, $url )`, as the example set-password link is built over the login page.
function add_query_arg( $args, $url ) { foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; } return $url; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function add_action() {}
function add_filter() {}
require_once __DIR__ . '/stubs/specialchars.php';

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';

/** The dashboards a template's example links, each published at an address of its own. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
/** As above. */
class WPCPM_Mentors_Dashboard {
	public static function page_url() { return 'https://example.test/mentor-report-card/'; }
}
/** As above. */
class WPCPM_Institutions_Dashboard {
	public static function page_url() { return 'https://example.test/institution-dashboard/'; }
}
/** As above. */
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

echo "=== The registry ===\n";

$all = WPCPM_Mail_Templates::all();

ck( 'the Invitations are the four invitations and the institution\'s second one, in that order', array_keys( $all ), array( 'invite-student', 'invite-mentor', 'invite-institution', 'invite-institution-settled', 'invite-sponsor' ) );

$named = array();
foreach ( $all as $id => $entry ) {
	$catalog      = WPCPM_Mail_Catalog::get( $id );
	$named[ $id ] = null !== $catalog && $catalog['label'] === $entry['label'] && $catalog['module'] === $entry['module'] && $catalog['audience'] === $entry['audience'];
}
ck( 'every template is a catalog id, and its name, area and audience are the catalog\'s', $named, array_fill_keys( array_keys( $all ), true ) );

$areas = array();
foreach ( WPCPM_Mail_Templates::AREAS as $module => $file ) {
	$defined = require __DIR__ . '/../includes/mail/templates/' . $file . '.php';
	foreach ( array_keys( $defined ) as $id ) {
		$areas[ $id ] = array( $module, WPCPM_Mail_Catalog::module_of( $id ) );
	}
}
ck( 'each area\'s file defines that area\'s emails, and every id it defines is the catalog\'s', array_values( array_filter( $areas, function ( $pair ) { return $pair[0] !== $pair[1]; } ) ), array() );
ck( 'and the registry names no email itself: no label, area or audience in a definition', array_values( array_filter( array_map( function ( $file ) { return preg_match( "/'(label|module|audience)'\s*=>/", (string) file_get_contents( __DIR__ . '/../includes/mail/templates/' . $file . '.php' ) ) ? $file : null; }, WPCPM_Mail_Templates::AREAS ) ) ), array() );
ck( 'the institution invitation for an agreement on file is the catalog\'s, in the Invitations area, to institution members', WPCPM_Mail_Catalog::get( 'invite-institution-settled' ), array( 'label' => 'Invitation: institution, agreement on file', 'module' => 'invitations', 'audience' => 'institution', 'test' => false ) );
ck( 'an id the registry does not hold is null', array( WPCPM_Mail_Templates::get( 'call-booked' ), WPCPM_Mail_Templates::render( 'no-such', array() ) ), array( null, null ) );

$whole = array();
foreach ( $all as $id => $entry ) {
	$used     = WPCPM_Mail_Templates::placeholders_in( $entry['subject'] . "\n" . $entry['body'] );
	$declared = array_keys( $entry['placeholders'] );
	$about    = array();

	foreach ( $entry['placeholders'] as $name => $placeholder ) {
		$about[] = '' !== (string) $placeholder['means'] && '.' === substr( $placeholder['means'], -1 ) && ( is_string( $placeholder['example'] ) || $placeholder['example'] instanceof Closure ) && is_bool( $placeholder['required'] ) && ( ! isset( $placeholder['body_only'] ) || is_bool( $placeholder['body_only'] ) );
	}

	$filled = WPCPM_Mail_Templates::render( $id, WPCPM_Mail_Templates::example_values( $id ) );

	$whole[ $id ] = array(
		'trigger'    => '' !== $entry['trigger'] && '.' === substr( $entry['trigger'], -1 ),
		'undeclared' => array_values( array_diff( $used, $declared ) ),
		'unused'     => array_values( array_diff( $declared, $used ) ),
		'missing'    => array_values( array_diff( WPCPM_Mail_Templates::required( $entry['placeholders'] ), WPCPM_Mail_Templates::placeholders_in( $entry['body'] ) ) ),
		'in_subject' => array_values( array_intersect( WPCPM_Mail_Templates::body_only( $entry['placeholders'] ), WPCPM_Mail_Templates::placeholders_in( $entry['subject'] ) ) ),
		'about'      => ! in_array( false, $about, true ),
		'left'       => WPCPM_Mail_Templates::placeholders_in( $filled['subject'] . $filled['body'] ),
		'eol'        => $entry['eol'],
		'link'       => array( WPCPM_Mail_Templates::required( $entry['placeholders'] ), WPCPM_Mail_Templates::body_only( $entry['placeholders'] ) ),
	);
}
$want_whole = array_fill_keys(
	array_keys( $all ),
	array( 'trigger' => true, 'undeclared' => array(), 'unused' => array(), 'missing' => array(), 'in_subject' => array(), 'about' => true, 'left' => array(), 'eol' => "\r\n", 'link' => array( array( 'set_password_link' ), array( 'set_password_link' ) ) )
);
ck( 'every template says what sends it, uses only the placeholders it declares and every one it declares, holds its required ones in its body and none that goes in the body in its subject, says what each means with an example, and leaves none unfilled; every invitation needs its set-password link, in its body, and keeps WordPress\'s line ending', $whole, $want_whole );

$student = WPCPM_Mail_Templates::render( 'invite-student', WPCPM_Mail_Templates::example_values( 'invite-student' ) );
ck( 'filled with its examples, an invitation carries a marked example link and the dashboard of its audience', array( false !== strpos( $student['body'], 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $student['body'], "Your Student Report Card:\r\nhttps://example.test/student-report-card/" ), $student['subject'] ), array( true, true, '[WordPress Credits] Welcome to the WordPress Credits Program' ) );

$words = array();
foreach ( $all as $entry ) {
	$words[] = $entry['trigger'];
	foreach ( $entry['placeholders'] as $placeholder ) {
		$words[] = $placeholder['means'];
	}
}
ck( 'no trigger or placeholder description says module', array_values( preg_grep( '/\bmodules?\b/i', $words ) ), array() );
ck( 'and no em or en dash in the registry or its definitions', preg_match( '/\x{2013}|\x{2014}/u', (string) file_get_contents( __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php' ) . (string) file_get_contents( __DIR__ . '/../includes/mail/templates/invitations.php' ) ), 0 );

echo "\n=== Filling a text ===\n";

$ph = array(
	'name'  => array( 'means' => 'A name.', 'example' => 'Ada', 'required' => true ),
	'topic' => array( 'means' => 'A topic.', 'example' => 'Blocks', 'required' => false ),
	'link'  => array( 'means' => 'A link.', 'example' => 'https://example.test/', 'required' => false ),
	'mail'  => array( 'means' => 'An address.', 'example' => 'help@example.test', 'required' => false ),
);

ck( 'each placeholder is replaced by its value, and a value is never read as a placeholder itself', WPCPM_Mail_Templates::fill( 'Hi {name}, about {topic}.', array( 'name' => '{topic}', 'topic' => 'Blocks' ), $ph ), 'Hi {topic}, about Blocks.' );
ck( 'a placeholder the email does not have is left as it was written', WPCPM_Mail_Templates::fill( 'Hi {name}, {nmae} and {Name}.', array( 'name' => 'Ada' ), $ph ), 'Hi Ada, {nmae} and {Name}.' );
ck( 'placeholders_in() reads a word in braces, each once, in order', WPCPM_Mail_Templates::placeholders_in( '{b} {a} {b} {not one} {set_password_link} {x-y}' ), array( 'b', 'a', 'set_password_link', 'x-y' ) );
ck( 'an optional sentence with nothing to say goes, and the rest of its line stays as it was spaced', WPCPM_Mail_Templates::fill( "You take part.  Questions? Write to {mail}.  Thanks.\n", array(), $ph ), "You take part.  Thanks.\n" );
ck( 'and with a value it stays', WPCPM_Mail_Templates::fill( 'You take part. Questions? Write to {mail}.', array( 'mail' => 'help@example.test' ), $ph ), 'You take part. Questions? Write to help@example.test.' );
// What a sentence is, for an Administrator's own text and for a translation: the rule reads English
// punctuation, and the editor's placeholder note and the guide say so.
ck( 'only a full stop followed by a space ends a sentence: "!" and "?" end none, so the words before them go with the sentence, and the full stop of "e.g." ends one, so what follows it goes on its own', array( WPCPM_Mail_Templates::fill( 'Thanks! Write to {mail}. Bye.', array(), $ph ), WPCPM_Mail_Templates::fill( 'See you then, e.g. {mail} or the site.', array(), $ph ) ), array( 'Bye.', 'See you then, e.g.' ) );
ck( 'a line left with nothing goes, and so does the colon line above it and the blank line it would leave at the end', WPCPM_Mail_Templates::fill( "Hello.\n\nYour page:\n{link}\n", array(), $ph ), "Hello.\n" );
ck( 'in the middle, the blank line it would leave doubled goes', WPCPM_Mail_Templates::fill( "Hello.\n\nTopic: {topic}\n\nBye.", array(), $ph ), "Hello.\n\nBye." );
ck( 'at the start, the blank line it would leave first goes', WPCPM_Mail_Templates::fill( "Topic: {topic}\n\nBye.", array(), $ph ), 'Bye.' );
ck( 'inside a paragraph, only the line goes', WPCPM_Mail_Templates::fill( "When: today\nTopic: {topic}\nWhere: online", array(), $ph ), "When: today\nWhere: online" );
ck( 'a line holding a required placeholder never goes, even when its value is empty', WPCPM_Mail_Templates::fill( "Hi {name}\nBye.", array(), $ph ), "Hi \nBye." );
ck( 'and its sentence stays, while an optional one beside it goes', WPCPM_Mail_Templates::fill( 'Hi {name}. Topic: {topic}.', array(), $ph ), 'Hi .' );
ck( 'a subject is filled and never shortened', WPCPM_Mail_Templates::fill( '[{topic}] Your account is ready', array(), $ph, "\n", false ), '[] Your account is ready' );
ck( 'and kept to one line: each line break in a value is one space there, since a subject is a header', WPCPM_Mail_Templates::fill( 'A {topic}', array( 'topic' => "x\r\ny" ), $ph, "\n", false ), 'A x y' );
ck( 'the lines are joined with the line ending asked for, a value\'s own left as they are, and the last line break kept', WPCPM_Mail_Templates::fill( "A {topic}\r\nB\n", array( 'topic' => "x\ny" ), $ph, "\r\n" ), "A x\ny\r\nB\r\n" );
ck( 'an indented sentence keeps its indent when the one before it goes', WPCPM_Mail_Templates::fill( "  About {topic}. Then this.", array(), $ph ), '  Then this.' );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

The catalog's suite counts the plugin's emails and checks that every email it names is one the source sends; the settled invitation is set from a choice between two ids, which the scan of send calls cannot read, so the suite reads the registry's ids from its definition files too. Two changes in `bin/test-mail-catalog.php`:

In `bin/test-mail-catalog.php`, replace:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the 42 contexts the plugin sends today', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 42 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

with:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the plugin\'s 43 emails: the 42 contexts it sent before 1.122.19, and the institution invitation for an agreement on file', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 43 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

In `bin/test-mail-catalog.php`, replace:

```php
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 42 of them (it is not reading nothing)', count( $contexts ), 42 );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
$unnamed = array_values( array_diff( array_values( $filters ), array_keys( $emails ) ) );
ck( 'every WordPress and Two Factor filter names an email the catalog has', $unnamed, array() );
```

with:

```php
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 42 of them (it is not reading nothing)', count( $contexts ), 42 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
// and one of them, the institution invitation for an agreement on file, is set from a choice
// between two ids rather than passed as a literal the scan above can read.
$templates = array();
foreach ( glob( __DIR__ . '/../includes/mail/templates/*.php' ) as $file ) {
	preg_match_all( "/^\t'([a-z0-9-]+)'\s*=>\s*array\(/m", (string) file_get_contents( $file ), $t );
	$templates = array_merge( $templates, $t[1] );
}
ck( 'every template the registry defines is in the catalog', array_values( array_diff( $templates, array_keys( $emails ) ) ), array() );
$plugin_ids = array_keys( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) );
$sent_ids   = array_unique( array_merge( array_keys( $contexts ), $templates ) );
sort( $plugin_ids );
sort( $sent_ids );
ck( 'and every email of the plugin\'s the catalog names is one the source sends or a template the registry holds', $plugin_ids, $sent_ids );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
$unnamed = array_values( array_diff( array_values( $filters ), array_keys( $emails ) ) );
ck( 'every WordPress and Two Factor filter names an email the catalog has', $unnamed, array() );
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php bin/test-mail-templates.php`
Expected: a fatal error, `Failed opening required '.../includes/mail/class-wpcpm-mail-templates.php'`.

Run: `php bin/test-mail-catalog.php`
Expected: `1 FAILURE(S) (19 checks)`: the count reads 42 where 43 is wanted. The two checks on the registry's ids pass on no definition file yet.

- [ ] **Step 3: Write the registry**

Create `includes/mail/class-wpcpm-mail-templates.php`:

```php
<?php
/**
 * The plugin's emails as text: the template registry.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What each of the plugin's emails says, with `{placeholders}` where its values go.
 *
 * An email's name, its area and its audience are the catalog's (`WPCPM_Mail_Catalog`), which stays
 * the one list of them. The registry adds to a catalog id what makes the email editable: its subject
 * and body as shipped, the placeholders they use, and one sentence saying what sends it. Each area's
 * emails are defined in a file of their own under includes/mail/templates/, which returns them, and
 * an area joins `AREAS` when its emails move here.
 *
 * The shipped text is the wording the code wrote before the move, word for word. It is built with
 * `__()` each time it is asked for, so it is in the language the email is being built in, the
 * recipient's, as it was before.
 *
 * Filling a text (`fill()`):
 *
 * - every placeholder is replaced by its value as plain text, in one pass, so a value is never read
 *   as a placeholder itself, and a placeholder the email does not have is left as it was written;
 * - in a body, a sentence whose placeholders are all optional and all empty is left out, and so is a
 *   line left with nothing on it, as the code left out a line with nothing to say before the move;
 *   a line ending in a colon directly above a line left out goes with it, since it introduced that
 *   line; and a blank line that the lines left out would leave doubled, or at the start or the end,
 *   goes too;
 * - a sentence holding a required placeholder is never left out, and neither is its line.
 *
 * A sentence ends at a full stop followed by a space, so "Questions? Write to {program_contact}." is
 * one sentence: a question or an exclamation mark ends none, and the full stop of "e.g." followed by
 * a space ends one. A subject is filled and never shortened, and is kept to one line: each line
 * break in a value is one space there, since a subject is a header.
 *
 * A placeholder may go in the body alone (`body_only`), as the set-password link does: a subject is
 * never a link a person can follow, and it is the one part of an email the mail log keeps.
 */
final class WPCPM_Mail_Templates {

	/**
	 * The areas whose emails the registry holds, each with its file under includes/mail/templates/.
	 *
	 * @var array<string,string>
	 */
	const AREAS = array(
		WPCPM_Mail_Catalog::MODULE_INVITATIONS => 'invitations',
	);

	/** What a placeholder looks like: a word in braces. */
	const PLACEHOLDER = '/\{([A-Za-z0-9_-]+)\}/';

	/**
	 * Every template, by id, in the order of `AREAS` and, within an area, of its file: the catalog's
	 * name, area and audience, then the registry's own fields. An id the catalog does not hold is left
	 * out, since it has no name to show.
	 *
	 * Built each time it is asked for, so the shipped text is in the language of the moment.
	 *
	 * @return array<string,array{id:string,label:string,module:string,audience:string,trigger:string,subject:string,body:string,eol:string,placeholders:array}>
	 */
	public static function all() {
		$out = array();

		foreach ( self::AREAS as $file ) {
			$defined = require __DIR__ . '/templates/' . $file . '.php';

			foreach ( (array) $defined as $id => $entry ) {
				$named = WPCPM_Mail_Catalog::get( $id );

				if ( null === $named || ! is_array( $entry ) ) {
					continue;
				}

				$out[ $id ] = array(
					'id'           => (string) $id,
					'label'        => $named['label'],
					'module'       => $named['module'],
					'audience'     => $named['audience'],
					'trigger'      => isset( $entry['trigger'] ) ? (string) $entry['trigger'] : '',
					'subject'      => isset( $entry['subject'] ) ? (string) $entry['subject'] : '',
					'body'         => isset( $entry['body'] ) ? (string) $entry['body'] : '',
					'eol'          => isset( $entry['eol'] ) && "\r\n" === $entry['eol'] ? "\r\n" : "\n",
					'placeholders' => isset( $entry['placeholders'] ) ? (array) $entry['placeholders'] : array(),
				);
			}
		}

		return $out;
	}

	/**
	 * One template.
	 *
	 * @param string $id The email's id in the catalog.
	 * @return array|null The entry `all()` holds for it, or null when the registry has none.
	 */
	public static function get( $id ) {
		$all = self::all();

		return isset( $all[ (string) $id ] ) ? $all[ (string) $id ] : null;
	}

	/**
	 * The placeholders a text uses, each once, in the order they first appear.
	 *
	 * @param string $text A subject or a body.
	 * @return string[] Their names, without the braces.
	 */
	public static function placeholders_in( $text ) {
		preg_match_all( self::PLACEHOLDER, (string) $text, $found );

		return array_values( array_unique( $found[1] ) );
	}

	/**
	 * The placeholders an email cannot be sent without.
	 *
	 * @param array $placeholders A template's placeholders, name to what `all()` holds for each.
	 * @return string[] Their names.
	 */
	public static function required( array $placeholders ) {
		$out = array();

		foreach ( $placeholders as $name => $about ) {
			if ( ! empty( $about['required'] ) ) {
				$out[] = (string) $name;
			}
		}

		return $out;
	}

	/**
	 * The placeholders that go in an email's body and never in its subject: a subject is a header,
	 * never a link a person can follow, and the one part of an email the mail log keeps.
	 *
	 * @param array $placeholders A template's placeholders, name to what `all()` holds for each.
	 * @return string[] Their names.
	 */
	public static function body_only( array $placeholders ) {
		$out = array();

		foreach ( $placeholders as $name => $about ) {
			if ( ! empty( $about['body_only'] ) ) {
				$out[] = (string) $name;
			}
		}

		return $out;
	}

	/**
	 * A text with its values in place, by the rules the class docblock gives.
	 *
	 * @param string $text         A subject or a body, with `{placeholders}`.
	 * @param array  $values       Each placeholder's value, by name; one not given is empty.
	 * @param array  $placeholders The email's placeholders, name to what `all()` holds for each.
	 * @param string $eol          The line ending the result is written with.
	 * @param bool   $shorten      Whether empty optional sentences, and what they leave, are left out:
	 *                             true for a body, false for a subject, which is kept to one line,
	 *                             each line break in a value one space.
	 * @return string
	 */
	public static function fill( $text, array $values, array $placeholders, $eol = "\n", $shorten = true ) {
		$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );

		if ( $shorten ) {
			$text = self::shorten( $text, $values, $placeholders );
		}

		$pairs = array();

		foreach ( self::placeholders_in( $text ) as $name ) {
			if ( isset( $placeholders[ $name ] ) ) {
				$value = isset( $values[ $name ] ) ? (string) $values[ $name ] : '';

				$pairs[ '{' . $name . '}' ] = $shorten ? $value : (string) preg_replace( '/\r\n|\r|\n/', ' ', $value );
			}
		}

		return strtr( str_replace( "\n", $eol, $text ), $pairs );
	}

	/**
	 * A body with what its empty optional placeholders leave out taken out: the sentences, the lines
	 * left with nothing, the colon lines above them, and the blank lines they leave doubled or at an
	 * end. The placeholders are still in the text; `fill()` puts the values in after.
	 *
	 * @param string $text         The body, its line endings "\n".
	 * @param array  $values       Each placeholder's value, by name.
	 * @param array  $placeholders The email's placeholders.
	 * @return string
	 */
	private static function shorten( $text, array $values, array $placeholders ) {
		$ends  = "\n" === substr( $text, -1 );
		$lines = explode( "\n", $ends ? substr( $text, 0, -1 ) : $text );
		$kept  = array();

		// Whether a placeholder may go: one the email has, optional, and empty.
		$may_go = static function ( $name ) use ( $values, $placeholders ) {
			return isset( $placeholders[ $name ] ) && empty( $placeholders[ $name ]['required'] ) && ( ! isset( $values[ $name ] ) || '' === (string) $values[ $name ] );
		};

		foreach ( $lines as $n => $line ) {
			$kept[ $n ] = $line;
			$names      = self::placeholders_in( $line );

			if ( array() === $names || array() === array_filter( $names, $may_go ) ) {
				continue;
			}

			preg_match( '/^[ \t]*/', $line, $indent );

			$parts = preg_split( '/(?<=\.)( +)/', substr( $line, strlen( $indent[0] ) ), -1, PREG_SPLIT_DELIM_CAPTURE );
			$left  = '';
			$space = '';

			foreach ( $parts as $i => $part ) {
				if ( 1 === $i % 2 ) {
					$space = $part;
					continue;
				}

				$in = self::placeholders_in( $part );

				if ( array() !== $in && count( array_filter( $in, $may_go ) ) === count( $in ) ) {
					continue;
				}

				$left .= ( '' === $left ? '' : $space ) . $part;
			}

			$kept[ $n ] = '' === trim( $left ) ? null : $indent[0] . $left;
		}

		// A line ending in a colon, directly above a line left out, introduced it, and goes with it.
		foreach ( array_keys( $kept ) as $n ) {
			if ( null === $kept[ $n ] && $n > 0 && null !== $kept[ $n - 1 ] && ':' === substr( rtrim( $kept[ $n - 1 ] ), -1 ) ) {
				$kept[ $n - 1 ] = null;
			}
		}

		// Each run of lines left out: a blank line before it goes when a blank line or the end comes
		// after it, and a blank line after it goes when the run opened the text.
		$count = count( $kept );

		for ( $n = 0; $n < $count; $n++ ) {
			if ( null !== $kept[ $n ] || ( $n > 0 && null === $kept[ $n - 1 ] ) ) {
				continue;
			}

			$after = $n;

			while ( $after < $count && null === $kept[ $after ] ) {
				++$after;
			}

			$before = $n - 1;

			while ( $before >= 0 && null === $kept[ $before ] ) {
				--$before;
			}

			if ( $before >= 0 && '' === trim( $kept[ $before ] ) && ( $after >= $count || '' === trim( $kept[ $after ] ) ) ) {
				$kept[ $before ] = null;
			} elseif ( $before < 0 && $after < $count && '' === trim( $kept[ $after ] ) ) {
				$kept[ $after ] = null;
			}
		}

		$out = array_filter(
			$kept,
			static function ( $line ) {
				return null !== $line;
			}
		);

		return implode( "\n", $out ) . ( $ends ? "\n" : '' );
	}

	/**
	 * An email's subject and body, filled with its values. No footer: the signature and footer is
	 * added once, where the email leaves, never by a builder.
	 *
	 * @param string $id     The email's id.
	 * @param array  $values Each placeholder's value, by name, worked out by whatever sends it.
	 * @return array{subject:string,body:string}|null Null when the registry has no such email.
	 */
	public static function render( $id, array $values ) {
		$entry = self::get( $id );

		if ( null === $entry ) {
			return null;
		}

		return array(
			'subject' => self::fill( $entry['subject'], $values, $entry['placeholders'], $entry['eol'], false ),
			'body'    => self::fill( $entry['body'], $values, $entry['placeholders'], $entry['eol'] ),
		);
	}

	/**
	 * The values an email is previewed and tested with: each placeholder's example, worked out now
	 * where a definition gives a function for it (the site's name, its login page, a dashboard).
	 *
	 * @param string $id The email's id.
	 * @return array<string,string> Name to example; empty when the registry has no such email.
	 */
	public static function example_values( $id ) {
		$entry = self::get( $id );

		return null === $entry ? array() : self::examples_of( $entry['placeholders'] );
	}

	/**
	 * Each placeholder's example: a string as it is, a closure called now.
	 *
	 * @param array $placeholders Name to what `all()` holds for each.
	 * @return array<string,string>
	 */
	public static function examples_of( array $placeholders ) {
		$out = array();

		foreach ( $placeholders as $name => $about ) {
			$example      = isset( $about['example'] ) ? $about['example'] : '';
			$out[ $name ] = $example instanceof Closure ? (string) $example() : (string) $example;
		}

		return $out;
	}

	/**
	 * The address of the dashboard page a kind of recipient has, while its page is published.
	 *
	 * @param string $type One of the catalog's recipient types (`WPCPM_Mail_Catalog::TYPE_*`).
	 * @return string The page's address, or '' for a type with no dashboard or a page not published.
	 */
	public static function page_of_type( $type ) {
		$pages = array(
			WPCPM_Mail_Catalog::TYPE_STUDENT     => 'WPCPM_Students_Dashboard',
			WPCPM_Mail_Catalog::TYPE_MENTOR      => 'WPCPM_Mentors_Dashboard',
			WPCPM_Mail_Catalog::TYPE_INSTITUTION => 'WPCPM_Institutions_Dashboard',
			WPCPM_Mail_Catalog::TYPE_SPONSOR     => 'WPCPM_Sponsors_Dashboard',
			WPCPM_Mail_Catalog::TYPE_ADMIN       => 'WPCPM_Administrators_Dashboard',
		);

		if ( ! isset( $pages[ $type ] ) || ! class_exists( $pages[ $type ] ) || ! method_exists( $pages[ $type ], 'page_url' ) ) {
			return '';
		}

		return (string) call_user_func( array( $pages[ $type ], 'page_url' ) );
	}
}
```

- [ ] **Step 4: Write the Invitations' shipped text**

Every sentence is the translatable string `welcome_email()` uses, unchanged, so a translation, should one ship, carries over. The bodies are joined with "\n" here and written in "\r\n" by the registry (`eol`), as WordPress writes its own email. The left-out rules read English punctuation, so each string they act on carries a translators comment saying what a translation must keep (ruling PR-5): the colon at the end of the five labels above a dashboard's address, and the full stop that ends the sentence on the link and the username's. The example link is built with `add_query_arg()` over the login page (ruling PR-8), so a login page whose address already carries a query gets no second "?".

Create `includes/mail/templates/invitations.php`:

```php
<?php
/**
 * The Invitations area's emails, as the template registry ships them.
 *
 * Read by `WPCPM_Mail_Templates::all()`, which adds each email's name, area and audience from the
 * catalog. Every sentence is the one `WPCPM_Mail::welcome_email()` wrote before 1.122.19, under the
 * same translatable string, and the bodies are joined by the registry with the line ending WordPress
 * gives its own email, "\r\n".
 *
 * Each invitation wraps WordPress's own lines: `{set_password_link}` is what WordPress wrote, its
 * sentence and the link that sets the password, kept whole so a change to how WordPress words it
 * arrives on its own; and `{username}` is the username when those lines do not show it already
 * (WordPress 7.1 and later do not), else empty, which leaves its sentence out.
 *
 * @package WPCreditsProgramManager
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// The placeholders every invitation has. An example is a value, or a function that works one out from
// the site when a preview or a test asks for it, so building an invitation reads nothing it does not use.
$wpcpm_placeholders = static function ( $audience ) {
	return array(
		'site_name'         => array(
			'means'    => __( 'The site\'s name.', 'wpcredits-program-manager' ),
			'example'  => static function () {
				return WPCPM_Mail::site_name();
			},
			'required' => false,
		),
		'set_password_link' => array(
			'means'     => __( 'WordPress\'s own lines with the link that sets the password. Every invitation needs it, in its body.', 'wpcredits-program-manager' ),
			'example'   => static function () {
				return __( 'To set your password, visit the following address:', 'wpcredits-program-manager' ) . "\r\n\r\n" . add_query_arg(
					array(
						'login'  => 'ada.example',
						'key'    => 'EXAMPLE-KEY-NOT-A-REAL-LINK',
						'action' => 'rp',
					),
					wp_login_url()
				);
			},
			'required'  => true,
			// In the body alone: a subject is never a link a person can follow, and it is the one part
			// of an email the mail log keeps, for 30 days, so the live key may never reach it.
			'body_only' => true,
		),
		'username'          => array(
			'means'    => __( 'The person\'s username, when WordPress\'s own lines do not show it. Empty leaves its sentence out.', 'wpcredits-program-manager' ),
			'example'  => 'ada.example',
			'required' => false,
		),
		'login_url'         => array(
			'means'    => __( 'The login page.', 'wpcredits-program-manager' ),
			'example'  => static function () {
				return wp_login_url();
			},
			'required' => false,
		),
		'dashboard_link'    => array(
			'means'    => __( 'The person\'s dashboard, while its page is published. Empty leaves its lines out.', 'wpcredits-program-manager' ),
			'example'  => static function () use ( $audience ) {
				return WPCPM_Mail_Templates::page_of_type( $audience );
			},
			'required' => false,
		),
	);
};

/**
 * One invitation's body: its opening, its next step, WordPress's lines, then the lines every
 * invitation shares, and the line naming the person's dashboard, under its label.
 *
 * @param string $opening What the account is.
 * @param string $next    What to do first.
 * @param string $label   The line above the dashboard's address.
 * @return string
 */
$wpcpm_body = static function ( $opening, $next, $label ) {
	return implode(
		"\n",
		array(
			$opening,
			'',
			$next,
			'',
			'{set_password_link}',
			'',
			/* translators: A sentence of its own. End it with a full stop: the site reads a full stop followed by a space as the end of a sentence, and leaves the sentence after it, the username's, out on its own when there is no username to give. */
			__( 'The link above sets your password and stops working after a day.', 'wpcredits-program-manager' ) . ' ' . sprintf(
				/* translators: %s: the WordPress username. A sentence of its own, ending in a full stop, after the one on the link: the site leaves it out when the username is not needed, and keeps the sentence before it. */
				__( 'Your username is %s.', 'wpcredits-program-manager' ),
				'{username}'
			),
			'',
			__( 'After that, sign in at the login page. If the password link has expired, open the login page, choose "Lost your password?" and enter your username or email address to get a fresh one.', 'wpcredits-program-manager' ),
			'{login_url}',
			'',
			__( 'Each new invitation or password email replaces the link in the ones before it, so if you have more than one, use the newest.', 'wpcredits-program-manager' ),
			'',
			$label,
			'{dashboard_link}',
		)
	) . "\n";
};

return array(
	'invite-student'             => array(
		'trigger'      => __( 'When a student is sent the invitation to their account: from the Students screen, or as the sync creates the account with invitation emails on.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Welcome to the WordPress Credits Program', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'You have been enrolled on the WordPress Credits Program, and this is your account on the program site. Your program details, your mentor and your report form all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below, then check your details and book your first call with your mentor.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your Student Report Card:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_STUDENT ),
	),
	'invite-mentor'              => array(
		'trigger'      => __( 'When a mentor is sent the invitation to their account: from the Mentors screen, or as the sync creates the account with invitation emails on.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your mentor account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'You have been set up as a mentor on the WordPress Credits Program, and this is your account on the program site. Your students, their programs and the calls they book with you all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below, then publish the hours you are free so your students can book calls with you.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your students:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_MENTOR ),
	),
	'invite-institution'         => array(
		'trigger'      => __( 'When an institution\'s member is sent the invitation to its account before the institution\'s Collaboration Agreement is on file.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your institution account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'Your institution has been set up on the WordPress Credits Program, and this is its account on the program site. Your Collaboration Agreement with the program, and once it is in place your students and their progress, all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below. The first and only step after that is the Collaboration Agreement: generate the program\'s or upload your own, sign it, and upload the signed copy. A program manager then accepts it and the rest of the site opens.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Where the agreement is uploaded:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_INSTITUTION ),
	),
	'invite-institution-settled' => array(
		'trigger'      => __( 'When an institution\'s member is sent the invitation to its account once the institution\'s Collaboration Agreement is on file.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your institution account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'Your institution has been set up on the WordPress Credits Program, and this is its account on the program site. Your Collaboration Agreement with the program, your students and their progress all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below. Your Collaboration Agreement is on file, so your account is open.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your Institution Dashboard:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_INSTITUTION ),
	),
	'invite-sponsor'             => array(
		'trigger'      => __( 'When a sponsor\'s member is sent the invitation to its account, from the Sponsors screen.', 'wpcredits-program-manager' ),
		'subject'      => sprintf(
			/* translators: %s: site name. */
			__( '[%s] Your sponsor account is ready', 'wpcredits-program-manager' ),
			'{site_name}'
		),
		'body'         => $wpcpm_body(
			__( 'Your company has been set up as a sponsor of the WordPress Credits Program, and this is your account on the program site. Your offer, the mentors you sponsor and your contact at the program all live there.', 'wpcredits-program-manager' ),
			__( 'Set your password using the link below, then check your company profile and tell us how you would like to support the program.', 'wpcredits-program-manager' ),
			/* translators: The line above the address of the person's dashboard. Keep the colon at the end; the site leaves this line out with the address under it. */
			__( 'Your Sponsor Dashboard:', 'wpcredits-program-manager' )
		),
		'eol'          => "\r\n",
		'placeholders' => $wpcpm_placeholders( WPCPM_Mail_Catalog::TYPE_SPONSOR ),
	),
);
```

- [ ] **Step 5: The catalog names the second institution invitation, and the plugin loads the registry**

In `includes/mail/class-wpcpm-mail-catalog.php`, replace:

```php
			'invite-student'                     => array( __( 'Invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_STUDENT ),
			'invite-mentor'                      => array( __( 'Invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_MENTOR ),
			'invite-institution'                 => array( __( 'Invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_INSTITUTION ),
			'invite-sponsor'                     => array( __( 'Invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_SPONSOR ),
			'test-student'                       => array( __( 'Sample invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-mentor'                        => array( __( 'Sample invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
```

with:

```php
			'invite-student'                     => array( __( 'Invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_STUDENT ),
			'invite-mentor'                      => array( __( 'Invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_MENTOR ),
			'invite-institution'                 => array( __( 'Invitation: institution', 'wpcredits-program-manager' ), $i, self::TYPE_INSTITUTION ),
			'invite-institution-settled'         => array( __( 'Invitation: institution, agreement on file', 'wpcredits-program-manager' ), $i, self::TYPE_INSTITUTION ),
			'invite-sponsor'                     => array( __( 'Invitation: sponsor', 'wpcredits-program-manager' ), $i, self::TYPE_SPONSOR ),
			'test-student'                       => array( __( 'Sample invitation: student', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
			'test-mentor'                        => array( __( 'Sample invitation: mentor', 'wpcredits-program-manager' ), $i, self::TYPE_ADMIN, true ),
```

In `wpcredits-program-manager.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-contribution-teams.php';
```

uninstall.php requires what the loader requires (bin/test-roles.php holds the two lists together):

In `uninstall.php`, replace:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-ics.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-mail.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-log.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-capture.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-contribution-teams.php';
```

with:

```php
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-ics.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-mail.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-templates.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-log.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/mail/class-wpcpm-mail-capture.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-wpcpm-contribution-teams.php';
```

- [ ] **Step 6: Run the tests to verify they pass**

Run, one per command: `php bin/test-mail-templates.php`, `php bin/test-mail-catalog.php`, `php bin/test-roles.php`
Expected: `ALL PASS (26 checks)`; `ALL PASS (19 checks)`; `ALL PASS`.

- [ ] **Step 7: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 8: Commit**

```bash
git add includes/mail/class-wpcpm-mail-templates.php includes/mail/templates/invitations.php includes/mail/class-wpcpm-mail-catalog.php wpcredits-program-manager.php uninstall.php bin/test-mail-templates.php bin/test-mail-catalog.php
git commit -m "Emails E2: the template registry and the Invitations' shipped text"
```

---

### Task 3: The invitations, built from the registry, word for word

`welcome_email()` keeps choosing which invitation an account is sent and working out the values; the registry writes it. The recording of Task 1 holds every byte. Two things change on purpose: the institution invitation for an agreement on file is named in the log by its own id, and a "%" in the subject, which WordPress reads as a format once more before sending, is written twice, so a site name such as "100% Credits" no longer fails the send with a `ValueError`. That is a defect of 1.122.18, and an editable subject would have put it in reach of every Administrator.

**Files:**
- Modify: `includes/class-wpcpm-mail.php` (`welcome_email()`, from line 1133)
- Modify: `bin/test-invitation-text.php` (it loads the registry)
- Modify: `bin/test-mail.php` (it loads the registry; the Student Report Card's label is read from the definitions; two new checks)
- Modify: `bin/test-institutions-accounts.php`, `bin/test-sponsors-accounts.php` (each loads the registry, which the invitation their stand-in for `wp_new_user_notification()` filters is built from)

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::render( string $id, array $values ): ?array` (Task 2).
- Produces: `WPCPM_Mail::welcome_email()` with its signature unchanged; the context it leaves for the capture is the template's id (`invite-student`, `invite-mentor`, `invite-institution`, `invite-institution-settled`, `invite-sponsor`); the subject it returns has each "%" doubled.

- [ ] **Step 1: The suites load the registry, and two checks pin the new behavior**

Every suite is plain PHP that prints an `ok` or `FAIL` line for each check through its `ck()` helper, ends on a count, and exits non-zero on a failure. The helper and the two closing lines, as a new suite writes them (an existing suite keeps its own, whose arguments come in the same order: the label, what was got, what was wanted):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

`bin/test-invitation-text.php` is the word-for-word proof: it loads the registry and nothing else changes in it.

In `bin/test-invitation-text.php`, replace:

```php

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
```

with:

```php

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
// The template registry the invitations are built from since 1.122.19, and the catalog that names them.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
```

In `bin/test-mail.php`: the registry is loaded; the check that the welcome names the Student Report Card reads the string where it now lives, the definitions; and two checks are added after the one for an institution whose agreement is on file.

In `bin/test-mail.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
// The Settings screen the sample handler goes back to, and whose outcome it queues through the
// screen's own door: the real one, since a stand-in's pairing of the two channels would only agree
// with itself.
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-ics.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
// The template registry the invitations are built from since 1.122.19, and the catalog that names them.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
// The Settings screen the sample handler goes back to, and whose outcome it queues through the
// screen's own door: the real one, since a stand-in's pairing of the two channels would only agree
// with itself.
```

In `bin/test-mail.php`, replace:

```php
    ),
    array( true, false, false, '[WordPress Education Dashboard] Your institution account is ready' ) );

// The dashboard line. There is no institution dashboard until Phase 1, and until there is one
// there must be no line pointing at it. The stand-in below is declared here rather than with
// the other stubs so the invitation is first built with no such page at all - the way it is in
```

with:

```php
    ),
    array( true, false, false, '[WordPress Education Dashboard] Your institution account is ready' ) );

// Two templates since 1.122.19, because the two say different things, and each is named in the log by
// its own id.
WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][70], 'Site' );
wp_mail( 'contact@oscar.example', $on_file['subject'], $on_file['message'] );
ck( 'and that invitation is the registry\'s second institution template, under its own id in the mail log', end( $GLOBALS['mail'] )['context'], 'invite-institution-settled' );

// WordPress runs the subject through sprintf() with the site's name once more before sending it, so a
// "%" in the name came back as a format: "100% Credits" failed the send with a ValueError.
$percent = WPCPM_Mail::welcome_email( $core, $GLOBALS['users'][30], '100% Credits' );
$as_sent = null;
try {
	$as_sent = sprintf( $percent['subject'], '100% Credits' );
} catch ( Throwable $e ) {
	$as_sent = get_class( $e );
}
ck( 'a "%" in the site\'s name survives WordPress\'s own sprintf() of the subject', $as_sent, '[100% Credits] Welcome to the WordPress Credits Program' );

// The dashboard line. There is no institution dashboard until Phase 1, and until there is one
// there must be no line pointing at it. The stand-in below is declared here rather than with
// the other stubs so the invitation is first built with no such page at all - the way it is in
```

In `bin/test-mail.php`, replace:

```php
ck( 'no constant string in the PHP under includes/ says a bare "report card": it is the Student Report Card or the Mentor Report Card', $bare, array() );
ck( 'the student\'s welcome and the notice that a group session moved name the Student Report Card',
    array(
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php' ), 'you can leave the session from your Student Report Card.' ),
    ),
```

with:

```php
ck( 'no constant string in the PHP under includes/ says a bare "report card": it is the Student Report Card or the Mentor Report Card', $bare, array() );
ck( 'the student\'s welcome and the notice that a group session moved name the Student Report Card',
    array(
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/mail/templates/invitations.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php' ), "__( 'Your Student Report Card:'" ),
        substr_count( (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-mentor-calls.php' ), 'you can leave the session from your Student Report Card.' ),
    ),
```

The two accounts suites filter WordPress's new-account email through the real `welcome_email()`, so each loads the registry too:

In `bin/test-institutions-accounts.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
// The template registry the invitation is built from since 1.122.19, and the catalog that names it.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-airtable.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
```

In `bin/test-sponsors-accounts.php`, replace:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
```

with:

```php
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-return.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
// The template registry the invitation is built from since 1.122.19, and the catalog that names it.
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-admin.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/modules/class-wpcpm-module.php';
```

- [ ] **Step 2: Run them to verify the new checks fail, and the rest still pass**

Run: `php bin/test-mail.php`
Expected: `2 FAILURE(S)`: "and that invitation is the registry's second institution template, under its own id in the mail log" (got `invite-institution`) and "a "%" in the site's name survives WordPress's own sprintf() of the subject" (got `ValueError`).

Run, one per command: `php bin/test-invitation-text.php`, `php bin/test-institutions-accounts.php`, `php bin/test-sponsors-accounts.php`
Expected: `ALL PASS (39 checks)`; `ALL PASS (153 checks)`; `ALL PASS (161 checks)`: today's code ignores the registry it now finds loaded.

- [ ] **Step 3: Build the invitations from the registry**

In `includes/class-wpcpm-mail.php`, replace the method `welcome_email()`, its docblock included, with:

```php
	/**
	 * Give the login invitation some context.
	 *
	 * WordPress sends a username, a reset link and a login URL. Arriving cold that reads as
	 * a phishing attempt: a site the reader may not recognize telling them to set a password,
	 * with no mention of the program, who they are to it, or what to do next. A mentor, a
	 * student and an institution's contact are also arriving for entirely different reasons,
	 * and get different copy.
	 *
	 * The reset link's one-day expiry is left exactly as WordPress sets it. Extending it here
	 * would lengthen the window on every password reset on the site, not just these - so
	 * instead the mail says what to do when the link has gone stale, which is the case that
	 * actually bites when ninety invitations go out and some are opened on Thursday.
	 *
	 * The wording is the template registry's (`WPCPM_Mail_Templates`, the Invitations area), which
	 * an Administrator can edit in WPCredits Program > Tools > Emails; this works out which of the
	 * five invitations the account is sent and the values its placeholders take.
	 *
	 * @param array   $email    `to`, `subject`, `message`, `headers`.
	 * @param WP_User $user     The new user.
	 * @param string  $blogname Site name.
	 * @return array
	 */
	public static function welcome_email( $email, $user, $blogname ) {
		if ( ! $user instanceof WP_User ) {
			return $email;
		}

		$is_mentor      = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_MENTOR );
		$is_student     = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_STUDENT );
		$is_institution = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_INSTITUTION );
		$is_sponsor     = WPCPM_Roles::user_has_role( $user, WPCPM_Roles::ROLE_SPONSOR );

		// Not one of ours. Somebody else's plugin, or a hand-made account.
		if ( ! $is_mentor && ! $is_student && ! $is_institution && ! $is_sponsor ) {
			return $email;
		}

		// One audience per message, in this order, whatever the account is stamped for: the
		// template is what the person reads, while `stamp_invited()` stamps an account for each of
		// the four program roles it holds, because the stamps are what the lists read. A mentor can
		// also be an institution's member (membership is added to an existing account, never the
		// other way round), and that account was a mentor's before it was anything else. The same
		// holds for a sponsor's representative who is also a mentor - sponsorship is likewise added
		// to an existing account, never the reverse - so mentor still wins there too.
		if ( $is_mentor ) {
			$kind = 'mentor';
		} elseif ( $is_institution ) {
			$kind = 'institution';
		} elseif ( $is_sponsor ) {
			$kind = 'sponsor';
		} else {
			$kind = 'student';
		}

		$id = 'invite-' . $kind;

		if ( 'mentor' === $kind ) {
			$page = WPCPM_Mentors_Dashboard::page_url();
		} elseif ( 'institution' === $kind ) {
			// Whether the institution's Collaboration Agreement is already on file, asked of the
			// agreement module at send time, because the two next steps are different in kind: a
			// new institution has to produce and sign the agreement before anything else opens,
			// while one whose agreement is on file walks straight in. So the two are two templates.
			// Array callables rather than static calls, because `bin/check-references.php` would
			// flag a call to a method of a class a site may not have loaded.
			if ( class_exists( 'WPCPM_Institution_Agreement' ) && class_exists( 'WPCPM_Institution_Members' ) ) {
				$record = (string) call_user_func( array( 'WPCPM_Institution_Members', 'institution_of' ), $user );

				if ( '' !== $record && (bool) call_user_func( array( 'WPCPM_Institution_Agreement', 'is_settled' ), $record ) ) {
					$id = 'invite-institution-settled';
				}
			}

			// No line pointing at the dashboard while there is no address to print.
			$page = method_exists( 'WPCPM_Institutions_Dashboard', 'page_url' )
				? (string) call_user_func( array( 'WPCPM_Institutions_Dashboard', 'page_url' ) )
				: '';
		} elseif ( 'sponsor' === $kind ) {
			// An array callable so `bin/check-references.php` never sees a call to a method that is
			// not declared.
			$page = class_exists( 'WPCPM_Sponsors_Dashboard' ) && method_exists( 'WPCPM_Sponsors_Dashboard', 'page_url' )
				? (string) call_user_func( array( 'WPCPM_Sponsors_Dashboard', 'page_url' ) )
				: '';
		} else {
			$page = WPCPM_Students_Dashboard::page_url();
		}

		// Core's own body, kept whole in the middle rather than rebuilt, so a change to how
		// WordPress words or forms the reset link arrives here on its own: it is the template's
		// `{set_password_link}`. What core prints around the link has changed once already: until
		// WordPress 7.1 the body opened with the username and closed with the plain login page on a
		// line of its own, and 7.1 prints the keyed link alone, which left a sentence here naming
		// "the two addresses above" pointing at one (a mentor's invitation, 14 September 2026;
		// 1.106.1). So the facts a person needs are stated from what the body carries: the username
		// when core left it out, and the login page always, labeled, with core's own bare copy of
		// it taken off the end first so no address appears twice.
		$body  = rtrim( (string) $email['message'] );
		$login = wp_login_url();
		$tail  = substr( $body, -strlen( $login ) );

		if ( $tail === $login ) {
			$body = rtrim( substr( $body, 0, -strlen( $login ) ) );
		}

		$mail = WPCPM_Mail_Templates::render(
			$id,
			array(
				'site_name'         => wp_specialchars_decode( (string) $blogname, ENT_QUOTES ),
				'set_password_link' => $body,
				// The site's language is English, and so is the line core prints, "Username: ...".
				'username'          => preg_match( '/^Username: /m', $body ) ? '' : $user->user_login,
				'login_url'         => $login,
				'dashboard_link'    => $page,
			)
		);

		if ( null === $mail ) {
			return $email;
		}

		// WordPress passes the subject through `sprintf()` with the site's name once more before it
		// sends it, so each "%" is written twice to come out once: a "%" in a site's name, or in a
		// subject an Administrator wrote, would otherwise be read as a format and fail the send.
		$email['subject'] = str_replace( '%', '%%', $mail['subject'] );
		$email['message'] = $mail['body'];

		// WordPress is about to call `wp_mail()` itself, so this is the only moment at which
		// the log can be told that what follows is an invitation and whose it is.
		self::$context = $id;

		return $email;
	}
```

- [ ] **Step 4: Run the tests to verify they pass, the recording byte for byte**

Run, one per command: `php bin/test-invitation-text.php`, `php bin/test-mail.php`, `php bin/test-institutions-accounts.php`, `php bin/test-sponsors-accounts.php`
Expected: `ALL PASS (39 checks)`: all 18 invitations, subject and body, are the recording's bytes; then `ALL PASS`, `ALL PASS (153 checks)` and `ALL PASS (161 checks)`.

- [ ] **Step 5: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add includes/class-wpcpm-mail.php bin/test-invitation-text.php bin/test-mail.php bin/test-institutions-accounts.php bin/test-sponsors-accounts.php
git commit -m "Emails E2: the invitations are built from the registry, word for word; a % in the subject no longer fails the send"
```

---

### Task 4: The Administrators' edits: what a save must pass, and where each email stands

An edit is stored when it passes every rule of ruling E2-7, with who saved it, when, and a fingerprint of the shipped text it was made from; the email goes out as edited while the edit fits, and as shipped once an update leaves it unfit. Nothing on a screen yet: Task 7 draws the editor.

**Files:**
- Modify: `includes/mail/class-wpcpm-mail-templates.php` (the constants; `render()` sends an edit; the edits' section)
- Modify: `uninstall.php` (the option goes with the mail log)
- Modify: `bin/test-mail-templates.php` (the stand-ins an edit needs; the edits' checks)
- Modify: `bin/test-uninstall.php` (the option is seeded, and gone after)

**Interfaces:**
- Consumes: `WPCPM_Typed_Text::cleaner_loses( string $raw, string $kind ): bool`, `loss_message( string $label ): string`, `box_length( string $typed ): int`, `mail_text( string $value ): string`; `get_option()`, `update_option()`.
- Produces:
  - `WPCPM_Mail_Templates::OPTION` (`wpcpm_mail_templates`: `array( 'templates' => array( id => edit ), 'footer' => ?edit )`, not autoloaded), `SUBJECT_MAX` (200), `BODY_MAX` (10000), `STATE_SHIPPED`, `STATE_EDITED`, `STATE_CHANGED`, `STATE_SET_ASIDE` (`shipped`, `edited`, `changed`, `set-aside`).
  - `text_of( string $id ): ?array{subject:string,body:string}`, the text that goes out, the edit while it fits.
  - `edit( string $id ): ?array{subject:string,body:string,by:int,at:int,shipped:string}`.
  - `misfit( array $placeholders, string $subject, string $body )` (private): `unknown` read over both boxes, `missing` over the body alone, `in_subject` the body-only placeholders the subject holds (ruling PR-1); `text_of()`, `refusal()` and `state()` pass the two boxes apart.
  - `refusal( array $placeholders, ?string $subject, string $body ): string` ('' when it may be saved; a null subject is a text with none, the footer, whose empty box is refused too, ruling PR-6). Its sentences, in order: the subject empty; the body, or the signature and footer, empty; "this email needs %s"; "%s goes in the body, never in the subject"; "%s is not a placeholder of this email"; WordPress would take words; too long.
  - `save( string $id, ?string $subject, string $body, int $user_id ): true|WP_Error` (`wpcpm_mail_refused`); `restore( string $id ): bool`.
  - `shipped_fingerprint( string $id ): string`; `state( string $id ): array{state:string,reason:string,by:int,at:int}`; `state_label( array $state ): string`. A set-aside reason is one of "Edit set aside: it uses %s, which this email no longer has.", "Edit set aside: it lacks %s, which this email needs." and "Edit set aside: its subject uses %s, which goes in the body."
  - `render()` now fills `text_of()`'s text.

- [ ] **Step 1: Write the failing checks**

Every suite is plain PHP that prints an `ok` or `FAIL` line for each check through its `ck()` helper, ends on a count, and exits non-zero on a failure. The helper and the two closing lines, as a new suite writes them (an existing suite keeps its own, whose arguments come in the same order: the label, what was got, what was wanted):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

The registry's suite gains what an edit needs: `WP_Error`, the option, the language a fingerprint is built in, the cleaners and the typed-text rules. Two changes in `bin/test-mail-templates.php`:

In `bin/test-mail-templates.php`, replace:

```php
	public function exists() { return $this->ID > 0; }
}

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's `add_query_arg( $args, $url )`, as the example set-password link is built over the login page.
function add_query_arg( $args, $url ) { foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; } return $url; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function add_action() {}
function add_filter() {}
require_once __DIR__ . '/stubs/specialchars.php';

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';

```

with:

```php
	public function exists() { return $this->ID > 0; }
}

class WP_Error {
	private $code, $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}

$GLOBALS['opts']     = array();
$GLOBALS['autoload'] = array();
$GLOBALS['locales']  = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's `add_query_arg( $args, $url )`, as the example set-password link is built over the login page.
function add_query_arg( $args, $url ) { foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; } return $url; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function current_user_can( $cap ) { return false; }
// The language the fingerprint of a shipped text is built in, recorded: the site's.
function get_locale() { return 'en_US'; }
function switch_to_locale( $locale ) { $GLOBALS['locales'][] = $locale; return true; }
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function add_action() {}
function add_filter() {}
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
require_once __DIR__ . '/../includes/class-wpcpm-mail.php';
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';

```

In `bin/test-mail-templates.php`, replace:

```php
ck( 'the lines are joined with the line ending asked for, a value\'s own left as they are, and the last line break kept', WPCPM_Mail_Templates::fill( "A {topic}\r\nB\n", array( 'topic' => "x\ny" ), $ph, "\r\n" ), "A x\ny\r\nB\r\n" );
ck( 'an indented sentence keeps its indent when the one before it goes', WPCPM_Mail_Templates::fill( "  About {topic}. Then this.", array(), $ph ), '  Then this.' );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

with:

```php
ck( 'the lines are joined with the line ending asked for, a value\'s own left as they are, and the last line break kept', WPCPM_Mail_Templates::fill( "A {topic}\r\nB\n", array( 'topic' => "x\ny" ), $ph, "\r\n" ), "A x\ny\r\nB\r\n" );
ck( 'an indented sentence keeps its indent when the one before it goes', WPCPM_Mail_Templates::fill( "  About {topic}. Then this.", array(), $ph ), '  Then this.' );

echo "\n=== Edits ===\n";

$shipped = WPCPM_Mail_Templates::get( 'invite-mentor' );
$values  = WPCPM_Mail_Templates::example_values( 'invite-mentor' );

/** What a save of the mentor invitation answers: true, or the sentence that refused it. */
function save_mentor( $subject, $body ) {
	$saved = WPCPM_Mail_Templates::save( 'invite-mentor', $subject, $body, 7 );

	return is_wp_error( $saved ) ? $saved->get_error_message() : $saved;
}

ck( 'with no edit, an email is shipped text and goes out as shipped', array( WPCPM_Mail_Templates::state( 'invite-mentor' )['state'], WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::text_of( 'invite-mentor' ) ), array( 'shipped', 'Shipped text', array( 'subject' => $shipped['subject'], 'body' => $shipped['body'] ) ) );

$good_body = "Welcome, mentor.\n\n{set_password_link}\n\nWe <3 our mentors & their students.";
$refusals  = array(
	'the subject left empty'                      => save_mentor( '  ', $good_body ),
	'the body left empty'                         => save_mentor( 'Hi', " \n " ),
	'the set-password link dropped'               => save_mentor( 'Hi', 'Welcome, mentor.' ),
	'the set-password link misspelled'            => save_mentor( 'Hi', "Welcome.\n{set_pasword_link}" ),
	'a placeholder the email does not have'       => save_mentor( 'Hi {student_nmae}', $good_body ),
	'two of them'                                 => save_mentor( 'Hi {a}', $good_body . ' {b}' ),
	'a subject WordPress would take words from'   => save_mentor( 'Get 10%cashback', $good_body ),
	'a body WordPress would take words from'      => save_mentor( 'Hi', $good_body . "\nKids <12 free, adults >18 pay" ),
	'a subject of 201 characters'                 => save_mentor( str_repeat( 'a', 201 ), $good_body ),
	'a body of 10,001 characters'                 => save_mentor( 'Hi', $good_body . str_repeat( 'b', 10001 - mb_strlen( $good_body ) ) ),
);
ck(
	'a save is refused, each with its own sentence, when a box is empty, a required placeholder is missing or misspelled, a placeholder is not the email\'s, WordPress would take words away, or a box is too long',
	$refusals,
	array(
		'the subject left empty'                    => 'Nothing was saved: the subject is empty.',
		'the body left empty'                       => 'Nothing was saved: the body is empty.',
		'the set-password link dropped'             => 'Nothing was saved: this email needs {set_password_link}.',
		'the set-password link misspelled'          => 'Nothing was saved: this email needs {set_password_link}.',
		'a placeholder the email does not have'     => 'Nothing was saved: {student_nmae} is not a placeholder of this email.',
		'two of them'                               => 'Nothing was saved: {a}, {b} are not placeholders of this email.',
		'a subject WordPress would take words from' => WPCPM_Typed_Text::loss_message( 'Subject' ),
		'a body WordPress would take words from'    => WPCPM_Typed_Text::loss_message( 'Body' ),
		'a subject of 201 characters'               => 'Nothing was saved: the subject is longer than 200 characters.',
		'a body of 10,001 characters'               => 'Nothing was saved: the body is longer than 10,000 characters.',
	)
);
// The set-password link goes in the body, never in the subject: a subject is no link a person can
// follow, and the mail log keeps it for 30 days.
ck( 'the set-password link in the subject alone is missing from the body, and refused as missing', save_mentor( 'Link: {set_password_link}', 'Welcome, mentor.' ), 'Nothing was saved: this email needs {set_password_link}.' );
ck( 'and in the subject beside the body\'s, it is refused with its own sentence', save_mentor( 'Link: {set_password_link}', $good_body ), 'Nothing was saved: {set_password_link} goes in the body, never in the subject.' );
ck( 'and a refused save stores nothing', array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ), false );
ck( 'while 200 and 10,000 characters are allowed', array( save_mentor( str_repeat( 'a', 200 ), $good_body . str_repeat( 'b', 10000 - mb_strlen( $good_body ) ) ), WPCPM_Mail_Templates::restore( 'invite-mentor' ) ), array( true, true ) );

$GLOBALS['locales'] = array();
$before             = time();
ck( 'a good save is stored', save_mentor( '[{site_name}] Hi <3', $good_body ), true );
$edit = WPCPM_Mail_Templates::edit( 'invite-mentor' );
ck( 'as WordPress\'s cleaner keeps it, with who saved it, when, and the fingerprint of the shipped text it was made from', array( $edit['subject'], $edit['body'], $edit['by'], $edit['at'] >= $before, $edit['shipped'] === md5( $shipped['subject'] . "\n\n" . $shipped['body'] ) ), array( '[{site_name}] Hi &lt;3', "Welcome, mentor.\n\n{set_password_link}\n\nWe &lt;3 our mentors &amp; their students.", 7, true, true ) );
ck( 'in one option, not loaded on every page', array( array_keys( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates'] ), $GLOBALS['autoload'][ WPCPM_Mail_Templates::OPTION ] ), array( array( 'invite-mentor' ), false ) );
ck( 'the fingerprint is built in the site\'s own language, whatever the Administrator reads', $GLOBALS['locales'], array( 'en_US', 'restored' ) );
ck( 'the email is Edited, and goes out as edited, what was typed read back as typed', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::render( 'invite-mentor', array( 'site_name' => 'WordPress Credits', 'set_password_link' => 'LINK' ) ) ), array( 'Edited', array( 'subject' => '[WordPress Credits] Hi <3', 'body' => "Welcome, mentor.\r\n\r\nLINK\r\n\r\nWe <3 our mentors & their students." ) ) );
ck( 'its edit is the mentor invitation\'s alone', WPCPM_Mail_Templates::text_of( 'invite-student' )['subject'], WPCPM_Mail_Templates::get( 'invite-student' )['subject'] );

// An update that changes the shipped text an edit was made from: the edit is still sent, and the
// state says the shipped text has changed since. The stored fingerprint stands in for an older text.
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['shipped'] = md5( 'the text an earlier version shipped' );
ck( 'when the shipped text has changed since the edit, the edit is still sent and the state says so', array( WPCPM_Mail_Templates::state( 'invite-mentor' )['state'], WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::text_of( 'invite-mentor' )['subject'] ), array( 'changed', 'Edited, and the shipped text has changed since', '[{site_name}] Hi <3' ) );

// An edit an update no longer fits, as one stored before the update is left: set aside, and the
// shipped text sent.
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body'] = $edit['body'] . ' About {topic}.';
ck( 'an edit that uses a placeholder the email no longer has is set aside, says why, and the shipped text is sent', array( WPCPM_Mail_Templates::state( 'invite-mentor' ), WPCPM_Mail_Templates::text_of( 'invite-mentor' ) ), array( array( 'state' => 'set-aside', 'reason' => 'Edit set aside: it uses {topic}, which this email no longer has.', 'by' => 7, 'at' => $edit['at'] ), array( 'subject' => $shipped['subject'], 'body' => $shipped['body'] ) ) );
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body'] = 'Welcome, mentor. Sign in when you can.';
ck( 'and so is one without the set-password link, which never goes out without it', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), false !== strpos( WPCPM_Mail_Templates::render( 'invite-mentor', array( 'set_password_link' => 'LINK' ) )['body'], 'LINK' ) ), array( 'Edit set aside: it lacks {set_password_link}, which this email needs.', true ) );
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['subject'] = 'Your link: {set_password_link}';
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-mentor']['body']    = "Welcome, mentor.\n\n{set_password_link}";
ck( 'and so is one whose subject holds the set-password link, so no subject carries the key: the shipped subject is sent', array( WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( 'invite-mentor' ) ), WPCPM_Mail_Templates::render( 'invite-mentor', array( 'site_name' => 'WordPress Credits', 'set_password_link' => 'LINK' ) )['subject'] ), array( 'Edit set aside: its subject uses {set_password_link}, which goes in the body.', '[WordPress Credits] Your mentor account is ready' ) );

ck( 'Restore the original drops the edit, and the email is shipped text again', array( WPCPM_Mail_Templates::restore( 'invite-mentor' ), WPCPM_Mail_Templates::edit( 'invite-mentor' ), WPCPM_Mail_Templates::state( 'invite-mentor' )['state'] ), array( true, null, 'shipped' ) );
ck( 'and there is nothing to restore a second time', WPCPM_Mail_Templates::restore( 'invite-mentor' ), false );

ck( 'a typing that reads as the shipped text stores nothing, so the email goes on translating', array( save_mentor( $shipped['subject'], str_replace( "\n", "\r\n", $shipped['body'] ) ), WPCPM_Mail_Templates::edit( 'invite-mentor' ) ), array( true, null ) );
$unheld = WPCPM_Mail_Templates::save( 'call-booked', 'Hi', 'Body', 7 );
ck( 'an email the registry does not hold is refused with its own sentence', is_wp_error( $unheld ) ? $unheld->get_error_message() : $unheld, 'Nothing was saved: the site has no such email.' );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

The uninstall deletes the edits with everything else the plugin keeps. Three changes in `bin/test-uninstall.php`:

In `bin/test-uninstall.php`, replace:

```php
// The email log's schema version and the time of its last cleanup (1.122.18); its table is dropped.
$GLOBALS['opts']['wpcpm_mail_log_version'] = 1;
$GLOBALS['opts']['wpcpm_mail_log_purged']  = time();
$GLOBALS['opts']['wpcpm_invite_queue'] = array( 7 );
$GLOBALS['opts']['wpcpm_invite_run']   = array( 'total' => 241, 'started' => time() - HOUR_IN_SECONDS, 'finished' => 0 );

```

with:

```php
// The email log's schema version and the time of its last cleanup (1.122.18); its table is dropped.
$GLOBALS['opts']['wpcpm_mail_log_version'] = 1;
$GLOBALS['opts']['wpcpm_mail_log_purged']  = time();
// The Administrators' edits of the emails and the footer (1.122.19), with who saved each.
$GLOBALS['opts']['wpcpm_mail_templates'] = array( 'templates' => array( 'invite-student' => array( 'subject' => 'Hi', 'body' => '{set_password_link}', 'by' => 1, 'at' => time(), 'shipped' => 'x' ) ), 'footer' => null );
$GLOBALS['opts']['wpcpm_invite_queue'] = array( 7 );
$GLOBALS['opts']['wpcpm_invite_run']   = array( 'total' => 241, 'started' => time() - HOUR_IN_SECONDS, 'finished' => 0 );

```

In `bin/test-uninstall.php`, replace:

```php

ck( 'the mail log goes, with the addresses in it', get_option( 'wpcpm_mail_log', 'gone' ), 'gone' );
ck( 'and so do the email log\'s table, its schema version and the time of its last cleanup', array( in_array( 'DROP TABLE IF EXISTS wp_wpcpm_mail_log', $wpdb->queries, true ), get_option( 'wpcpm_mail_log_version', 'gone' ), get_option( 'wpcpm_mail_log_purged', 'gone' ) ), array( true, 'gone', 'gone' ) );
ck( 'and so does everybody still waiting for an invitation', get_option( 'wpcpm_invite_queue', 'gone' ), 'gone' );
ck( 'and the counts and times of the last bulk invite', get_option( 'wpcpm_invite_run', 'gone' ), 'gone' );

```

with:

```php

ck( 'the mail log goes, with the addresses in it', get_option( 'wpcpm_mail_log', 'gone' ), 'gone' );
ck( 'and so do the email log\'s table, its schema version and the time of its last cleanup', array( in_array( 'DROP TABLE IF EXISTS wp_wpcpm_mail_log', $wpdb->queries, true ), get_option( 'wpcpm_mail_log_version', 'gone' ), get_option( 'wpcpm_mail_log_purged', 'gone' ) ), array( true, 'gone', 'gone' ) );
ck( 'and so do the Administrators\' edits of the emails and the footer', get_option( 'wpcpm_mail_templates', 'gone' ), 'gone' );
ck( 'and so does everybody still waiting for an invitation', get_option( 'wpcpm_invite_queue', 'gone' ), 'gone' );
ck( 'and the counts and times of the last bulk invite', get_option( 'wpcpm_invite_run', 'gone' ), 'gone' );

```

In `bin/test-uninstall.php`, replace:

```php
	'WPCPM_Mail_Log::OPT_VERSION'                   => 'wpcpm_mail_log_version',
	'WPCPM_Mail_Log::OPT_PURGED'                    => 'wpcpm_mail_log_purged',
	'WPCPM_Mail_Log::PURGE_HOOK'                    => 'wpcpm_mail_log_purge',
	'WPCPM_Private_Files::OPT_KEY'                  => 'wpcpm_private_key',
	'WPCPM_Track_Publish::OPT_LOCK'                 => 'wpcpm_track_publish_lock',
	'WPCPM_Airtable::SCHEMA_TRANSIENT'              => 'wpcpm_airtable_schema',
```

with:

```php
	'WPCPM_Mail_Log::OPT_VERSION'                   => 'wpcpm_mail_log_version',
	'WPCPM_Mail_Log::OPT_PURGED'                    => 'wpcpm_mail_log_purged',
	'WPCPM_Mail_Log::PURGE_HOOK'                    => 'wpcpm_mail_log_purge',
	'WPCPM_Mail_Templates::OPTION'                  => 'wpcpm_mail_templates',
	'WPCPM_Private_Files::OPT_KEY'                  => 'wpcpm_private_key',
	'WPCPM_Track_Publish::OPT_LOCK'                 => 'wpcpm_track_publish_lock',
	'WPCPM_Airtable::SCHEMA_TRANSIENT'              => 'wpcpm_airtable_schema',
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-mail-templates.php`
Expected: a fatal error at the first edit's check, `Call to undefined method WPCPM_Mail_Templates::state()`.

Run: `php bin/test-uninstall.php`
Expected: `3 FAILED (36 checks)`: "no option of the plugin's is left but that key", "and so do the Administrators' edits of the emails and the footer", and "the names seeded above are the ones the classes declare" (the registry declares no `OPTION` yet).

- [ ] **Step 3: The edits**

The constants first:

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
	/** What a placeholder looks like: a word in braces. */
	const PLACEHOLDER = '/\{([A-Za-z0-9_-]+)\}/';
```

with:

```php
	/** What a placeholder looks like: a word in braces. */
	const PLACEHOLDER = '/\{([A-Za-z0-9_-]+)\}/';

	/** The option holding the Administrators' edits, not autoloaded. */
	const OPTION = 'wpcpm_mail_templates';

	/** The most characters a subject may have. */
	const SUBJECT_MAX = 200;

	/** The most characters a body may have. */
	const BODY_MAX = 10000;

	/** An email sent as the registry ships it. */
	const STATE_SHIPPED = 'shipped';

	/** An email sent as an Administrator edited it. */
	const STATE_EDITED = 'edited';

	/** An edited email whose shipped text an update has changed since the edit was saved. */
	const STATE_CHANGED = 'changed';

	/** An edit that no longer fits its email, so the shipped text is sent. */
	const STATE_SET_ASIDE = 'set-aside';
```

`render()` fills the text that goes out:

In `includes/mail/class-wpcpm-mail-templates.php`, replace the method `render()`, its docblock included, with:

```php
	/**
	 * An email's subject and body as it goes out, filled with its values: the Administrators' edit
	 * when one fits, the shipped text otherwise (`text_of()`). No footer: the signature and footer is
	 * added once, where the email leaves, never by a builder.
	 *
	 * @param string $id     The email's id.
	 * @param array  $values Each placeholder's value, by name, worked out by whatever sends it.
	 * @return array{subject:string,body:string}|null Null when the registry has no such email.
	 */
	public static function render( $id, array $values ) {
		$entry = self::get( $id );

		if ( null === $entry ) {
			return null;
		}

		$text = self::text_of( $id );

		return array(
			'subject' => self::fill( $text['subject'], $values, $entry['placeholders'], $entry['eol'], false ),
			'body'    => self::fill( $text['body'], $values, $entry['placeholders'], $entry['eol'] ),
		);
	}
```

Then the edits' section, after `page_of_type()`:

In `includes/mail/class-wpcpm-mail-templates.php`, add before the class's closing brace:

```php
	/*
	 * Edits
	 * --------------------------------------------------------------------
	 */

	/**
	 * An email's text as it goes out: the Administrators' edit when there is one that fits, the
	 * shipped text otherwise. An edit fits while every placeholder it uses is one the email has,
	 * every required one is in its body and none that goes in the body alone is in its subject; one
	 * that no longer fits, because an update took a placeholder away or made one required or
	 * body-only, is set aside and the shipped text sent, and the Templates tab says why (`state()`).
	 *
	 * An edit is stored as WordPress's cleaner kept it, and read back here as the plain text it was
	 * typed as (`WPCPM_Typed_Text::mail_text()`), as every typed text the plugin mails is.
	 *
	 * @param string $id The email's id.
	 * @return array{subject:string,body:string}|null Null when the registry has no such email.
	 */
	public static function text_of( $id ) {
		$entry = self::entry_of( $id );

		if ( null === $entry ) {
			return null;
		}

		$edit = self::edit( $id );

		if ( null === $edit || array() !== self::misfit( $entry['placeholders'], $edit['subject'], $edit['body'] ) ) {
			return array(
				'subject' => $entry['subject'],
				'body'    => $entry['body'],
			);
		}

		return array(
			'subject' => WPCPM_Typed_Text::mail_text( $edit['subject'] ),
			'body'    => WPCPM_Typed_Text::mail_text( $edit['body'] ),
		);
	}

	/**
	 * The entry an id names: a template of the registry's.
	 *
	 * @param string $id The email's id.
	 * @return array|null
	 */
	private static function entry_of( $id ) {
		return self::get( $id );
	}

	/**
	 * What keeps a text from fitting an email, each placeholder written in its braces: the ones it
	 * uses, in either box, that the email does not have; the required ones its body leaves out; and
	 * the ones that go in the body alone that its subject holds.
	 *
	 * @param array  $placeholders The email's placeholders.
	 * @param string $subject      The subject, '' for a text that has none.
	 * @param string $body         The body.
	 * @return array{unknown?:string[],missing?:string[],in_subject?:string[]} Empty when the text fits.
	 */
	private static function misfit( array $placeholders, $subject, $body ) {
		$in_subject = self::placeholders_in( (string) $subject );
		$in_body    = self::placeholders_in( (string) $body );
		$unknown    = array_values( array_diff( array_unique( array_merge( $in_subject, $in_body ) ), array_keys( $placeholders ) ) );
		$missing    = array_values( array_diff( self::required( $placeholders ), $in_body ) );
		$misplaced  = array_values( array_intersect( self::body_only( $placeholders ), $in_subject ) );
		$braced     = static function ( $name ) {
			return '{' . $name . '}';
		};
		$out        = array();

		if ( array() !== $unknown ) {
			$out['unknown'] = array_map( $braced, $unknown );
		}

		if ( array() !== $missing ) {
			$out['missing'] = array_map( $braced, $missing );
		}

		if ( array() !== $misplaced ) {
			$out['in_subject'] = array_map( $braced, $misplaced );
		}

		return $out;
	}

	/**
	 * Everything the option holds, in its shape: each edited template by id, and the footer.
	 *
	 * @return array{templates:array<string,array>,footer:array|null}
	 */
	private static function stored() {
		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'templates' => isset( $stored['templates'] ) && is_array( $stored['templates'] ) ? $stored['templates'] : array(),
			'footer'    => isset( $stored['footer'] ) && is_array( $stored['footer'] ) ? $stored['footer'] : null,
		);
	}

	/**
	 * The edit an email holds, as stored: its subject and body, who saved it, when, and the
	 * fingerprint of the shipped text it was made from.
	 *
	 * @param string $id The email's id.
	 * @return array{subject:string,body:string,by:int,at:int,shipped:string}|null Null when it has none.
	 */
	public static function edit( $id ) {
		$edit = self::read_edit( (string) $id );

		if ( ! is_array( $edit ) || ! isset( $edit['subject'], $edit['body'] ) ) {
			return null;
		}

		return array(
			'subject' => (string) $edit['subject'],
			'body'    => (string) $edit['body'],
			'by'      => isset( $edit['by'] ) ? (int) $edit['by'] : 0,
			'at'      => isset( $edit['at'] ) ? (int) $edit['at'] : 0,
			'shipped' => isset( $edit['shipped'] ) ? (string) $edit['shipped'] : '',
		);
	}

	/**
	 * An email's edit as the option holds it.
	 *
	 * @param string $id The email's id.
	 * @return mixed The stored value, or null.
	 */
	private static function read_edit( $id ) {
		$stored = self::stored();

		return isset( $stored['templates'][ $id ] ) ? $stored['templates'][ $id ] : null;
	}

	/**
	 * Store an email's edit, or drop it, and leave every other edit as it was.
	 *
	 * @param string     $id   The email's id.
	 * @param array|null $edit The edit, or null to drop it.
	 */
	private static function write_edit( $id, $edit ) {
		$stored = self::stored();

		if ( null === $edit ) {
			unset( $stored['templates'][ $id ] );
		} else {
			$stored['templates'][ $id ] = $edit;
		}

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Why a typing may not be saved, or '' when it may, by the rules every save of the Emails tool
	 * follows, in this order: neither box is empty, the footer's one box included, since every email
	 * ends with it; every required placeholder is in the body; no placeholder that goes in the body
	 * alone is in the subject; every placeholder is one of the email's; WordPress's cleaner would
	 * take nothing from the text (the rule every form of the plugin follows); and the subject is at
	 * most `SUBJECT_MAX` characters and the body at most `BODY_MAX`, counted as their boxes count
	 * them.
	 *
	 * @param array       $placeholders The email's placeholders.
	 * @param string|null $subject      The subject as posted, unslashed and not cleaned; null for a
	 *                                  text that has none, the footer.
	 * @param string      $body         The body as posted, unslashed and not cleaned.
	 * @return string The sentence that refuses the save, or ''.
	 */
	public static function refusal( array $placeholders, $subject, $body ) {
		$body = (string) $body;

		if ( null !== $subject && '' === trim( (string) $subject ) ) {
			return __( 'Nothing was saved: the subject is empty.', 'wpcredits-program-manager' );
		}

		if ( '' === trim( $body ) ) {
			return null === $subject
				? __( 'Nothing was saved: the signature and footer is empty.', 'wpcredits-program-manager' )
				: __( 'Nothing was saved: the body is empty.', 'wpcredits-program-manager' );
		}

		$misfit = self::misfit( $placeholders, (string) $subject, $body );

		if ( isset( $misfit['missing'] ) ) {
			return sprintf(
				/* translators: %s: the placeholders the email needs, such as {set_password_link}. */
				__( 'Nothing was saved: this email needs %s.', 'wpcredits-program-manager' ),
				implode( ', ', $misfit['missing'] )
			);
		}

		if ( isset( $misfit['in_subject'] ) ) {
			return sprintf(
				/* translators: %s: the placeholders that go in the body alone, such as {set_password_link}. */
				_n( 'Nothing was saved: %s goes in the body, never in the subject.', 'Nothing was saved: %s go in the body, never in the subject.', count( $misfit['in_subject'] ), 'wpcredits-program-manager' ),
				implode( ', ', $misfit['in_subject'] )
			);
		}

		if ( isset( $misfit['unknown'] ) ) {
			return sprintf(
				/* translators: %s: the placeholders the email does not have, such as {student_nmae}. */
				_n( 'Nothing was saved: %s is not a placeholder of this email.', 'Nothing was saved: %s are not placeholders of this email.', count( $misfit['unknown'] ), 'wpcredits-program-manager' ),
				implode( ', ', $misfit['unknown'] )
			);
		}

		if ( null !== $subject && WPCPM_Typed_Text::cleaner_loses( (string) $subject, 'line' ) ) {
			return WPCPM_Typed_Text::loss_message( __( 'Subject', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Typed_Text::cleaner_loses( $body, 'lines' ) ) {
			return WPCPM_Typed_Text::loss_message( null === $subject ? __( 'Signature and footer', 'wpcredits-program-manager' ) : __( 'Body', 'wpcredits-program-manager' ) );
		}

		if ( null !== $subject && WPCPM_Typed_Text::box_length( (string) $subject ) > self::SUBJECT_MAX ) {
			return sprintf(
				/* translators: %s: the most characters a subject may have. */
				__( 'Nothing was saved: the subject is longer than %s characters.', 'wpcredits-program-manager' ),
				number_format_i18n( self::SUBJECT_MAX )
			);
		}

		if ( WPCPM_Typed_Text::box_length( $body ) > self::BODY_MAX ) {
			return sprintf(
				null === $subject
					/* translators: %s: the most characters the signature and footer may have. */
					? __( 'Nothing was saved: the signature and footer is longer than %s characters.', 'wpcredits-program-manager' )
					/* translators: %s: the most characters a body may have. */
					: __( 'Nothing was saved: the body is longer than %s characters.', 'wpcredits-program-manager' ),
				number_format_i18n( self::BODY_MAX )
			);
		}

		return '';
	}

	/**
	 * Store an Administrator's edit of an email, when `refusal()` lets it be stored: its subject and
	 * body as WordPress's cleaner keeps them, each line break one "\n", with who saved it, when, and
	 * the fingerprint of the shipped text it was made from. A typing that reads as the shipped text
	 * stores nothing, and drops any edit, so the email stays shipped text and goes on translating.
	 *
	 * @param string      $id      The email's id.
	 * @param string|null $subject The subject as posted, unslashed and not cleaned; null for a text
	 *                             that has none.
	 * @param string      $body    The body as posted, unslashed and not cleaned.
	 * @param int         $user_id Who saved it.
	 * @return true|WP_Error `wpcpm_mail_refused`, carrying the sentence that refuses it, when nothing
	 *                       was stored.
	 */
	public static function save( $id, $subject, $body, $user_id ) {
		$id    = (string) $id;
		$entry = self::entry_of( $id );

		if ( null === $entry ) {
			return new WP_Error( 'wpcpm_mail_refused', __( 'Nothing was saved: the site has no such email.', 'wpcredits-program-manager' ) );
		}

		$refused = self::refusal( $entry['placeholders'], $subject, $body );

		if ( '' !== $refused ) {
			return new WP_Error( 'wpcpm_mail_refused', $refused );
		}

		$subject = null === $subject ? '' : sanitize_text_field( (string) $subject );
		$body    = str_replace( array( "\r\n", "\r" ), "\n", sanitize_textarea_field( (string) $body ) );

		if ( self::reads_as( $subject, $entry['subject'] ) && self::reads_as( $body, $entry['body'] ) ) {
			self::write_edit( $id, null );

			return true;
		}

		self::write_edit(
			$id,
			array(
				'subject' => $subject,
				'body'    => $body,
				'by'      => (int) $user_id,
				'at'      => time(),
				'shipped' => self::shipped_fingerprint( $id ),
			)
		);

		return true;
	}

	/**
	 * Whether a stored text reads as another: the same once its entities are read back and the white
	 * space at either end is let go.
	 *
	 * @param string $stored A text as the cleaner kept it.
	 * @param string $other  Another, as the registry ships it.
	 * @return bool
	 */
	private static function reads_as( $stored, $other ) {
		return trim( WPCPM_Typed_Text::mail_text( $stored ) ) === trim( str_replace( array( "\r\n", "\r" ), "\n", (string) $other ) );
	}

	/**
	 * Drop an email's edit: from now on it is sent as shipped.
	 *
	 * @param string $id The email's id.
	 * @return bool Whether there was an edit to drop.
	 */
	public static function restore( $id ) {
		if ( null === self::read_edit( (string) $id ) ) {
			return false;
		}

		self::write_edit( (string) $id, null );

		return true;
	}

	/**
	 * A fingerprint of a shipped text: how the site tells, later, that an update changed the text an
	 * edit was made from.
	 *
	 * Built in the site's own language, whichever language the Administrator who saved reads, so two
	 * Administrators reading two languages are told the same thing.
	 *
	 * @param string $id The email's id.
	 * @return string
	 */
	public static function shipped_fingerprint( $id ) {
		$switched = function_exists( 'switch_to_locale' ) && function_exists( 'get_locale' ) ? switch_to_locale( get_locale() ) : false;
		$entry    = self::entry_of( $id );

		if ( $switched ) {
			restore_previous_locale();
		}

		return null === $entry ? '' : md5( $entry['subject'] . "\n\n" . $entry['body'] );
	}

	/**
	 * Where an email stands: shipped text, edited, edited with the shipped text changed since, or an
	 * edit set aside, with why, and who saved the edit and when.
	 *
	 * @param string $id The email's id.
	 * @return array{state:string,reason:string,by:int,at:int}
	 */
	public static function state( $id ) {
		$entry = self::entry_of( $id );
		$edit  = null === $entry ? null : self::edit( $id );

		if ( null === $edit ) {
			return array(
				'state'  => self::STATE_SHIPPED,
				'reason' => '',
				'by'     => 0,
				'at'     => 0,
			);
		}

		$misfit = self::misfit( $entry['placeholders'], $edit['subject'], $edit['body'] );
		$reason = '';

		if ( isset( $misfit['unknown'] ) ) {
			$reason = sprintf(
				/* translators: %s: the placeholders the edit uses, such as {topic}. */
				__( 'Edit set aside: it uses %s, which this email no longer has.', 'wpcredits-program-manager' ),
				implode( ', ', $misfit['unknown'] )
			);
		} elseif ( isset( $misfit['missing'] ) ) {
			$reason = sprintf(
				/* translators: %s: the placeholders the email needs, such as {set_password_link}. */
				__( 'Edit set aside: it lacks %s, which this email needs.', 'wpcredits-program-manager' ),
				implode( ', ', $misfit['missing'] )
			);
		} elseif ( isset( $misfit['in_subject'] ) ) {
			$reason = sprintf(
				/* translators: %s: the placeholders that go in the body alone, such as {set_password_link}. */
				_n( 'Edit set aside: its subject uses %s, which goes in the body.', 'Edit set aside: its subject uses %s, which go in the body.', count( $misfit['in_subject'] ), 'wpcredits-program-manager' ),
				implode( ', ', $misfit['in_subject'] )
			);
		}

		if ( '' !== $reason ) {
			$state = self::STATE_SET_ASIDE;
		} elseif ( self::shipped_fingerprint( $id ) !== $edit['shipped'] ) {
			$state = self::STATE_CHANGED;
		} else {
			$state = self::STATE_EDITED;
		}

		return array(
			'state'  => $state,
			'reason' => $reason,
			'by'     => $edit['by'],
			'at'     => $edit['at'],
		);
	}

	/**
	 * What a state is called on the Templates tab.
	 *
	 * @param array $state What `state()` answered.
	 * @return string
	 */
	public static function state_label( array $state ) {
		switch ( isset( $state['state'] ) ? $state['state'] : '' ) {
			case self::STATE_EDITED:
				return __( 'Edited', 'wpcredits-program-manager' );
			case self::STATE_CHANGED:
				return __( 'Edited, and the shipped text has changed since', 'wpcredits-program-manager' );
			case self::STATE_SET_ASIDE:
				return (string) $state['reason'];
		}

		return __( 'Shipped text', 'wpcredits-program-manager' );
	}
```

- [ ] **Step 4: The uninstall deletes the option**

In `uninstall.php`, replace:

```php
// and the daily cleanup; anyone still waiting for an invitation that is no longer coming, and the
// counts and times of the last bulk invite (the final fix wave of the deep check of 1.109.1).
WPCPM_Mail_Log::uninstall();
WPCPM_Mail::clear_queue();
delete_option( WPCPM_Mail::RUN_OPTION );

```

with:

```php
// and the daily cleanup; anyone still waiting for an invitation that is no longer coming, and the
// counts and times of the last bulk invite (the final fix wave of the deep check of 1.109.1).
WPCPM_Mail_Log::uninstall();
// The Administrators' edits of the emails and of the signature and footer (1.122.19).
delete_option( WPCPM_Mail_Templates::OPTION );
WPCPM_Mail::clear_queue();
delete_option( WPCPM_Mail::RUN_OPTION );

```

- [ ] **Step 5: Run the tests to verify they pass**

Run, one per command: `php bin/test-mail-templates.php`, `php bin/test-uninstall.php`, `php bin/test-invitation-text.php`
Expected: `ALL PASS (46 checks)`; `ALL PASS (36 checks)`; `ALL PASS (39 checks)`: with no edit stored, every invitation is still the recording's.

- [ ] **Step 6: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 7: Commit**

```bash
git add includes/mail/class-wpcpm-mail-templates.php uninstall.php bin/test-mail-templates.php bin/test-uninstall.php
git commit -m "Emails E2: the Administrators' edits, the rules a save passes, and where each email stands"
```

---

### Task 5: The signature and footer, at the end of every plugin email

One block, its shipped text the owner's approved wording word for word (ruling PR-2), filled for each recipient and added after a blank line and a line holding `-- `: in `WPCPM_Mail::hand_off()` for the emails the plugin sends, and in `welcome_email()` for the invitations WordPress sends. It is edited, restored and told apart from its shipped text as a template is, under the name `WPCPM_Mail_Templates::FOOTER`, and an empty one is never saved (ruling PR-6). `{program_contact}` reads Contact address in emails (`mail_contact`), which Task 8 makes a setting; until then, and as it ships, it is empty and its sentence goes. `{dashboard_link}` is empty for a recipient with no account, and for a body that names the address already, as every invitation does under its last label, so no email names it twice.

The footer is added only where the registry is loaded, which on a site is always: a suite that loads the mail layer without it goes on testing its builders' own text, while bin/test-mail.php loads the registry and holds every send to the footer.

**Files:**
- Modify: `includes/mail/class-wpcpm-mail-templates.php` (`FOOTER`; `entry_of()`, `read_edit()` and `write_edit()` know the footer; the footer's section)
- Modify: `includes/class-wpcpm-mail.php` (`hand_off()` adds the footer; `welcome_email()` too)
- Modify: `bin/test-mail-templates.php`, `bin/test-mail.php`, `bin/test-invitation-text.php`

**Interfaces:**
- Consumes: `WPCPM_Mail_Capture::type_of( ?WP_User $user, string $audience ): string`; `WPCPM_Settings::get_value( 'mail_contact', '' )` (the setting arrives in Task 8; until then the value is the fallback); `is_email()`.
- Produces:
  - `WPCPM_Mail_Templates::FOOTER` (`footer`); `footer_shipped(): string`; `footer_placeholders(): array` (`site_name`, `dashboard_link`, `program_contact`, none required).
  - `program_contact(): string`; `dashboard_of( ?WP_User $recipient, string $audience = '' ): string`.
  - `footer_values( ?WP_User $recipient, string $context, string[] $used ): array`, only the values the footer's text uses; `footer_examples( string $context ): array`, for a preview or a test.
  - `add_footer( string $body, ?WP_User $recipient, string $context, ?array $values = null ): string`; `{dashboard_link}` is empty there when the body holds its address already (ruling PR-2).
  - `save( WPCPM_Mail_Templates::FOOTER, null, $body, $user_id )`, `restore( FOOTER )`, `state( FOOTER )`, `text_of( FOOTER )` work for the footer.
  - A builder handed to `WPCPM_Mail::send()` or `send_to()` may return `footer_values`, the footer's values in place of the recipient's.

- [ ] **Step 1: Write the failing checks**

Every suite is plain PHP that prints an `ok` or `FAIL` line for each check through its `ck()` helper, ends on a count, and exits non-zero on a failure. The helper and the two closing lines, as a new suite writes them (an existing suite keeps its own, whose arguments come in the same order: the label, what was got, what was wanted):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

The registry's suite reads which kind of person an account is (the capture's `type_of()`) and the one setting the footer reads, through a stand-in. Three changes in `bin/test-mail-templates.php`:

In `bin/test-mail-templates.php`, replace:

```php
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's `add_query_arg( $args, $url )`, as the example set-password link is built over the login page.
function add_query_arg( $args, $url ) { foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; } return $url; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function current_user_can( $cap ) { return false; }
```

with:

```php
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
// Core's `add_query_arg( $args, $url )`, as the example set-password link is built over the login page.
function add_query_arg( $args, $url ) { foreach ( $args as $k => $v ) { $url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; } return $url; }
function get_bloginfo( $k = 'name' ) { $GLOBALS['site_name_read'] = ( $GLOBALS['site_name_read'] ?? 0 ) + 1; return 'WordPress Credits'; }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
// Nobody here may manage the program, so each account is the kind its role says.
function user_can( $user, $cap ) { return false; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['opts'][ $k ] = $v; $GLOBALS['autoload'][ $k ] = $autoload; return true; }
function current_user_can( $cap ) { return false; }
```

In `bin/test-mail-templates.php`, replace:

```php
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';

/** The dashboards a template's example links, each published at an address of its own. */
class WPCPM_Students_Dashboard {
```

with:

```php
require_once __DIR__ . '/../includes/class-wpcpm-typed-text.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
// Which kind of person an account is, which the footer's dashboard line reads.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

/** The one setting the footer reads, Contact address in emails, as a check sets it. */
class WPCPM_Settings {
	public static $contact = '';
	public static function get_value( $key, $fallback = null ) { return 'mail_contact' === $key ? self::$contact : $fallback; }
}

/** The dashboards a template's example links, each published at an address of its own. */
class WPCPM_Students_Dashboard {
```

In `bin/test-mail-templates.php`, replace:

```php
$unheld = WPCPM_Mail_Templates::save( 'call-booked', 'Hi', 'Body', 7 );
ck( 'an email the registry does not hold is refused with its own sentence', is_wp_error( $unheld ) ? $unheld->get_error_message() : $unheld, 'Nothing was saved: the site has no such email.' );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

with:

```php
$unheld = WPCPM_Mail_Templates::save( 'call-booked', 'Hi', 'Body', 7 );
ck( 'an email the registry does not hold is refused with its own sentence', is_wp_error( $unheld ) ? $unheld->get_error_message() : $unheld, 'Nothing was saved: the site has no such email.' );

echo "\n=== The signature and footer ===\n";

ck( 'the footer ships as the owner approved it, word for word', WPCPM_Mail_Templates::footer_shipped(), "WordPress Credits, WordPress Education\n{dashboard_link}\nThis email comes from the WordPress Credits program. Questions? Write to {program_contact}." );

$student_account = new WP_User( 30, 'Lu Example', 'lu@example.test', array( WPCPM_Roles::ROLE_STUDENT ) );
$mentor_student  = new WP_User( 31, 'Mo Example', 'mo@example.test', array( WPCPM_Roles::ROLE_STUDENT, WPCPM_Roles::ROLE_MENTOR ) );

$GLOBALS['site_name_read'] = 0;
ck( 'to an applicant with no account: a blank line, the "-- " line, then the footer with no dashboard line, and no contact sentence while Contact address in emails is empty', WPCPM_Mail_Templates::add_footer( "Thanks for applying.\nWe will write soon.", null, 'institution-applied' ), "Thanks for applying.\nWe will write soon.\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program." );
ck( 'and the footer reads nothing it does not print: the site\'s name is not asked for', $GLOBALS['site_name_read'], 0 );
ck( 'to a student, her dashboard is on its line', WPCPM_Mail_Templates::add_footer( 'Booked.', $student_account, 'call-booked' ), "Booked.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/student-report-card/\nThis email comes from the WordPress Credits program." );
ck( 'and to an account of two kinds, the email\'s audience decides which dashboard', WPCPM_Mail_Templates::add_footer( 'Ready.', $mentor_student, 'invite-mentor' ), "Ready.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/mentor-report-card/\nThis email comes from the WordPress Credits program." );
ck( 'and a body that already names that dashboard gets no second copy of it in the footer, by the plugin\'s rule that no address appears twice', WPCPM_Mail_Templates::add_footer( "Booked.\nYour Student Report Card:\nhttps://example.test/student-report-card/", $student_account, 'call-booked' ), "Booked.\nYour Student Report Card:\nhttps://example.test/student-report-card/\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program." );

WPCPM_Settings::$contact = 'programs@example.test';
ck( 'with Contact address in emails set, its sentence says where to write', WPCPM_Mail_Templates::add_footer( 'Booked.', null, 'call-booked' ), "Booked.\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program. Questions? Write to programs@example.test." );
WPCPM_Settings::$contact = 'not an address';
ck( 'and a setting that holds no address leaves the sentence out', false !== strpos( WPCPM_Mail_Templates::add_footer( 'Booked.', null, 'call-booked' ), 'Questions?' ), false );
WPCPM_Settings::$contact = '';

ck( 'a body written with CR LF that ends with one gets the footer in CR LF, and ends with one', WPCPM_Mail_Templates::add_footer( "Hello.\r\n", null, 'call-booked' ), "Hello.\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n" );
ck( 'a test hands its own values over, in place of the recipient\'s', WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked', array( 'dashboard_link' => 'https://example.test/x/', 'program_contact' => 'help@example.test' ) ), "Hi.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/x/\nThis email comes from the WordPress Credits program. Questions? Write to help@example.test." );
ck( 'and an email\'s examples are its audience\'s dashboard and the contact as set', array( WPCPM_Mail_Templates::footer_examples( 'invite-sponsor' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'call-booked' )['dashboard_link'], WPCPM_Mail_Templates::footer_examples( 'call-booked' )['program_contact'] ), array( 'https://example.test/sponsor-dashboard/', 'https://example.test/student-report-card/', '' ) );

/** What a save of the footer answers: true, or the sentence that refused it. */
function save_footer( $body ) {
	$saved = WPCPM_Mail_Templates::save( WPCPM_Mail_Templates::FOOTER, null, $body, 7 );

	return is_wp_error( $saved ) ? $saved->get_error_message() : $saved;
}

ck( 'the footer\'s save is refused for a placeholder it does not have, for words WordPress would take, and past 10,000 characters', array( save_footer( "Thanks.\n{student_name}" ), save_footer( 'Kids <12 free, adults >18 pay' ), save_footer( str_repeat( 'f', 10001 ) ) ), array( 'Nothing was saved: {student_name} is not a placeholder of this email.', WPCPM_Typed_Text::loss_message( 'Signature and footer' ), 'Nothing was saved: the signature and footer is longer than 10,000 characters.' ) );
ck( 'an edited footer is stored on its own and ends every email', array( save_footer( "Kind regards,\n{site_name}" ), array_keys( $GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates'] ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), WPCPM_Mail_Templates::state_label( WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER ) ) ), array( true, array(), "Hi.\n\n-- \nKind regards,\nWordPress Credits", 'Edited' ) );
ck( 'an empty footer is refused, since every email ends with it, and the one saved stays; a footer whose every line is left out adds nothing, not even the "-- " line', array( save_footer( '' ), save_footer( " \n " ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), save_footer( '{dashboard_link}' ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'institution-applied' ) ), array( 'Nothing was saved: the signature and footer is empty.', 'Nothing was saved: the signature and footer is empty.', "Hi.\n\n-- \nKind regards,\nWordPress Credits", true, 'Hi.' ) );
ck( 'Restore the original brings the shipped footer back', array( WPCPM_Mail_Templates::restore( WPCPM_Mail_Templates::FOOTER ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'], false !== strpos( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), 'WordPress Credits, WordPress Education' ) ), array( true, 'shipped', true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

In `bin/test-mail.php`, a section on the footer every send ends with, after the `send_to()` checks:

In `bin/test-mail.php`, replace:

```php
$sent = WPCPM_Mail::send_to( 'not-an-address', 'test', function () { return array( 'subject' => 'x', 'body' => 'y' ); } );
ck( 'something that is not an address is refused', array( $sent, count( $GLOBALS['mail'] ) ), array( false, 0 ) );

/* ---- a subject marked as plain text ------------------------------------- */

echo "\n=== A subject marked as plain text ===\n";
```

with:

```php
$sent = WPCPM_Mail::send_to( 'not-an-address', 'test', function () { return array( 'subject' => 'x', 'body' => 'y' ); } );
ck( 'something that is not an address is refused', array( $sent, count( $GLOBALS['mail'] ) ), array( false, 0 ) );

/* ---- the signature and footer ------------------------------------------- */

echo "\n=== The signature and footer every email ends with ===\n";

// Since 1.122.19 every email that leaves through the mail layer ends with the one footer, filled for
// its recipient. Lu has an account, but no dashboard page is published here, so no dashboard line;
// an applicant has no account at all; and Contact address in emails ships empty.
$footer           = "\n\n-- \nWordPress Credits, WordPress Education\nThis email comes from the WordPress Credits program.";
$GLOBALS['mail']  = array();
$filter_saw       = array();
$GLOBALS['filters']['wpcpm_mail'][] = function ( $mail ) use ( &$filter_saw ) {
	$filter_saw[] = $mail['body'];

	return $mail;
};
WPCPM_Mail::send( 30, 'call-booked', function () {
	return array( 'subject' => 'Booked', 'body' => "Your call is booked.\nSee you then." );
} );
WPCPM_Mail::send_to( 'applicant@example.test', 'institution-applied', function () {
	return array( 'subject' => 'Received', 'body' => 'We have your application.' );
} );
array_pop( $GLOBALS['filters']['wpcpm_mail'] );
ck( 'an email to an account and one to an applicant both end with the footer, after a blank line and the "-- " line, the applicant\'s with no dashboard line',
    array_column( $GLOBALS['mail'], 'body' ),
    array( "Your call is booked.\nSee you then." . $footer, 'We have your application.' . $footer ) );
ck( 'and the wpcpm_mail filter sees each email with its footer, as it goes out', $filter_saw, array_column( $GLOBALS['mail'], 'body' ) );

$GLOBALS['mail'] = array();
WPCPM_Mail::send( 30, 'call-booked', function () {
	return array( 'subject' => 'Booked', 'body' => 'Hi.', 'footer_values' => array( 'dashboard_link' => 'https://example.test/example/', 'program_contact' => 'help@example.test' ) );
} );
ck( 'a builder can hand the footer its values, as a test hands it examples', $GLOBALS['mail'][0]['body'], "Hi.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/example/\nThis email comes from the WordPress Credits program. Questions? Write to help@example.test." );

// The one way out: no file under includes/ calls wp_mail() but the mail layer's hand-off, where the
// footer is added, and the inventory mailed to the site's own address when the plugin is uninstalled,
// which is not a program email.
$callers = array();
foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( WPCPM_PLUGIN_DIR . 'includes', FilesystemIterator::SKIP_DOTS ) ) as $source ) {
	if ( 'php' !== $source->getExtension() ) {
		continue;
	}

	foreach ( token_get_all( (string) file_get_contents( $source->getPathname() ) ) as $i => $token ) {
		if ( is_array( $token ) && T_STRING === $token[0] && 'wp_mail' === $token[1] ) {
			$callers[] = $source->getFilename();
		}
	}
}
sort( $callers );
ck( 'every plugin email leaves through the hand-off that adds the footer: wp_mail() is called there and in the uninstall inventory alone', $callers, array( 'class-wpcpm-institution-agreement.php', 'class-wpcpm-mail.php' ) );

/* ---- a subject marked as plain text ------------------------------------- */

echo "\n=== A subject marked as plain text ===\n";
```

The recording is held to its bytes with the footer after them. Four changes in `bin/test-invitation-text.php`:

In `bin/test-invitation-text.php`, replace:

```php
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_action() {}
function add_filter() {}
require_once __DIR__ . '/stubs/specialchars.php';

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
```

with:

```php
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function add_action() {}
function add_filter() {}
// Nobody here may manage the program, so each account is the kind its role says.
function user_can( $user, $cap ) { return false; }
require_once __DIR__ . '/stubs/specialchars.php';

require_once __DIR__ . '/../includes/class-wpcpm-roles.php';
```

In `bin/test-invitation-text.php`, replace:

```php
// The template registry the invitations are built from since 1.122.19, and the catalog that names them.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
```

with:

```php
// The template registry the invitations are built from since 1.122.19, and the catalog that names them.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-catalog.php';
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-templates.php';
// Which kind of person an account is, which the footer's dashboard line reads.
require_once __DIR__ . '/../includes/mail/class-wpcpm-mail-capture.php';

/** The dashboards, each at the address a case gives it, '' while its page is not published. */
class WPCPM_Students_Dashboard {
```

In `bin/test-invitation-text.php`, replace:

```php
	);
}

$fixture = __DIR__ . '/fixtures/invitations-1-122-18.json';

if ( in_array( '--record', $argv, true ) ) {
```

with:

```php
	);
}

/**
 * The signature and footer an invitation ends with since 1.122.19, after the body as it was: a blank
 * line, the "-- " line, then the footer as the owner approved it, with no dashboard line, since an
 * invitation names the person's dashboard in its last lines already while its page is published and
 * no address appears twice; and no contact sentence, since Contact address in emails ships empty.
 *
 * @return string
 */
function footer_of() {
	return "\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n";
}

$fixture = __DIR__ . '/fixtures/invitations-1-122-18.json';

if ( in_array( '--record', $argv, true ) ) {
```

In `bin/test-invitation-text.php`, replace:

```php
	$got  = invitation( $case );

	ck( $name . ': the subject, byte for byte', $got['subject'], $want['subject'] );
	ck( $name . ': the body, byte for byte', $got['message'], $want['message'] );
}

ck( 'and the recording holds no real key: every link carries the fixed fake', is_file( $fixture ) && preg_match_all( '/key=([^&\s]+)/', (string) file_get_contents( $fixture ), $keys ) > 0 ? array_values( array_unique( $keys[1] ) ) : array(), array( 'TEST-KEY-NOT-A-REAL-LINK' ) );
```

with:

```php
	$got  = invitation( $case );

	ck( $name . ': the subject, byte for byte', $got['subject'], $want['subject'] );
	ck( $name . ': the body, byte for byte, then the signature and footer', $got['message'], null === $want['message'] ? null : $want['message'] . footer_of() );
}

ck( 'and the recording holds no real key: every link carries the fixed fake', is_file( $fixture ) && preg_match_all( '/key=([^&\s]+)/', (string) file_get_contents( $fixture ), $keys ) > 0 ? array_values( array_unique( $keys[1] ) ) : array(), array( 'TEST-KEY-NOT-A-REAL-LINK' ) );
```

- [ ] **Step 2: Run them to verify they fail**

Run: `php bin/test-mail-templates.php`
Expected: a fatal error at the footer's first check, `Call to undefined method WPCPM_Mail_Templates::footer_shipped()`.

Run: `php bin/test-mail.php`
Expected: `2 FAILURE(S)`: no email ends with the footer yet, and the builder's `footer_values` is not read.

Run: `php bin/test-invitation-text.php`
Expected: `18 FAILURE(S) (39 checks)`: every body is the recording's with no footer after it.

- [ ] **Step 3: The footer in the registry**

In `includes/mail/class-wpcpm-mail-templates.php`, replace:

```php
	/** An edit that no longer fits its email, so the shipped text is sent. */
	const STATE_SET_ASIDE = 'set-aside';
```

with:

```php
	/** An edit that no longer fits its email, so the shipped text is sent. */
	const STATE_SET_ASIDE = 'set-aside';

	/** The signature and footer, which is edited, restored and stored as a template is, under this name. */
	const FOOTER = 'footer';
```

The footer is an entry, its edit kept on its own in the option:

In `includes/mail/class-wpcpm-mail-templates.php`, replace the method `entry_of()`, its docblock included, with:

```php
	/**
	 * The entry an id names: a template of the registry's, or, for `FOOTER`, the signature and
	 * footer.
	 *
	 * @param string $id The email's id, or `FOOTER`.
	 * @return array|null
	 */
	private static function entry_of( $id ) {
		return self::FOOTER === (string) $id ? self::footer_entry() : self::get( $id );
	}
```

In `includes/mail/class-wpcpm-mail-templates.php`, replace the method `read_edit()`, its docblock included, with:

```php
	/**
	 * An email's edit as the option holds it: a template's under its id, the footer's on its own.
	 *
	 * @param string $id The email's id, or `FOOTER`.
	 * @return mixed The stored value, or null.
	 */
	private static function read_edit( $id ) {
		$stored = self::stored();

		if ( self::FOOTER === $id ) {
			return $stored['footer'];
		}

		return isset( $stored['templates'][ $id ] ) ? $stored['templates'][ $id ] : null;
	}
```

In `includes/mail/class-wpcpm-mail-templates.php`, replace the method `write_edit()`, its docblock included, with:

```php
	/**
	 * Store an email's edit, or drop it, and leave every other edit as it was.
	 *
	 * @param string     $id   The email's id, or `FOOTER`.
	 * @param array|null $edit The edit, or null to drop it.
	 */
	private static function write_edit( $id, $edit ) {
		$stored = self::stored();

		if ( self::FOOTER === $id ) {
			$stored['footer'] = $edit;
		} elseif ( null === $edit ) {
			unset( $stored['templates'][ $id ] );
		} else {
			$stored['templates'][ $id ] = $edit;
		}

		update_option( self::OPTION, $stored, false );
	}
```

Then the footer's section, after `state_label()`:

In `includes/mail/class-wpcpm-mail-templates.php`, add before the class's closing brace:

```php
	/*
	 * The signature and footer
	 * --------------------------------------------------------------------
	 */

	/**
	 * The signature and footer as the registry ships it, in the wording the owner approved on
	 * 10 October 2026: the text every plugin email ends with, after a line holding "-- ". Its third
	 * line holds for everybody the plugin writes to, an applicant turned down and an Administrator
	 * among them.
	 *
	 * @return string
	 */
	public static function footer_shipped() {
		/* translators: The signature and footer every email of the plugin ends with, three lines. Keep {dashboard_link} and {program_contact} as they are: the site puts the person's dashboard and the program's contact address in their place, and leaves out the line or the sentence that has nothing to say, reading a full stop followed by a space as the end of a sentence. */
		return __( "WordPress Credits, WordPress Education\n{dashboard_link}\nThis email comes from the WordPress Credits program. Questions? Write to {program_contact}.", 'wpcredits-program-manager' );
	}

	/**
	 * The signature and footer as an entry of the registry, so it is edited, restored and told
	 * apart from its shipped text as a template is: its body, no subject, and its placeholders, none
	 * of them required.
	 *
	 * @return array
	 */
	private static function footer_entry() {
		return array(
			'id'           => self::FOOTER,
			'label'        => __( 'Signature and footer', 'wpcredits-program-manager' ),
			'module'       => '',
			'audience'     => '',
			'trigger'      => __( 'Added to the end of every email the plugin sends.', 'wpcredits-program-manager' ),
			'subject'      => '',
			'body'         => self::footer_shipped(),
			'eol'          => "\n",
			'placeholders' => self::footer_placeholders(),
		);
	}

	/**
	 * The signature and footer's placeholders, none of them required.
	 *
	 * @return array Name to what `all()` holds for each.
	 */
	public static function footer_placeholders() {
		return array(
			'site_name'       => array(
				'means'    => __( 'The site\'s name.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return WPCPM_Mail::site_name();
				},
				'required' => false,
			),
			'dashboard_link'  => array(
				'means'    => __( 'The recipient\'s dashboard. Empty for somebody with no account, and for an email that names it already, which leaves its line out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return self::page_of_type( WPCPM_Mail_Catalog::TYPE_STUDENT );
				},
				'required' => false,
			),
			'program_contact' => array(
				'means'    => __( 'The address in Contact address in emails. Empty leaves its sentence out.', 'wpcredits-program-manager' ),
				'example'  => static function () {
					return self::program_contact();
				},
				'required' => false,
			),
		);
	}

	/**
	 * The address an email names for questions: the Contact address in emails setting, while it holds
	 * an address.
	 *
	 * @return string
	 */
	public static function program_contact() {
		$contact = class_exists( 'WPCPM_Settings' ) ? trim( (string) WPCPM_Settings::get_value( 'mail_contact', '' ) ) : '';

		return '' !== $contact && is_email( $contact ) ? $contact : '';
	}

	/**
	 * The dashboard of an email's recipient: the page of the kind of person the account is, the
	 * email's audience deciding for an account of several kinds, as the mail log reads it
	 * (`WPCPM_Mail_Capture::type_of()`).
	 *
	 * @param WP_User|null $recipient The account, or null for an address with none.
	 * @param string       $audience  The email's audience in the catalog, or '' when the account decides.
	 * @return string The page's address, or '' for nobody with an account or no page published.
	 */
	public static function dashboard_of( $recipient, $audience = '' ) {
		if ( ! $recipient instanceof WP_User || ! $recipient->exists() || ! class_exists( 'WPCPM_Mail_Capture' ) ) {
			return '';
		}

		return self::page_of_type( WPCPM_Mail_Capture::type_of( $recipient, (string) $audience ) );
	}

	/**
	 * The signature and footer's values for one email to one recipient: those the footer's text
	 * uses, so sending an email reads nothing the footer does not print.
	 *
	 * @param WP_User|null $recipient The account, or null for an address with none.
	 * @param string       $context   The email's id in the catalog.
	 * @param string[]     $used      The placeholders the footer's text uses.
	 * @return array<string,string>
	 */
	public static function footer_values( $recipient, $context, array $used ) {
		$named = WPCPM_Mail_Catalog::get( $context );
		$out   = array();

		if ( in_array( 'site_name', $used, true ) ) {
			$out['site_name'] = WPCPM_Mail::site_name();
		}

		if ( in_array( 'dashboard_link', $used, true ) ) {
			$out['dashboard_link'] = self::dashboard_of( $recipient, null === $named ? '' : $named['audience'] );
		}

		if ( in_array( 'program_contact', $used, true ) ) {
			$out['program_contact'] = self::program_contact();
		}

		return $out;
	}

	/**
	 * The signature and footer's values for a preview or a test of an email: the site's name, the
	 * dashboard of the email's audience (a student's, for an email to several kinds of people), and
	 * the contact address as set.
	 *
	 * @param string $context The email's id in the catalog.
	 * @return array<string,string>
	 */
	public static function footer_examples( $context ) {
		$named    = WPCPM_Mail_Catalog::get( $context );
		$audience = null === $named || '' === $named['audience'] ? WPCPM_Mail_Catalog::TYPE_STUDENT : $named['audience'];

		return array(
			'site_name'       => WPCPM_Mail::site_name(),
			'dashboard_link'  => self::page_of_type( $audience ),
			'program_contact' => self::program_contact(),
		);
	}

	/**
	 * A body with the signature and footer at its end: a blank line, a line holding "-- ", then the
	 * footer, filled with its values and written with the body's own line ending. The body is kept
	 * byte for byte, and so is the way it ended: one that ended with a line break ends with one
	 * after the footer too. A body that already names the recipient's dashboard, as an invitation
	 * does under its last label, gets no second copy of it: `{dashboard_link}` is empty there, so its
	 * line is left out, by the plugin's rule that no address appears twice. A footer that comes out
	 * empty, every line of it left out, adds nothing, the "-- " line included; an empty footer is
	 * never saved (`refusal()`).
	 *
	 * @param string       $body      The email's body.
	 * @param WP_User|null $recipient The account it goes to, or null for an address with none.
	 * @param string       $context   The email's id in the catalog.
	 * @param array|null   $values    The footer's values, to use in place of the recipient's: a
	 *                                test's, from `footer_examples()`.
	 * @return string
	 */
	public static function add_footer( $body, $recipient, $context, $values = null ) {
		$body   = (string) $body;
		$eol    = false !== strpos( $body, "\r\n" ) ? "\r\n" : "\n";
		$text   = self::text_of( self::FOOTER );
		$values = is_array( $values ) ? $values : self::footer_values( $recipient, $context, self::placeholders_in( $text['body'] ) );

		if ( ! empty( $values['dashboard_link'] ) && false !== strpos( $body, (string) $values['dashboard_link'] ) ) {
			$values['dashboard_link'] = '';
		}

		$footer = rtrim( self::fill( $text['body'], $values, self::footer_placeholders(), $eol ), "\r\n\t " );

		if ( '' === $footer ) {
			return $body;
		}

		if ( substr( $body, -strlen( $eol ) ) === $eol ) {
			return $body . $eol . '-- ' . $eol . $footer . $eol;
		}

		return $body . $eol . $eol . '-- ' . $eol . $footer;
	}
```

- [ ] **Step 4: Every email ends with it**

`hand_off()` adds it before the filter, and `welcome_email()` to the invitation it returns to WordPress. Two changes in `includes/class-wpcpm-mail.php`:

In `includes/class-wpcpm-mail.php`, replace:

```php
				'attachments'   => array(),
				'cleanup'       => array(),
				'plain_subject' => false,
			)
		);

		/**
		 * Filter a message before it is sent.
		 *
```

with:

```php
				'attachments'   => array(),
				'cleanup'       => array(),
				'plain_subject' => false,
				'footer_values' => null,
			)
		);

		// Every email the plugin sends ends with the signature and footer, filled for its recipient,
		// and before the filter, so the filter sees the email as it goes out. A builder can hand over
		// the footer's values itself, as a test hands over examples (`footer_values`).
		if ( class_exists( 'WPCPM_Mail_Templates' ) ) {
			$mail['body'] = WPCPM_Mail_Templates::add_footer( (string) $mail['body'], $recipient, $context, is_array( $mail['footer_values'] ) ? $mail['footer_values'] : null );
		}

		/**
		 * Filter a message before it is sent.
		 *
```

In `includes/class-wpcpm-mail.php`, replace:

```php
		// sends it, so each "%" is written twice to come out once: a "%" in a site's name, or in a
		// subject an Administrator wrote, would otherwise be read as a format and fail the send.
		$email['subject'] = str_replace( '%', '%%', $mail['subject'] );
		$email['message'] = $mail['body'];

		// WordPress is about to call `wp_mail()` itself, so this is the only moment at which
		// the log can be told that what follows is an invitation and whose it is.
```

with:

```php
		// sends it, so each "%" is written twice to come out once: a "%" in a site's name, or in a
		// subject an Administrator wrote, would otherwise be read as a format and fail the send.
		$email['subject'] = str_replace( '%', '%%', $mail['subject'] );
		$email['message'] = WPCPM_Mail_Templates::add_footer( $mail['body'], $user, $id );

		// WordPress is about to call `wp_mail()` itself, so this is the only moment at which
		// the log can be told that what follows is an invitation and whose it is.
```

- [ ] **Step 5: Run the tests to verify they pass**

Run, one per command: `php bin/test-mail-templates.php`, `php bin/test-mail.php`, `php bin/test-invitation-text.php`, `php bin/test-institutions-accounts.php`, `php bin/test-sponsors-accounts.php`
Expected: `ALL PASS (61 checks)`; `ALL PASS`; `ALL PASS (39 checks)`, each body the recording's bytes and then the footer, with no second copy of the dashboard's address; `ALL PASS (153 checks)`; `ALL PASS (161 checks)`: the two accounts suites' stand-ins for WordPress record every function the plugin asks of them, and the footer, which uses no `{site_name}`, asks nothing new.

- [ ] **Step 6: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 7: Commit**

```bash
git add includes/mail/class-wpcpm-mail-templates.php includes/class-wpcpm-mail.php bin/test-mail-templates.php bin/test-mail.php bin/test-invitation-text.php
git commit -m "Emails E2: the signature and footer at the end of every plugin email"
```

---

### Task 6: Send me a test: one email, to the presser, marked Test

A test is the email filled with its example values and with "[Test] " before its subject, sent to the account that asked and to nobody else, its footer filled with the examples of the email's audience. It is an ordinary send whose builder says `'test' => true`: `hand_off()` keeps the mark beside the context, and the mail log's capture takes it (`WPCPM_Mail::take_test()`) and writes the rows Test, under the template's own id (ruling E2-6). The footer's test is the catalog's new `footer-test`.

**Files:**
- Modify: `includes/class-wpcpm-mail.php` (`$test`, `take_test()`, `send()`'s docblock, `hand_off()`)
- Modify: `includes/mail/class-wpcpm-mail-capture.php` (`open()` takes the mark, `write()` writes it)
- Modify: `includes/mail/class-wpcpm-mail-catalog.php` (`footer-test`)
- Modify: `includes/mail/class-wpcpm-mail-templates.php` (the tests' section)
- Modify: `bin/test-mail-capture.php` (its stand-in for the mail layer hands the mark too), `bin/test-mail-catalog.php`, `bin/test-mail.php`, `bin/test-mail-templates.php`

**Interfaces:**
- Consumes: `WPCPM_Mail::send( int|WP_User $recipient, string $context, callable $build ): bool`; `WPCPM_Mail_Templates::render()`, `example_values()`, `footer_examples()` (Tasks 2 and 5).
- Produces:
  - `WPCPM_Mail::take_test(): bool`; a builder may return `'test' => true`.
  - `WPCPM_Mail_Capture::open()` stacks `test`; `write()` writes `is_test` for a test or a catalog test id.
  - `WPCPM_Mail_Catalog` `footer-test`: "Test of the signature and footer", area Other, to an Administrator, a test.
  - `WPCPM_Mail_Templates::send_test( string $id, WP_User $to ): bool`, for a template's id or `FOOTER`.

- [ ] **Step 1: Write the failing checks**

Every suite is plain PHP that prints an `ok` or `FAIL` line for each check through its `ck()` helper, ends on a count, and exits non-zero on a failure. The helper and the two closing lines, as a new suite writes them (an existing suite keeps its own, whose arguments come in the same order: the label, what was got, what was wanted):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

The capture's suite stands in for the mail layer with `take_context()` alone; it hands the mark too, and a check pins a test under a template's id. Two changes in `bin/test-mail-capture.php`:

In `bin/test-mail-capture.php`, replace:

```php
}
// Stands in for the real class: only the context passes between the two.
class WPCPM_Mail {
	public static $ctx = '';
	public static function take_context() { $c = self::$ctx; self::$ctx = ''; return $c; }
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
```

with:

```php
}
// Stands in for the real class: only the context passes between the two.
class WPCPM_Mail {
	public static $ctx  = '';
	public static $test = false;
	public static function take_context() { $c = self::$ctx; self::$ctx = ''; return $c; }
	public static function take_test() { $t = self::$test; self::$test = false; return $t; }
}

require __DIR__ . '/stubs/sqlite-wpdb.php';
```

In `bin/test-mail-capture.php`, replace:

```php
send( 'cy@example.test', 'Sample' );
ck( 'a sample invitation is marked Test', (int) rows()[0]['is_test'], 1 );

// No body is stored: every column of every row written in this run, read back from the table.
wipe();
WPCPM_Mail::$ctx = 'call-booked';
```

with:

```php
send( 'cy@example.test', 'Sample' );
ck( 'a sample invitation is marked Test', (int) rows()[0]['is_test'], 1 );

// Send me a test, since 1.122.19: under the template's own id, with the test flag the mail layer hands
// over beside the context, which the next email does not inherit.
wipe();
WPCPM_Mail::$ctx  = 'invite-student';
WPCPM_Mail::$test = true;
send( 'cy@example.test', '[Test] Welcome' );
WPCPM_Mail::$ctx = 'invite-student';
send( 'ada@example.test', 'Welcome' );
ck( 'a test sent under a template\'s own id is marked Test, and the invitation after it is not', array( array_column( rows(), 'template' ), array_map( 'intval', array_column( rows(), 'is_test' ) ) ), array( array( 'invite-student', 'invite-student' ), array( 0, 1 ) ) );

// No body is stored: every column of every row written in this run, read back from the table.
wipe();
WPCPM_Mail::$ctx = 'call-booked';
```

The catalog gains the footer's test, which a literal send call names, so the scan finds one more. Three changes in `bin/test-mail-catalog.php`:

In `bin/test-mail-catalog.php`, replace:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the plugin\'s 43 emails: the 42 contexts it sent before 1.122.19, and the institution invitation for an agreement on file', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 43 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

with:

```php

$emails = WPCPM_Mail_Catalog::emails();

ck( 'the catalog names the plugin\'s 44 emails: the 42 contexts it sent before 1.122.19, the institution invitation for an agreement on file and the test of the signature and footer', count( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), 44 );
ck( 'and reads the same list twice in a request', WPCPM_Mail_Catalog::emails() === $emails, true );

$bad = array();
```

In `bin/test-mail-catalog.php`, replace:

```php
}
ck( 'every entry has a key of letters, digits and hyphens, a label, a known area and a known audience', $bad, array() );

ck( 'the four samples from Settings > Mail are tests, nothing else is', array_keys( array_filter( $emails, function ( $e ) { return $e['test']; } ) ), array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor' ) );

ck( 'an unknown id is "Other email" in the Other area', array( WPCPM_Mail_Catalog::label( 'no-such' ), WPCPM_Mail_Catalog::module_of( 'no-such' ), WPCPM_Mail_Catalog::label( '' ) ), array( 'Other email', 'other', 'Other email' ) );

```

with:

```php
}
ck( 'every entry has a key of letters, digits and hyphens, a label, a known area and a known audience', $bad, array() );

ck( 'the four samples from Settings > Mail and the test of the signature and footer are tests, nothing else is', array_keys( array_filter( $emails, function ( $e ) { return $e['test']; } ) ), array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor', 'footer-test' ) );
ck( 'the test of the signature and footer belongs to no area, and goes to an Administrator', WPCPM_Mail_Catalog::get( 'footer-test' ), array( 'label' => 'Test of the signature and footer', 'module' => 'other', 'audience' => 'administrator', 'test' => true ) );

ck( 'an unknown id is "Other email" in the Other area', array( WPCPM_Mail_Catalog::label( 'no-such' ), WPCPM_Mail_Catalog::module_of( 'no-such' ), WPCPM_Mail_Catalog::label( '' ) ), array( 'Other email', 'other', 'Other email' ) );

```

In `bin/test-mail-catalog.php`, replace:

```php
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 42 of them (it is not reading nothing)', count( $contexts ), 42 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
```

with:

```php
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 43 of them (it is not reading nothing)', count( $contexts ), 43 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
```

`bin/test-mail.php`'s `wp_mail()` takes the mark where the capture takes it. Two changes:

In `bin/test-mail.php`, replace:

```php
		$contents[ $path ] = $present[ $path ] ? (string) file_get_contents( $path ) : '';
	}

	// Taken where the capture takes it, so a context left standing would show on the next message.
	$context = WPCPM_Mail::take_context();

	$GLOBALS['mail'][] = compact( 'to', 'subject', 'body', 'headers', 'attachments', 'present', 'contents', 'context' );

	if ( ! empty( $GLOBALS['mail_fails'] ) ) {
		do_action( 'wp_mail_failed', new WP_Error( 'fail', 'nope', compact( 'to', 'subject' ) ) );
```

with:

```php
		$contents[ $path ] = $present[ $path ] ? (string) file_get_contents( $path ) : '';
	}

	// Taken where the capture takes it, so a context left standing would show on the next message;
	// and so is whether the email is a test, which the capture marks Test.
	$context = WPCPM_Mail::take_context();
	$test    = WPCPM_Mail::take_test();

	$GLOBALS['mail'][] = compact( 'to', 'subject', 'body', 'headers', 'attachments', 'present', 'contents', 'context', 'test' );

	if ( ! empty( $GLOBALS['mail_fails'] ) ) {
		do_action( 'wp_mail_failed', new WP_Error( 'fail', 'nope', compact( 'to', 'subject' ) ) );
```

In `bin/test-mail.php`, replace:

```php
wp_mail( 'someone@example.test', 'Comment awaiting moderation', 'body' );
ck( 'a message the plugin did not send carries no context', end( $GLOBALS['mail'] )['context'], '' );

/* ---- the invitation queue ---------------------------------------------- */

echo "\n=== The invitation queue ===\n";
```

with:

```php
wp_mail( 'someone@example.test', 'Comment awaiting moderation', 'body' );
ck( 'a message the plugin did not send carries no context', end( $GLOBALS['mail'] )['context'], '' );

// A test, since 1.122.19 (Send me a test in WPCredits Program > Tools > Emails), is sent under its
// template's own id and marked as one, which the capture takes beside the context.
$GLOBALS['mail'] = array();
WPCPM_Mail::send( 30, 'invite-student', function () { return array( 'subject' => '[Test] Welcome', 'body' => 'x', 'test' => true ); } );
WPCPM_Mail::send( 30, 'invite-student', function () { return array( 'subject' => 'Welcome', 'body' => 'x' ); } );
ck( 'a builder that says it is a test hands wp_mail() a test under its own context, and the next email is not one', array( array_column( $GLOBALS['mail'], 'context' ), array_column( $GLOBALS['mail'], 'test' ) ), array( array( 'invite-student', 'invite-student' ), array( true, false ) ) );
ck( 'and once taken the mark is gone', WPCPM_Mail::take_test(), false );

/* ---- the invitation queue ---------------------------------------------- */

echo "\n=== The invitation queue ===\n";
```

The registry's suite sends through the real mail layer into a `wp_mail()` that records. Three changes in `bin/test-mail-templates.php`:

In `bin/test-mail-templates.php`, replace:

```php
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function add_action() {}
function add_filter() {}
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

```

with:

```php
function restore_previous_locale() { $GLOBALS['locales'][] = 'restored'; return true; }
function add_action() {}
function add_filter() {}
function apply_filters( $hook, $value ) { return $value; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
// What the mail layer hands WordPress, with the id and the test mark the mail log's capture takes.
$GLOBALS['mail'] = array();
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'context' => WPCPM_Mail::take_context(), 'test' => WPCPM_Mail::take_test() );
	return true;
}
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

```

In `bin/test-mail-templates.php`, replace:

```php
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}

$fails = 0;
$total = 0;
```

with:

```php
class WPCPM_Sponsors_Dashboard {
	public static function page_url() { return 'https://example.test/sponsor-dashboard/'; }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
}

$fails = 0;
$total = 0;
```

In `bin/test-mail-templates.php`, replace:

```php
ck( 'an empty footer is refused, since every email ends with it, and the one saved stays; a footer whose every line is left out adds nothing, not even the "-- " line', array( save_footer( '' ), save_footer( " \n " ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), save_footer( '{dashboard_link}' ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'institution-applied' ) ), array( 'Nothing was saved: the signature and footer is empty.', 'Nothing was saved: the signature and footer is empty.', "Hi.\n\n-- \nKind regards,\nWordPress Credits", true, 'Hi.' ) );
ck( 'Restore the original brings the shipped footer back', array( WPCPM_Mail_Templates::restore( WPCPM_Mail_Templates::FOOTER ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'], false !== strpos( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), 'WordPress Credits, WordPress Education' ) ), array( true, 'shipped', true ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

with:

```php
ck( 'an empty footer is refused, since every email ends with it, and the one saved stays; a footer whose every line is left out adds nothing, not even the "-- " line', array( save_footer( '' ), save_footer( " \n " ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), save_footer( '{dashboard_link}' ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'institution-applied' ) ), array( 'Nothing was saved: the signature and footer is empty.', 'Nothing was saved: the signature and footer is empty.', "Hi.\n\n-- \nKind regards,\nWordPress Credits", true, 'Hi.' ) );
ck( 'Restore the original brings the shipped footer back', array( WPCPM_Mail_Templates::restore( WPCPM_Mail_Templates::FOOTER ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'], false !== strpos( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ), 'WordPress Credits, WordPress Education' ) ), array( true, 'shipped', true ) );

echo "\n=== Send me a test ===\n";

$admin           = new WP_User( 1, 'Ida Admin', 'ida@example.test', array( 'administrator' ) );
$GLOBALS['mail'] = array();
$sent            = WPCPM_Mail_Templates::send_test( 'invite-student', $admin );
$test            = isset( $GLOBALS['mail'][0] ) ? $GLOBALS['mail'][0] : array();

ck( 'a test is one email, to the Administrator who asked, and to nobody else', array( $sent, count( $GLOBALS['mail'] ), $test['to'] ?? null, $test['headers'] ?? null ), array( true, 1, 'ida@example.test', array() ) );
ck( 'with "[Test] " before its subject, under the template\'s own id, marked as a test', array( $test['subject'] ?? null, $test['context'] ?? null, $test['test'] ?? null ), array( '[Test] [WordPress Credits] Welcome to the WordPress Credits Program', 'invite-student', true ) );
ck( 'filled with its examples: the marked example link, the example username, and the footer as a student\'s ends, the Student Report Card\'s address named once, in the body', array( false !== strpos( $test['body'] ?? '', 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), false !== strpos( $test['body'] ?? '', 'Your username is ada.example.' ), substr( $test['body'] ?? '', -strlen( "\r\nYour Student Report Card:\r\nhttps://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n" ) ), substr_count( $test['body'] ?? '', 'https://example.test/student-report-card/' ) ), array( true, true, "\r\nYour Student Report Card:\r\nhttps://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n", 1 ) );

save_mentor( 'Hi <3, {site_name}', $good_body );
$GLOBALS['mail'] = array();
WPCPM_Mail_Templates::send_test( 'invite-mentor', $admin );
ck( 'a test sends the email as it goes out: the edit, its subject kept as typed', array( $GLOBALS['mail'][0]['subject'] ?? null, false !== strpos( $GLOBALS['mail'][0]['body'] ?? '', 'We <3 our mentors' ) ), array( '[Test] Hi <3, WordPress Credits', true ) );
WPCPM_Mail_Templates::restore( 'invite-mentor' );

$GLOBALS['mail'] = array();
WPCPM_Mail_Templates::send_test( WPCPM_Mail_Templates::FOOTER, $admin );
ck( 'the footer\'s test is a short email that ends with it, filled as an Administrator\'s, under the catalog\'s footer-test', array( $GLOBALS['mail'][0]['subject'] ?? null, $GLOBALS['mail'][0]['body'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, $GLOBALS['mail'][0]['test'] ?? null ), array( '[Test] [WordPress Credits] The signature and footer', "This is how every email the site sends ends, filled as an Administrator's own would be.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/administrator-dashboard/\nThis email comes from the WordPress Credits program.", 'footer-test', true ) );

$GLOBALS['mail'] = array();
ck( 'and an email the registry does not hold sends nothing', array( WPCPM_Mail_Templates::send_test( 'call-booked', $admin ), $GLOBALS['mail'] ), array( false, array() ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them to verify they fail**

Run, one per command: `php bin/test-mail-capture.php`, `php bin/test-mail-catalog.php`, `php bin/test-mail.php`, `php bin/test-mail-templates.php`
Expected: `1 FAILURE(S) (38 checks)` (a test under the template's own id is not marked Test); `4 FAILURE(S) (20 checks)` (the catalog has no `footer-test` yet: it counts 43 emails and four tests, has no entry for it to read, and the scan still finds 42); a fatal error, `Call to undefined method WPCPM_Mail::take_test()`; a fatal error at the first test, `Call to undefined method WPCPM_Mail_Templates::send_test()`.

- [ ] **Step 3: The mail layer hands the mark over**

Six changes in `includes/class-wpcpm-mail.php`:

In `includes/class-wpcpm-mail.php`, replace:

```php
	 */
	private static $context = '';

	/**
	 * Hooks.
	 */
```

with:

```php
	 */
	private static $context = '';

	/**
	 * Whether what is currently being sent is a test, which the mail log marks Test: set and taken
	 * with the context (`take_test()`).
	 *
	 * @var bool
	 */
	private static $test = false;

	/**
	 * Hooks.
	 */
```

In `includes/class-wpcpm-mail.php`, replace:

```php
		return $context;
	}

	/*
	 * Sending
	 * --------------------------------------------------------------------
```

with:

```php
		return $context;
	}

	/**
	 * Whether the email being sent is a test, cleared as it is read: the mail log's capture takes it
	 * with the context (`WPCPM_Mail_Capture::open()`).
	 *
	 * @return bool
	 */
	public static function take_test() {
		$test       = self::$test;
		self::$test = false;

		return $test;
	}

	/*
	 * Sending
	 * --------------------------------------------------------------------
```

In `includes/class-wpcpm-mail.php`, replace:

```php
	 * @param string      $context   The email's id in `WPCPM_Mail_Catalog`, e.g. `call-booked`.
	 * @param callable    $build     Receives the recipient as a `WP_User` and returns an
	 *                               array with `subject`, `body`, and optionally `headers`,
	 *                               `attachments`, `cleanup` and `plain_subject` (see
	 *                               `subject()`).
	 * @return bool Whether the message was handed off successfully.
	 */
	public static function send( $recipient, $context, $build ) {
```

with:

```php
	 * @param string      $context   The email's id in `WPCPM_Mail_Catalog`, e.g. `call-booked`.
	 * @param callable    $build     Receives the recipient as a `WP_User` and returns an
	 *                               array with `subject`, `body`, and optionally `headers`,
	 *                               `attachments`, `cleanup`, `plain_subject` (see
	 *                               `subject()`), `test` (the mail log marks it Test) and
	 *                               `footer_values` (the footer's values in place of the
	 *                               recipient's, as a test's examples).
	 * @return bool Whether the message was handed off successfully.
	 */
	public static function send( $recipient, $context, $build ) {
```

In `includes/class-wpcpm-mail.php`, replace:

```php
				'attachments'   => array(),
				'cleanup'       => array(),
				'plain_subject' => false,
				'footer_values' => null,
			)
		);
```

with:

```php
				'attachments'   => array(),
				'cleanup'       => array(),
				'plain_subject' => false,
				'test'          => false,
				'footer_values' => null,
			)
		);
```

In `includes/class-wpcpm-mail.php`, replace:

```php

		if ( '' !== trim( (string) $mail['subject'] ) ) {
			self::$context = sanitize_key( $context );

			$sent = wp_mail(
				$to,
```

with:

```php

		if ( '' !== trim( (string) $mail['subject'] ) ) {
			self::$context = sanitize_key( $context );
			self::$test    = ! empty( $mail['test'] );

			$sent = wp_mail(
				$to,
```

In `includes/class-wpcpm-mail.php`, replace:

```php
			);

			self::$context = '';
		}

		foreach ( (array) $mail['cleanup'] as $path ) {
```

with:

```php
			);

			self::$context = '';
			self::$test    = false;
		}

		foreach ( (array) $mail['cleanup'] as $path ) {
```

- [ ] **Step 4: The capture writes it**

In `includes/mail/class-wpcpm-mail-capture.php`, replace:

```php
	 */
	public static function open( $atts ) {
		$context = class_exists( 'WPCPM_Mail' ) ? (string) WPCPM_Mail::take_context() : '';
		$id      = '' !== $context ? $context : ( '' !== self::$hint ? self::$hint : WPCPM_Mail_Catalog::OTHER );

		self::$hint    = '';
		self::$stack[] = array_merge( array( 'template' => $id ), self::email( $atts ) );

		return $atts;
	}
```

with:

```php
	 */
	public static function open( $atts ) {
		$context = class_exists( 'WPCPM_Mail' ) ? (string) WPCPM_Mail::take_context() : '';
		$test    = class_exists( 'WPCPM_Mail' ) && WPCPM_Mail::take_test();
		$id      = '' !== $context ? $context : ( '' !== self::$hint ? self::$hint : WPCPM_Mail_Catalog::OTHER );

		self::$hint    = '';
		self::$stack[] = array_merge(
			array(
				'template' => $id,
				'test'     => $test,
			),
			self::email( $atts )
		);

		return $atts;
	}
```

In `includes/mail/class-wpcpm-mail-capture.php`, replace:

```php
					'subject'        => $email['subject'],
					'status'         => $status,
					'error'          => $error,
					'is_test'        => null !== $entry && $entry['test'],
				)
			);
		}
```

with:

```php
					'subject'        => $email['subject'],
					'status'         => $status,
					'error'          => $error,
					'is_test'        => ! empty( $email['test'] ) || ( null !== $entry && $entry['test'] ),
				)
			);
		}
```

- [ ] **Step 5: The catalog names the footer's test**

In `includes/mail/class-wpcpm-mail-catalog.php`, replace:

```php
			'wp-comment-moderation'              => array( __( 'Comment waiting for moderation', 'wpcredits-program-manager' ), $w, '' ),
			// The Two Factor plugin's.
			'two-factor-code'                    => array( __( 'Sign-in code', 'wpcredits-program-manager' ), self::MODULE_TWO_FACTOR, '' ),
		);

		$out = array();
```

with:

```php
			'wp-comment-moderation'              => array( __( 'Comment waiting for moderation', 'wpcredits-program-manager' ), $w, '' ),
			// The Two Factor plugin's.
			'two-factor-code'                    => array( __( 'Sign-in code', 'wpcredits-program-manager' ), self::MODULE_TWO_FACTOR, '' ),
			// The Emails tool's test of the signature and footer, which belongs to no area.
			'footer-test'                        => array( __( 'Test of the signature and footer', 'wpcredits-program-manager' ), self::MODULE_OTHER, self::TYPE_ADMIN, true ),
		);

		$out = array();
```

- [ ] **Step 6: The registry sends a test**

After `add_footer()`:

In `includes/mail/class-wpcpm-mail-templates.php`, add before the class's closing brace:

```php
	/*
	 * Tests
	 * --------------------------------------------------------------------
	 */

	/**
	 * Send an email to the Administrator who asked, filled with its example values, as Send me a test
	 * does: "[Test] " before its subject, its footer filled with the examples of its audience, and
	 * marked Test in the mail log. It goes to `$to` and to nobody else: the address is the account's,
	 * never one a form posted.
	 *
	 * The signature and footer's test is a short email that ends with it, logged as the catalog's
	 * `footer-test`; a template's is logged under the template's own id.
	 *
	 * @param string  $id The email's id, or `FOOTER`.
	 * @param WP_User $to The Administrator who pressed Send me a test.
	 * @return bool Whether the email was handed off.
	 */
	public static function send_test( $id, WP_User $to ) {
		$id = (string) $id;

		if ( self::FOOTER === $id ) {
			return WPCPM_Mail::send(
				$to,
				'footer-test',
				self::test_of(
					'footer-test',
					array(
						'subject' => sprintf(
							/* translators: %s: site name. */
							__( '[%s] The signature and footer', 'wpcredits-program-manager' ),
							WPCPM_Mail::site_name()
						),
						'body'    => __( 'This is how every email the site sends ends, filled as an Administrator\'s own would be.', 'wpcredits-program-manager' ),
					)
				)
			);
		}

		if ( null === self::get( $id ) ) {
			return false;
		}

		return WPCPM_Mail::send( $to, $id, self::test_of( $id, null ) );
	}

	/**
	 * The builder a test hands the mail layer: the email as `render()` fills it with its examples, or
	 * the subject and body given, built in the recipient's language, "[Test] " before the subject,
	 * marked as a test, with the footer's examples.
	 *
	 * @param string     $context The email's id in the catalog.
	 * @param array|null $mail    The subject and body to send, or null for the template's own.
	 * @return callable
	 */
	private static function test_of( $context, $mail ) {
		return static function () use ( $context, $mail ) {
			$mail = null === $mail ? self::render( $context, self::example_values( $context ) ) : $mail;

			return array(
				'subject'       => sprintf(
					/* translators: %s: the email's subject. */
					__( '[Test] %s', 'wpcredits-program-manager' ),
					$mail['subject']
				),
				'body'          => $mail['body'],
				'plain_subject' => true,
				'test'          => true,
				'footer_values' => self::footer_examples( $context ),
			);
		};
	}
```

- [ ] **Step 7: Run the tests to verify they pass**

Run, one per command: `php bin/test-mail-capture.php`, `php bin/test-mail-catalog.php`, `php bin/test-mail.php`, `php bin/test-mail-templates.php`, `php bin/test-mail-log.php`
Expected: `ALL PASS (38 checks)`, `ALL PASS (20 checks)`, `ALL PASS`, `ALL PASS (67 checks)`, `ALL PASS (71 checks)`.

- [ ] **Step 8: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 9: Commit**

```bash
git add includes/class-wpcpm-mail.php includes/mail/class-wpcpm-mail-capture.php includes/mail/class-wpcpm-mail-catalog.php includes/mail/class-wpcpm-mail-templates.php bin/test-mail-capture.php bin/test-mail-catalog.php bin/test-mail.php bin/test-mail-templates.php
git commit -m "Emails E2: Send me a test, to the presser alone, logged Test under the template's id"
```

---

### Task 7: The Emails screen: its tabs, the Templates list, the editor and its three buttons

The tool draws three tabs (ruling E2-4): **Templates**, which the tool's own address opens, lists the templates by area and opens each one's editor; **Signature and footer** is the same editor for the footer, without a subject; **Log** is E1's screen, unchanged, at the address every link to it already names. The editor's three buttons are two forms, each an admin-post handler of the tool's asking the capability, then its nonce: **Save**, whose second submit button is **Send me a test** (`name="wpcpm_then" value="test"`, ruling PR-3), so the handler sees what is in the boxes and sends the test only when they read as the editor drew them, and otherwise keeps the typing and sends nothing; and **Restore the original**.

**Files:**
- Modify: `includes/tools/class-wpcpm-emails.php` (the class docblock and constants; `description()`; `boot()`; a dispatcher `render_admin_page()` and the Templates tab, the editor, the footer tab and the two handlers, with the test Save sends, before the Log's code; the Log's method becomes `render_log_tab()`)
- Modify: `assets/css/emails.css` (the state chips, the editor, the preview)
- Create: `bin/test-emails-templates.php`
- Modify: `bin/test-emails-screen.php` (the tool now hooks the editor's two forms; the screen prints the tab bar and the notice, so the suite loads `WPCPM_Flash` and `WPCPM_Screen_Tabs` and core's array form of `add_query_arg()`; the no-table check opens the Log tab, since the bare address opens Templates)
- Modify: `bin/test-submit-guard.php` (the Emails tool prints a `data-wpcpm-confirm` question, Restore's, so it has a row in the table of what draws one)

**Interfaces:**
- Consumes: `WPCPM_Mail_Templates::all()`, `get()`, `render()`, `example_values()`, `examples_of()`, `edit()`, `state()`, `state_label()`, `save()`, `restore()`, `send_test()`, `footer_shipped()`, `footer_placeholders()`, `footer_examples()`, `add_footer()`, `FOOTER`, `SUBJECT_MAX`, `BODY_MAX`, `STATE_*`; `WPCPM_Mail_Catalog::modules()`, `types()`; `WPCPM_Typed_Text::keep()`, `kept()`, `forget()`, `kept_attr()`, `attr_text()`, `typed_text()`, `one_line()`, `same_lines()`; `WPCPM_Flash::set()`, `take()`; `WPCPM_Screen_Tabs::render()`; `WPCPM_Request::key()`, `posted_key()`, `posted_raw()`; core's `sanitize_text_field()`, `sanitize_textarea_field()`.
- Produces:
  - `WPCPM_Emails::TAB_TEMPLATES` (`templates`), `TAB_FOOTER` (`footer`), `TAB_LOG` (`log`), `ACTION_SAVE` (`wpcpm_email_save`), `ACTION_RESTORE` (`wpcpm_email_restore`), `FIELD` (`wpcpm_email`, the template's id or `footer`), `THEN` (`wpcpm_then`, the Save form's second button, which posts `test`), `FLASH` (`emails`, an `array( 'type', 'text' )` notice).
  - `tabs(): array<string,string>`; `current_tab(): string`; `editor_url( string $id ): string`.
  - `handle_save()`, `handle_restore()`, hooked on `admin_post_` + each action; `handle_save()` sends the test when the press names `wpcpm_then=test` (`send_test_of()`, private), only while the boxes read as the editor drew them (`as_drawn()`, private, `WPCPM_Typed_Text::same_lines()` against the edit as stored or the shipped text), and otherwise keeps the typing and says "Nothing was sent: what you typed is not saved yet. Save it first, then send the test."
  - The kept typing's form name: `email:<id>`, for a refused save and for a test of typing not saved yet.

- [ ] **Step 1: Write the failing test**

Drawn and pressed through the real tool, registry, typed-text rules and flash. A press lands on a new request, and `WPCPM_Flash::take()` memoizes per person and channel for the life of a request, so the suite's `press()` hands what the press left to an Administrator of their own for the next page, as bin/test-institution-request.php does.

Create `bin/test-emails-templates.php`:

```php
<?php
/**
 * The Emails tool's Templates and Signature and footer tabs, and the editor's buttons: Save and
 * Send me a test, two buttons of one form, and Restore the original.
 *
 * Drawn and pressed through the real tool, the real registry, the real typed-text rules and the real
 * flash. What each block pins:
 *
 * - **The tabs**: the screen's own address opens Templates, a tab it does not have does too, and
 *   the bar is WordPress's own, Templates, Signature and footer, Log, in that order.
 * - **The Templates tab** lists the templates by area, each with who receives it, what sends it,
 *   where it stands and who edited it last, and an Edit link to its editor; the Invitations alone,
 *   and nothing about what is not there.
 * - **The editor**: the boxes hold the shipped text, then the edit read back as it was typed; the
 *   placeholders say what they mean, the required one marked; the preview is filled with the
 *   examples and ends with the footer; Restore asks first, and is there only for an edit.
 * - **The buttons**: Administrators only, the capability asked before the nonce; a refused save
 *   stores nothing and the editor comes back with what was typed, byte for byte; Send me a test goes
 *   to the presser's own address and to nobody a press names, and only for the text as saved:
 *   pressed with typing not saved yet, it sends nothing and gives the typing back as a refused save
 *   does.
 *
 * Run from the plugin root:  php bin/test-emails-templates.php
 */

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WPCPM_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'WPCPM_PLUGIN_URL', 'https://example.test/wp-content/plugins/wpcredits-program-manager/' );
define( 'WPCPM_VERSION', 'test' );

class WP_User {
	public $ID = 0, $display_name = '', $user_email = '', $user_login = '', $roles = array();
	public function __construct( $id = 0, $name = '', $email = '', array $roles = array() ) {
		$this->ID           = $id;
		$this->display_name = $name;
		$this->user_email   = $email;
		$this->user_login   = strtolower( str_replace( ' ', '.', $name ) );
		$this->roles        = $roles;
	}
	public function exists() { return $this->ID > 0; }
}
class WP_Error {
	private $code, $message;
	public function __construct( $code = '', $message = '' ) { $this->code = $code; $this->message = $message; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
}
class RedirectSignal extends Exception {}
class DieSignal extends Exception {}

$GLOBALS['opts']   = array( 'timezone_string' => 'Europe/Warsaw' );
$GLOBALS['umeta']  = array();
$GLOBALS['hooked'] = array();
$GLOBALS['mail']   = array();
$GLOBALS['can']    = true;
$GLOBALS['uid']    = 1;
$GLOBALS['users']  = array();

function __( $s, $d = null ) { return $s; }
function _n( $a, $b, $n, $d = null ) { return 1 === (int) $n ? $a : $b; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s, $d = null ) { return esc_html( $s ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8', false ); }
function esc_attr__( $s, $d = null ) { return esc_attr( $s ); }
function esc_textarea( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function is_wp_error( $t ) { return $t instanceof WP_Error; }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $v ) { return $v; }
function wp_slash( $v ) { return $v; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function is_email( $s ) { return false !== filter_var( (string) $s, FILTER_VALIDATE_EMAIL ) ? $s : false; }
function user_can( $user, $cap ) { return false; }
function current_user_can( $cap ) { return 'unfiltered_html' !== $cap && (bool) $GLOBALS['can']; }
function get_bloginfo( $k = 'name' ) { return 'WordPress Credits'; }
function wp_login_url( $r = '' ) { return 'https://example.test/wp-login.php'; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Core's two forms: `add_query_arg( $key, $value, $url )` and `add_query_arg( $args, $url )`.
function add_query_arg( ...$args ) {
	$pairs = is_array( $args[0] ) ? $args[0] : array( $args[0] => $args[1] );
	$url   = (string) ( is_array( $args[0] ) ? $args[1] : $args[2] );

	foreach ( $pairs as $k => $v ) {
		$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
	}

	return $url;
}
function wp_nonce_field( $action = -1, $name = '_wpnonce' ) { echo '<input type="hidden" id="' . $name . '" name="' . $name . '" value="nonce:' . $action . '" />'; }
function wp_create_nonce( $action = -1 ) { return 'nonce:' . $action; }
function check_admin_referer( $action = -1, $name = '_wpnonce' ) {
	if ( ! empty( $GLOBALS['nonce_fails'] ) ) {
		throw new DieSignal( 'The link you followed has expired.' );
	}

	$GLOBALS['nonce_checked'] = $action;

	return true;
}
function wp_die( $m = '' ) { throw new DieSignal( (string) $m ); }
function wp_safe_redirect( $to ) { throw new RedirectSignal( (string) $to ); }
function get_current_user_id() { return $GLOBALS['uid']; }
function wp_get_current_user() { return $GLOBALS['users'][ $GLOBALS['uid'] ] ?? new WP_User( 0 ); }
function get_user_by( $field, $value ) { return $GLOBALS['users'][ (int) $value ] ?? false; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ (int) $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ (int) $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ (int) $id ][ $k ] ); return true; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['opts'] ) ? $GLOBALS['opts'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['opts'][ $k ] = $v; return true; }
function wp_timezone() { return new DateTimeZone( (string) get_option( 'timezone_string', 'UTC' ) ); }
function wp_date( $format, $timestamp = null ) { return ( new DateTimeImmutable( '@' . (int) $timestamp ) )->setTimezone( wp_timezone() )->format( $format ); }
function add_action( $h, $cb, $p = 10, $n = 1 ) { $GLOBALS['hooked'][] = $h; return true; }
function add_filter( $h, $cb, $p = 10, $n = 1 ) { return true; }
function apply_filters( $h, $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) {}
// What the mail layer hands WordPress, with the id and the test mark the mail log's capture takes.
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'context' => WPCPM_Mail::take_context(), 'test' => WPCPM_Mail::take_test() );
	return true;
}
require_once __DIR__ . '/stubs/specialchars.php';
require_once __DIR__ . '/stubs/cleaners.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-templates.php';
require_once WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-capture.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-tool.php';
require_once WPCPM_PLUGIN_DIR . 'includes/tools/class-wpcpm-emails.php';

/** The dashboards, each published at an address of its own. */
class WPCPM_Students_Dashboard {
	public static function page_url() { return 'https://example.test/student-report-card/'; }
}
/** As above. */
class WPCPM_Administrators_Dashboard {
	public static function page_url() { return 'https://example.test/administrator-dashboard/'; }
}

$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}

/**
 * Sign an Administrator in, a new one for each press and draw: a flash is read once a request, and
 * this whole run is one request.
 *
 * @return WP_User
 */
function new_administrator() {
	$id                     = count( $GLOBALS['users'] ) + 1;
	$GLOBALS['users'][ $id ] = new WP_User( $id, 'Ida Admin', 'ida' . $id . '@example.test', array( 'administrator' ) );
	$GLOBALS['uid']         = $id;

	return $GLOBALS['users'][ $id ];
}

/** The screen as the signed-in Administrator opens it with these query arguments. */
function screen( array $query ) {
	$_GET     = $query;
	$_REQUEST = $query;

	ob_start();
	( new WPCPM_Emails() )->render_admin_page();

	return (string) ob_get_clean();
}

/**
 * Press one of the editor's buttons with these fields, as the signed-in Administrator: where it went,
 * or what it died with. The page it lands on is a new request, which reads the flash afresh, while
 * `WPCPM_Flash::take()` memoizes per person and channel for the life of a request, and a press takes
 * its typing's channel first (`WPCPM_Typed_Text::forget()`). So, as bin/test-institution-request.php
 * does, an Administrator of their own holding what the press left stands in for the presser on the
 * next page.
 */
function press( $handler, array $fields ) {
	$_POST                    = $fields;
	$GLOBALS['nonce_checked'] = null;
	$went                     = 'returned';

	try {
		( new WPCPM_Emails() )->$handler();
	} catch ( RedirectSignal $e ) {
		$went = 'redirect: ' . $e->getMessage();
	} catch ( DieSignal $e ) {
		$went = 'died: ' . $e->getMessage();
	}

	$left = $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ] ?? array();
	unset( $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ] );
	$next = new_administrator();

	$GLOBALS['umeta'][ $next->ID ][ WPCPM_Flash::META ] = $left;

	return $went;
}

/** The words some markup says, as a person reads them. */
function words_of( $html ) {
	return trim( preg_replace( '/\s+/', ' ', html_entity_decode( preg_replace( '/<[^>]*>/', ' ', (string) $html ), ENT_QUOTES ) ) );
}

/** The value of the input or the text of the text area with this id. */
function box( $html, $id ) {
	if ( preg_match( '#<input type="text" id="' . $id . '"[^>]* value="([^"]*)" />#', $html, $m ) ) {
		return html_entity_decode( $m[1], ENT_QUOTES );
	}

	return preg_match( '#<textarea id="' . $id . '"[^>]*>(.*?)</textarea>#s', $html, $m ) ? html_entity_decode( $m[1], ENT_QUOTES ) : null;
}

$editor_url = 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-student';

echo "=== The tool and its tabs ===\n";

( new WPCPM_Emails() )->boot();
ck( 'the tool hooks its stylesheet and the editor\'s two forms, and nothing else', $GLOBALS['hooked'], array( 'admin_enqueue_scripts', 'admin_post_wpcpm_email_save', 'admin_post_wpcpm_email_restore' ) );

new_administrator();
$home = screen( array( 'page' => 'wpcpm-tool-emails' ) );
preg_match_all( '#<a href="([^"]*)" class="nav-tab( nav-tab-active)?"[^>]*>([^<]*)</a>#', $home, $bar, PREG_SET_ORDER );
ck( 'the screen\'s own address opens Templates, in a bar of three tabs, each at its own address', array_map( function ( $tab ) { return array( $tab[3], $tab[1], '' !== $tab[2] ); }, $bar ), array( array( 'Templates', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', true ), array( 'Signature and footer', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', false ), array( 'Log', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log', false ) ) );
ck( 'and a tab the screen does not have opens Templates too', 1 === preg_match( '#class="nav-tab nav-tab-active" aria-current="page">Templates</a>#', screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'settings' ) ) ), true );

echo "\n=== The Templates tab ===\n";

preg_match_all( '#<h2>([^<]*)</h2>#', $home, $areas );
preg_match_all( '#<tr><td>([^<]*)</td><td>([^<]*)</td><td>([^<]*)</td><td><span class="wpcpm-emails__state wpcpm-emails__state--([a-z-]+)">([^<]*)</span></td><td>([^<]*)</td><td><a href="([^"]*)">Edit<span class="screen-reader-text"> ([^<]*)</span></a></td></tr>#', $home, $rows, PREG_SET_ORDER );
ck( 'the templates are listed by area: the Invitations, and nothing about any other', $areas[1], array( 'Invitations' ) );
ck(
	'each row names the email, who receives it, what sends it, its state and who edited it last, and links its editor by its name',
	array_map( function ( $row ) { return array( html_entity_decode( $row[1], ENT_QUOTES ), $row[2], $row[5], $row[6], $row[7], $row[8] ); }, $rows ),
	array(
		array( 'Invitation: student', 'Student', 'Shipped text', 'Not edited', $editor_url, 'Invitation: student' ),
		array( 'Invitation: mentor', 'Mentor', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-mentor', 'Invitation: mentor' ),
		array( 'Invitation: institution', 'Institution member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-institution', 'Invitation: institution' ),
		array( 'Invitation: institution, agreement on file', 'Institution member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-institution-settled', 'Invitation: institution, agreement on file' ),
		array( 'Invitation: sponsor', 'Sponsor member', 'Shipped text', 'Not edited', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates&edit=invite-sponsor', 'Invitation: sponsor' ),
	)
);
ck( 'and what sends each is the registry\'s sentence', html_entity_decode( $rows[0][3] ?? '', ENT_QUOTES ), WPCPM_Mail_Templates::get( 'invite-student' )['trigger'] );
ck( 'no word on the tab says module, and no em or en dash', array( preg_match_all( '/\bmodules?\b/i', words_of( $home ) ), preg_match( '/\x{2013}|\x{2014}/u', $home ) ), array( 0, 0 ) );
ck( 'an Edit link naming an email the registry does not hold opens the list', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'call-booked' ) ), '<h2>Invitations</h2>' ), true );

echo "\n=== The editor ===\n";

new_administrator();
$editor = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
$entry  = WPCPM_Mail_Templates::get( 'invite-student' );
preg_match_all( '#<form method="post" action="https://example.test/wp-admin/admin-post.php"([^>]*)>(.*?)</form>#s', $editor, $forms, PREG_SET_ORDER );
$posted = array_map(
	function ( $form ) {
		preg_match( '#name="action" value="([^"]*)"#', $form[2], $action );
		preg_match( '#name="wpcpm_email" value="([^"]*)"#', $form[2], $email );
		preg_match( '#name="_wpnonce" value="([^"]*)"#', $form[2], $nonce );

		return array( trim( $form[1] ), $action[1] ?? null, $email[1] ?? null, $nonce[1] ?? null );
	},
	$forms
);

ck( 'it names the email, who receives it and what sends it, and where it stands', array( false !== strpos( $editor, '<h2>Invitation: student</h2>' ), false !== strpos( words_of( $editor ), 'To: Student. ' . $entry['trigger'] ), false !== strpos( words_of( $editor ), 'State: Shipped text' ), false !== strpos( $editor, '>All templates</a>' ) ), array( true, true, true, true ) );
ck( 'its Subject and its Body hold the shipped text, at most 200 and 10,000 characters', array( box( $editor, 'wpcpm-email-subject' ), box( $editor, 'wpcpm-email-body' ), 1 === preg_match( '#name="subject" class="large-text" maxlength="200"#', $editor ), 1 === preg_match( '#name="body" class="large-text code" rows="18" maxlength="10000"#', $editor ) ), array( $entry['subject'], $entry['body'], true, true ) );
preg_match_all( '#<button type="submit" class="([^"]*)"(?: name="([^"]*)" value="([^"]*)")?>([^<]*)</button>#', $forms[0][2] ?? '', $buttons, PREG_SET_ORDER );
ck(
	'Save is one form, posting its action, the email and its nonce, locked at its first press, and Send me a test is its second button, so a test reads what is in the boxes; there is no Restore while nothing is edited',
	array( $posted, array_map( function ( $button ) { return array( $button[1], $button[2] . '=' . $button[3], $button[4] ); }, $buttons ) ),
	array(
		array( array( 'class="wpcpm-emails__form" data-wpcpm-once data-wpcpm-busy="Working"', 'wpcpm_email_save', 'invite-student', 'nonce:wpcpm_email_save' ) ),
		array( array( 'button button-primary', '=', 'Save' ), array( 'button', 'wpcpm_then=test', 'Send me a test' ) ),
	)
);
preg_match_all( '#<tr><td><code>\{([a-z_]+)\}</code>( <span class="wpcpm-emails__required">Required</span>)?</td><td>([^<]*)</td><td>([^<]*)</td></tr>#', $editor, $placeholders, PREG_SET_ORDER );
ck( 'its placeholders are listed with what each means and its example, the set-password link marked required', array_map( function ( $row ) { return array( $row[1], '' !== $row[2] ); }, $placeholders ), array( array( 'site_name', false ), array( 'set_password_link', true ), array( 'username', false ), array( 'login_url', false ), array( 'dashboard_link', false ) ) );
ck( 'each example on one line, the empty ones said to be empty', array( html_entity_decode( $placeholders[1][4] ?? '', ENT_QUOTES ), html_entity_decode( $placeholders[4][4] ?? '', ENT_QUOTES ) ), array( 'To set your password, visit the following address:  https://example.test/wp-login.php?login=ada.example&key=EXAMPLE-KEY-NOT-A-REAL-LINK&action=rp', 'https://example.test/student-report-card/' ) );
preg_match( '#<div class="wpcpm-emails__preview"><p><strong>Subject:</strong> ([^<]*)</p><pre class="wpcpm-emails__text">(.*?)</pre></div>#s', $editor, $preview );
ck( 'the preview is the email filled with the examples, ending with the footer as a student\'s does, the Student Report Card\'s address named once, in the body', array( html_entity_decode( $preview[1] ?? '', ENT_QUOTES ), false !== strpos( $preview[2] ?? '', 'key=EXAMPLE-KEY-NOT-A-REAL-LINK' ), substr( html_entity_decode( $preview[2] ?? '', ENT_QUOTES ), -strlen( "https://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n" ) ), substr_count( $preview[2] ?? '', 'https://example.test/student-report-card/' ) ), array( '[WordPress Credits] Welcome to the WordPress Credits Program', true, "https://example.test/student-report-card/\r\n\r\n-- \r\nWordPress Credits, WordPress Education\r\nThis email comes from the WordPress Credits program.\r\n", 1 ) );

echo "\n=== Who may press ===\n";

$GLOBALS['can'] = false;
$refused        = array();
foreach ( array( 'handle_save' => array(), 'handle_save, Send me a test' => array( 'wpcpm_then' => 'test' ), 'handle_restore' => array() ) as $pressed => $more ) {
	$refused[ $pressed ] = array( press( strtok( $pressed, ',' ), array( 'wpcpm_email' => 'invite-student', 'subject' => 'x', 'body' => 'y' ) + $more ), $GLOBALS['nonce_checked'] );
}
$GLOBALS['can'] = true;
ck( 'without the right to manage the program, each button refuses before it reads anything, its nonce included', $refused, array_fill_keys( array( 'handle_save', 'handle_save, Send me a test', 'handle_restore' ), array( 'died: You do not have permission to manage the program.', null ) ) );
ck( 'and nothing was stored or sent', array( array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ), $GLOBALS['mail'] ), array( false, array() ) );
$GLOBALS['nonce_fails'] = true;
ck( 'a nonce that fails is answered by WordPress\'s own page', press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => 'x', 'body' => '{set_password_link}' ) ), 'died: The link you followed has expired.' );
$GLOBALS['nonce_fails'] = false;
ck( 'and each form checks its own nonce', array( press( 'handle_save', array( 'wpcpm_email' => 'nope' ) ), $GLOBALS['nonce_checked'], press( 'handle_restore', array( 'wpcpm_email' => 'nope' ) ), $GLOBALS['nonce_checked'] ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', 'wpcpm_email_save', 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', 'wpcpm_email_restore' ) );

echo "\n=== Save ===\n";

// A refused save stores nothing, and the editor comes back with every byte that was typed.
new_administrator();
$typed = array(
	'subject' => 'Welcome <3 & "friends" &amp; co',
	'body'    => "Hi.\r\nSet a password, then sign in.\r\nWe <3 you & your \"team\" &amp; all.",
);
ck( 'a save without the set-password link goes back to the editor', press( 'handle_save', array( 'wpcpm_email' => 'invite-student' ) + $typed ), 'redirect: ' . $editor_url );
ck( 'and stores nothing', array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ), false );
$again = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'which says why, and holds what was typed, byte for byte, each line break as its box counts it', array( false !== strpos( $again, '<div class="notice notice-error is-dismissible"><p>Nothing was saved: this email needs {set_password_link}.</p></div>' ), box( $again, 'wpcpm-email-subject' ), box( $again, 'wpcpm-email-body' ) ), array( true, $typed['subject'], str_replace( "\r\n", "\n", $typed['body'] ) ) );
ck( 'once: the typing is taken as the editor draws it', isset( $GLOBALS['umeta'][ $GLOBALS['uid'] ][ WPCPM_Flash::META ][ WPCPM_Typed_Text::KEPT_CHANNEL . 'email:invite-student' ] ), false );

new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => 'Hi {studnet_name}', 'body' => '{set_password_link}' ) );
ck( 'a misspelled placeholder is refused by name, and nothing is stored', array( false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'Nothing was saved: {studnet_name} is not a placeholder of this email.' ), array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ) ), array( true, false ) );

new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => 'Your link: {set_password_link}', 'body' => "Hi.\r\n\r\n{set_password_link}" ) );
ck( 'the set-password link in the subject is refused, saying it goes in the body, and nothing is stored', array( false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'Nothing was saved: {set_password_link} goes in the body, never in the subject.' ), array_key_exists( WPCPM_Mail_Templates::OPTION, $GLOBALS['opts'] ) ), array( true, false ) );

new_administrator();
$good = array(
	'subject' => '[{site_name}] Welcome <3',
	'body'    => "Hi.\r\n\r\n{set_password_link}\r\n\r\nWe <3 you & your team.",
);
ck( 'a good save goes back to the editor', press( 'handle_save', array( 'wpcpm_email' => 'invite-student' ) + $good ), 'redirect: ' . $editor_url );
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-student']['at'] = strtotime( '2026-10-10 12:05:00 UTC' );
$saved = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
preg_match_all( '#<form method="post" action="https://example.test/wp-admin/admin-post.php"([^>]*)>#', $saved, $saved_forms );
ck( 'which says so, and holds the edit as it was typed', array( false !== strpos( $saved, '<div class="notice notice-success is-dismissible"><p>Saved. The email is sent as you wrote it from now on.</p></div>' ), box( $saved, 'wpcpm-email-subject' ), box( $saved, 'wpcpm-email-body' ), false !== strpos( words_of( $saved ), 'State: Edited' ) ), array( true, $good['subject'], str_replace( "\r\n", "\n", $good['body'] ), true ) );
ck( 'Restore the original is there now, a form of its own, and asks first', array_map( 'trim', $saved_forms[1] ), array( 'class="wpcpm-emails__form" data-wpcpm-once data-wpcpm-busy="Working"', 'class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Restoring" data-wpcpm-confirm="Restore the original? Your edit is dropped, and the shipped text is sent from now on."' ) );
ck( 'and under it, folded, the shipped text', 1 === preg_match( '#<details class="wpcpm-emails__shipped"><summary>The shipped text</summary><p><strong>Subject:</strong> \[\{site_name\}\] Welcome to the WordPress Credits Program</p>#', $saved ), true );
preg_match( '#<tr><td>Invitation: student</td>.*?</tr>#s', screen( array( 'page' => 'wpcpm-tool-emails' ) ), $row );
ck( 'the Templates tab says the email is Edited, by whom, and when, in the site\'s time zone', array( false !== strpos( $row[0] ?? '', '>Edited</span>' ), false !== strpos( $row[0] ?? '', '<td>Ida Admin, 10 Oct 2026, 14:05</td>' ) ), array( true, true ) );

// An update changed the shipped text since: the edit is still sent, the editor says so, and the
// shipped text is open beside it.
new_administrator();
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-student']['shipped'] = md5( 'an older shipped text' );
$changed = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'when the shipped text has changed since the edit, the editor says the edit is still sent and that saving it again clears this, and opens the shipped text', array( false !== strpos( $changed, 'An update changed the shipped text since this edit was saved.' ), false !== strpos( $changed, 'Saving your edit again clears this.' ), false !== strpos( $changed, '<details class="wpcpm-emails__shipped" open>' ), false !== strpos( words_of( $changed ), 'State: Edited, and the shipped text has changed since' ) ), array( true, true, true, true ) );
new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student' ) + $good );
ck( 'and saving the edit again clears it: the edit now carries the shipped text\'s fingerprint, and is Edited', WPCPM_Mail_Templates::state( 'invite-student' )['state'], 'edited' );

// An edit set aside: the state says why, and the editor that the shipped text is sent.
new_administrator();
$GLOBALS['opts'][ WPCPM_Mail_Templates::OPTION ]['templates']['invite-student']['body'] = 'Hi {topic}.';
$aside = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'an edit set aside says why, and that the shipped text is sent', false !== strpos( $aside, '<div class="notice notice-warning inline"><p>Edit set aside: it uses {topic}, which this email no longer has. The shipped text is sent until the edit is saved again in a form this email can use.</p></div>' ), true );

echo "\n=== Restore the original ===\n";

new_administrator();
ck( 'Restore the original drops the edit and goes back to the editor', array( press( 'handle_restore', array( 'wpcpm_email' => 'invite-student' ) ), WPCPM_Mail_Templates::edit( 'invite-student' ) ), array( 'redirect: ' . $editor_url, null ) );
$restored = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'which says so, the boxes hold the shipped text again, and Restore is gone', array( false !== strpos( $restored, 'The original is back: the shipped text is sent from now on.' ), box( $restored, 'wpcpm-email-subject' ), substr_count( $restored, 'wpcpm_email_restore' ) ), array( true, $entry['subject'], 0 ) );
new_administrator();
press( 'handle_restore', array( 'wpcpm_email' => 'invite-student' ) );
ck( 'and a second press says there was nothing to restore', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), '<div class="notice notice-info is-dismissible"><p>There was no edit to restore: the shipped text is already the one sent.</p></div>' ), true );

new_administrator();
press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'subject' => $entry['subject'], 'body' => str_replace( "\n", "\r\n", $entry['body'] ) ) );
ck( 'a save of the shipped text as it was drawn stores nothing, and says so', array( WPCPM_Mail_Templates::edit( 'invite-student' ), false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'Saved. It reads as the shipped text, so it is sent as shipped.' ) ), array( null, true ) );

echo "\n=== Send me a test ===\n";

// The Save form's second button, pressed with the boxes as the editor drew them: here the shipped
// text, since nothing is edited.
$me              = new_administrator();
$GLOBALS['mail'] = array();
$as_drawn        = array(
	'wpcpm_email' => 'invite-student',
	'wpcpm_then'  => 'test',
	'subject'     => $entry['subject'],
	'body'        => str_replace( "\n", "\r\n", $entry['body'] ),
);
$went            = press( 'handle_save', $as_drawn + array( 'to' => 'someone@example.test', 'email' => 'someone@example.test', 'cc' => 'other@example.test', 'user_id' => '2' ) );
ck( 'a test goes to the Administrator who pressed and to nobody the press names: one email, to the presser\'s own address, with no header, and nothing is saved', array( $went, count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'] ?? null, $GLOBALS['mail'][0]['headers'] ?? null, WPCPM_Mail_Templates::edit( 'invite-student' ) ), array( 'redirect: ' . $editor_url, 1, $me->user_email, array(), null ) );
ck( 'marked [Test], under the template\'s own id, as a test', array( $GLOBALS['mail'][0]['subject'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, $GLOBALS['mail'][0]['test'] ?? null ), array( '[Test] [WordPress Credits] Welcome to the WordPress Credits Program', 'invite-student', true ) );
ck( 'and the editor says where it went', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) ), 'The test is on its way to your own address, ' . $me->user_email . '.' ), true );

// Pressed with typing not saved yet, it sends nothing, since the test would show what no recipient
// gets, and gives the typing back as a refused save does.
new_administrator();
$GLOBALS['mail'] = array();
$unsaved         = array(
	'subject' => 'Welcome <3, "new" & improved',
	'body'    => "Hi.\r\n\r\n{set_password_link}\r\n\r\nNot saved & not sent.",
);
ck( 'pressed with typing not saved yet, it sends nothing, stores nothing and goes back to the editor', array( press( 'handle_save', array( 'wpcpm_email' => 'invite-student', 'wpcpm_then' => 'test' ) + $unsaved ), $GLOBALS['mail'], WPCPM_Mail_Templates::edit( 'invite-student' ) ), array( 'redirect: ' . $editor_url, array(), null ) );
$unsent = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'templates', 'edit' => 'invite-student' ) );
ck( 'which says so, and holds what was typed, byte for byte, each line break as its box counts it', array( false !== strpos( $unsent, '<div class="notice notice-error is-dismissible"><p>Nothing was sent: what you typed is not saved yet. Save it first, then send the test.</p></div>' ), box( $unsent, 'wpcpm-email-subject' ), box( $unsent, 'wpcpm-email-body' ) ), array( true, $unsaved['subject'], str_replace( "\r\n", "\n", $unsaved['body'] ) ) );

new_administrator();
$GLOBALS['mail'] = array();
ck( 'a press naming an email the screen does not edit goes to the list, saying so, and sends nothing', array( press( 'handle_save', array( 'wpcpm_email' => 'call-booked', 'wpcpm_then' => 'test' ) ), $GLOBALS['mail'], false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails' ) ), 'Nothing was sent: the site has no such email.' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates', array(), true ) );

echo "\n=== The Signature and footer tab ===\n";

new_administrator();
$footer_tab = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) );
preg_match_all( '#<tr><td><code>\{([a-z_]+)\}</code>#', $footer_tab, $footer_placeholders );
ck( 'it is the editor without a Subject, its box holding the shipped footer', array( false !== strpos( $footer_tab, '<h2>Signature and footer</h2>' ), box( $footer_tab, 'wpcpm-email-subject' ), box( $footer_tab, 'wpcpm-email-body' ), false !== strpos( $footer_tab, 'All templates' ) ), array( true, null, WPCPM_Mail_Templates::footer_shipped(), false ) );
ck( 'it says where the block goes, and lists its three placeholders', array( false !== strpos( words_of( $footer_tab ), 'Every email the plugin sends ends with this block, after a line holding "-- ".' ), $footer_placeholders[1] ), array( true, array( 'site_name', 'dashboard_link', 'program_contact' ) ) );
ck( 'its preview ends a line of text with the footer', false !== strpos( $footer_tab, "The text of the email.\n\n-- \nWordPress Credits, WordPress Education\nhttps://example.test/administrator-dashboard/\nThis email comes from the WordPress Credits program.</pre>" ), true );

new_administrator();
ck( 'a save of the footer goes back to its tab, and every email ends with it', array( press( 'handle_save', array( 'wpcpm_email' => 'footer', 'body' => "Kind regards,\r\n{site_name}" ) ), WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'call-booked' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', "Hi.\n\n-- \nKind regards,\nWordPress Credits" ) );
ck( 'and the tab says so', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) ), 'Saved. Every email ends with the signature and footer as you wrote it from now on.' ), true );

$me              = new_administrator();
$GLOBALS['mail'] = array();
press( 'handle_save', array( 'wpcpm_email' => 'footer', 'wpcpm_then' => 'test', 'body' => "Kind regards,\r\n{site_name}" ) );
ck( 'its test, pressed with the box as drawn, is one email to the presser, under the footer\'s test, ending with the edited footer', array( count( $GLOBALS['mail'] ), $GLOBALS['mail'][0]['to'] ?? null, $GLOBALS['mail'][0]['context'] ?? null, substr( $GLOBALS['mail'][0]['body'] ?? '', -strlen( "-- \nKind regards,\nWordPress Credits" ) ) ), array( 1, $me->user_email, 'footer-test', "-- \nKind regards,\nWordPress Credits" ) );
new_administrator();
$GLOBALS['mail'] = array();
ck( 'and pressed with typing not saved yet, it sends nothing and gives the typing back, as on a template\'s editor', array( press( 'handle_save', array( 'wpcpm_email' => 'footer', 'wpcpm_then' => 'test', 'body' => "Kind regards,\r\nThe team" ) ), $GLOBALS['mail'], box( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) ), 'wpcpm-email-body' ) ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', array(), "Kind regards,\nThe team" ) );

new_administrator();
ck( 'and Restore the original brings the shipped footer back', array( press( 'handle_restore', array( 'wpcpm_email' => 'footer' ) ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'] ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', 'shipped' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run it to verify it fails**

Run: `php bin/test-emails-templates.php`
Expected: the first check fails (the tool hooks its stylesheet alone), then a fatal error, `Class "WPCPM_Mail_Log" not found`: today's screen is the Log and nothing else, whatever the address asks for.

- [ ] **Step 3: The tool: its docblock, its constants, its description and its hooks**

In `includes/tools/class-wpcpm-emails.php`, replace:

```php
/**
 * The site's email: in this version the Log, every email the site sent in the last 30 days.
 *
 * The tool draws the screen and its status line and nothing more. The recording, the table's
 * creation and upgrade, the daily cleanup and the privacy tools' hooks boot from the plugin's
 * bootstrap (`WPCPM_Mail_Log::init()`), so a site that filters this tool out of `wpcpm_tools` still
 * records its email.
 *
 * Every link to the Log names its tab (`tab=log`), which the screen's filter form carries too, so
 * the links keep working once the screen has other tabs beside it.
 */
class WPCPM_Emails extends WPCPM_Tool {

	/** The Log's tab. */
	const TAB_LOG = 'log';
```

with:

```php
/**
 * The site's email, in three tabs: Templates, the plugin's emails as text an Administrator edits;
 * Signature and footer, the one block every plugin email ends with; and Log, every email the site
 * sent in the last 30 days.
 *
 * The tool draws the screen, its status line and the editor's three buttons (Save and Send me a
 * test, two buttons of one form, and Restore the original) and nothing more. What an email says,
 * and the edits, are the template registry's (`WPCPM_Mail_Templates`), which every email reads as
 * it is sent, so a site that filters this tool out of `wpcpm_tools` loses the screen and keeps
 * sending its emails as they were last saved. The recording, the table's creation and upgrade, the
 * daily cleanup and the privacy tools' hooks boot from the plugin's bootstrap
 * (`WPCPM_Mail_Log::init()`), so the log is kept all the same.
 *
 * Every link to the Log names its tab (`tab=log`), which the screen's filter form carries too; the
 * screen's own address opens Templates.
 */
class WPCPM_Emails extends WPCPM_Tool {

	/** The Templates tab, which the screen's own address opens. */
	const TAB_TEMPLATES = 'templates';

	/** The Signature and footer tab. */
	const TAB_FOOTER = 'footer';

	/** The Log's tab. */
	const TAB_LOG = 'log';

	/** Admin-post action of the editor's Save. */
	const ACTION_SAVE = 'wpcpm_email_save';

	/** Admin-post action of Restore the original. */
	const ACTION_RESTORE = 'wpcpm_email_restore';

	/** The field each of the two forms names its email in: a template's id, or the footer's. */
	const FIELD = 'wpcpm_email';

	/**
	 * The name of the Save form's second button, Send me a test, which posts `test` under it: the
	 * test is the Save form's own, so whatever is in the boxes reaches the handler.
	 */
	const THEN = 'wpcpm_then';

	/** The flash channel the buttons leave their notice on. */
	const FLASH = 'emails';
```

In `includes/tools/class-wpcpm-emails.php`, replace:

```php
	public function description() {
		return __( 'Every email the site sent in the last 30 days: who it went to, which email, and whether WordPress handed it to the mail server.', 'wpcredits-program-manager' );
	}
```

with:

```php
	public function description() {
		return __( 'The plugin\'s emails you can edit, by area. One you have not edited is sent as the plugin ships it. Every email the plugin sends ends with the signature and footer. The Log holds every email the site sent in the last 30 days.', 'wpcredits-program-manager' );
	}
```

In `includes/tools/class-wpcpm-emails.php`, replace the method `boot()`, its docblock included, with:

```php
	/**
	 * Hooks: the screen's stylesheet and the editor's two forms, Save (with Send me a test) and
	 * Restore the original. Nothing else: the log boots on its own, and every email reads the
	 * registry as it is sent.
	 */
	public function boot() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_RESTORE, array( $this, 'handle_restore' ) );
	}
```

- [ ] **Step 4: The tabs, the Templates list, the editor and the three buttons; the Log becomes a tab**

The screen's method becomes the dispatcher, with the Templates tab, the editor and the handlers after it, and the Log's code, unchanged, becomes `render_log_tab()`, which no longer opens or closes the screen's `wrap`:

In `includes/tools/class-wpcpm-emails.php`, replace:

```php
	/**
	 * The screen: the filters, a form of their own, then the count, the table and what its main
	 * status promises.
	 *
	 * The filter form is a GET form closed before the table, and its search box is its own field
	 * (`s`) rather than the table's `search_box()`, which core draws only while there is a search or
	 * a row: so the box never disappears, and no nonce or referer of the table's joins the filters'
	 * address. The table's page links carry the filters, which are the address's.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		echo '<div class="wrap wpcpm-wrap wpcpm-emails">';
		printf( '<h1>%s</h1>', esc_html( $this->label() ) );

		if ( ! WPCPM_Mail_Log::exists() ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'The email log is not ready yet. It is created the next time a page of the site loads; if this stays, tell whoever looks after the site.', 'wpcredits-program-manager' ) );
			echo '</div>';

			return;
		}
```

with:

```php
	/**
	 * The screen's tabs, in the bar's order.
	 *
	 * @return array<string,string> Slug to label.
	 */
	public static function tabs() {
		return array(
			self::TAB_TEMPLATES => __( 'Templates', 'wpcredits-program-manager' ),
			self::TAB_FOOTER    => __( 'Signature and footer', 'wpcredits-program-manager' ),
			self::TAB_LOG       => __( 'Log', 'wpcredits-program-manager' ),
		);
	}

	/**
	 * The tab the address asks for: one of `tabs()`, Templates for none or one the screen does not have.
	 *
	 * @return string
	 */
	public static function current_tab() {
		$tab = WPCPM_Request::key( 'tab' );

		return isset( self::tabs()[ $tab ] ) ? $tab : self::TAB_TEMPLATES;
	}

	/**
	 * Where an email is edited: a template's editor on the Templates tab, or the Signature and footer
	 * tab.
	 *
	 * @param string $id A template's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @return string
	 */
	public function editor_url( $id ) {
		if ( WPCPM_Mail_Templates::FOOTER === (string) $id ) {
			return add_query_arg( 'tab', self::TAB_FOOTER, $this->admin_url() );
		}

		return add_query_arg(
			array(
				'tab'  => self::TAB_TEMPLATES,
				'edit' => (string) $id,
			),
			$this->admin_url()
		);
	}

	/**
	 * The screen: its heading, the notice a button left, the tab bar, and the tab asked for.
	 */
	public function render_admin_page() {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		$tab = self::current_tab();

		echo '<div class="wrap wpcpm-wrap wpcpm-emails">';
		printf( '<h1>%s</h1>', esc_html( $this->label() ) );

		$notice = WPCPM_Flash::take( self::FLASH );

		if ( is_array( $notice ) && isset( $notice['type'], $notice['text'] ) ) {
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( (string) $notice['type'] ), esc_html( (string) $notice['text'] ) );
		}

		WPCPM_Screen_Tabs::render( $this->page_slug(), self::tabs(), $tab );

		if ( self::TAB_FOOTER === $tab ) {
			$this->render_footer_tab();
		} elseif ( self::TAB_LOG === $tab ) {
			$this->render_log_tab();
		} else {
			$this->render_templates_tab();
		}

		echo '</div>';
	}

	/**
	 * The Templates tab: the editor of the email the address names, or the list of every template,
	 * grouped by area, each with who receives it, what sends it, where it stands and who edited it
	 * last.
	 */
	private function render_templates_tab() {
		$edit = WPCPM_Request::key( 'edit' );

		if ( '' !== $edit && null !== WPCPM_Mail_Templates::get( $edit ) ) {
			$this->render_editor( $edit );

			return;
		}

		printf( '<p class="description">%s</p>', esc_html__( 'The plugin\'s emails you can edit, by area. One you have not edited is sent as the plugin ships it. Every email the plugin sends ends with the signature and footer.', 'wpcredits-program-manager' ) );

		$by_area = array();

		foreach ( WPCPM_Mail_Templates::all() as $id => $entry ) {
			$by_area[ $entry['module'] ][ $id ] = $entry;
		}

		foreach ( WPCPM_Mail_Catalog::modules() as $module => $area ) {
			if ( empty( $by_area[ $module ] ) ) {
				continue;
			}

			printf( '<h2>%s</h2>', esc_html( $area ) );
			echo '<table class="widefat striped wpcpm-emails__templates"><thead><tr>';

			foreach ( array( __( 'Email', 'wpcredits-program-manager' ), __( 'Who receives it', 'wpcredits-program-manager' ), __( 'What sends it', 'wpcredits-program-manager' ), __( 'State', 'wpcredits-program-manager' ), __( 'Last edited', 'wpcredits-program-manager' ) ) as $column ) {
				printf( '<th scope="col">%s</th>', esc_html( $column ) );
			}

			printf( '<th scope="col"><span class="screen-reader-text">%s</span></th></tr></thead><tbody>', esc_html__( 'Edit', 'wpcredits-program-manager' ) );

			foreach ( $by_area[ $module ] as $id => $entry ) {
				$state = WPCPM_Mail_Templates::state( $id );

				printf(
					'<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td><a href="%6$s">%7$s<span class="screen-reader-text"> %8$s</span></a></td></tr>',
					esc_html( $entry['label'] ),
					esc_html( self::audience_label( $entry['audience'] ) ),
					esc_html( $entry['trigger'] ),
					self::state_chip( $state ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
					esc_html( self::edited_by( $state ) ),
					esc_url( $this->editor_url( $id ) ),
					esc_html__( 'Edit', 'wpcredits-program-manager' ),
					esc_html( $entry['label'] )
				);
			}

			echo '</tbody></table>';
		}
	}

	/**
	 * The Signature and footer tab: the one block every plugin email ends with, in the editor every
	 * template has.
	 */
	private function render_footer_tab() {
		$this->render_editor( WPCPM_Mail_Templates::FOOTER );
	}

	/**
	 * Who receives an email, as the Templates tab says it.
	 *
	 * @param string $audience A recipient type, or '' for an email to several kinds of people.
	 * @return string
	 */
	private static function audience_label( $audience ) {
		$types = WPCPM_Mail_Catalog::types();

		return isset( $types[ $audience ] ) ? $types[ $audience ] : __( 'The people it concerns', 'wpcredits-program-manager' );
	}

	/**
	 * An email's state as a chip: its words, marked for the eye by its kind.
	 *
	 * @param array $state What `WPCPM_Mail_Templates::state()` answered.
	 * @return string The chip's markup, escaped.
	 */
	private static function state_chip( array $state ) {
		return sprintf(
			'<span class="wpcpm-emails__state wpcpm-emails__state--%1$s">%2$s</span>',
			esc_attr( (string) $state['state'] ),
			esc_html( WPCPM_Mail_Templates::state_label( $state ) )
		);
	}

	/**
	 * Who saved an email's edit and when, in the site's time zone, or that it was never edited.
	 *
	 * @param array $state What `WPCPM_Mail_Templates::state()` answered.
	 * @return string
	 */
	private static function edited_by( array $state ) {
		if ( WPCPM_Mail_Templates::STATE_SHIPPED === $state['state'] ) {
			return __( 'Not edited', 'wpcredits-program-manager' );
		}

		$user = $state['by'] ? get_user_by( 'id', (int) $state['by'] ) : false;
		$name = $user instanceof WP_User ? (string) $user->display_name : __( 'An account since removed', 'wpcredits-program-manager' );

		return sprintf(
			/* translators: 1: who saved the edit, 2: when. */
			__( '%1$s, %2$s', 'wpcredits-program-manager' ),
			$name,
			wp_date( 'j M Y, H:i', (int) $state['at'] )
		);
	}

	/**
	 * One email's editor: what it is and where it stands, a Subject (none for the footer) and a Body
	 * with Save and Send me a test, its placeholders, a preview filled with the example values and
	 * ending with the footer, then, for an edited email, Restore the original and the shipped text.
	 *
	 * Send me a test is the Save form's second submit button, so the handler reads the boxes and
	 * sends the test only while they hold what the editor drew; the form's busy word, "Working",
	 * holds for either button.
	 *
	 * The boxes hold, in this order: what a refused save kept of the typing, the edit as stored, read
	 * back as it was typed, or the shipped text.
	 *
	 * @param string $id A template's id, or `WPCPM_Mail_Templates::FOOTER`.
	 */
	private function render_editor( $id ) {
		$footer = WPCPM_Mail_Templates::FOOTER === $id;
		$entry  = $footer ? null : WPCPM_Mail_Templates::get( $id );
		$label  = $footer ? __( 'Signature and footer', 'wpcredits-program-manager' ) : $entry['label'];
		$state  = WPCPM_Mail_Templates::state( $id );
		$edit   = WPCPM_Mail_Templates::edit( $id );
		$kept   = WPCPM_Typed_Text::kept( self::kept_form( $id ) );
		$ships  = $footer ? array(
			'subject' => '',
			'body'    => WPCPM_Mail_Templates::footer_shipped(),
		) : array(
			'subject' => $entry['subject'],
			'body'    => $entry['body'],
		);

		if ( isset( $kept['body'] ) && is_string( $kept['body'] ) ) {
			$subject = isset( $kept['subject'] ) && is_string( $kept['subject'] ) ? WPCPM_Typed_Text::kept_attr( $kept['subject'] ) : '';
			$body    = $kept['body'];
		} elseif ( null !== $edit ) {
			$subject = WPCPM_Typed_Text::attr_text( $edit['subject'] );
			$body    = WPCPM_Typed_Text::typed_text( $edit['body'] );
		} else {
			$subject = WPCPM_Typed_Text::kept_attr( $ships['subject'] );
			$body    = $ships['body'];
		}

		if ( ! $footer ) {
			printf( '<p><a href="%1$s">%2$s</a></p>', esc_url( add_query_arg( 'tab', self::TAB_TEMPLATES, $this->admin_url() ) ), esc_html__( 'All templates', 'wpcredits-program-manager' ) );
		}

		echo '<div class="wpcpm-card wpcpm-emails__editor">';
		printf( '<h2>%s</h2>', esc_html( $label ) );

		if ( $footer ) {
			printf( '<p class="description">%s</p>', esc_html__( 'Every email the plugin sends ends with this block, after a line holding "-- ". It is not added to the emails WordPress and the Two Factor plugin send themselves.', 'wpcredits-program-manager' ) );
		} else {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: 1: who receives the email, such as Student, 2: what sends it, a sentence. */
						__( 'To: %1$s. %2$s', 'wpcredits-program-manager' ),
						self::audience_label( $entry['audience'] ),
						$entry['trigger']
					)
				)
			);
		}

		printf(
			'<p>%1$s %2$s</p>',
			esc_html__( 'State:', 'wpcredits-program-manager' ),
			self::state_chip( $state ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped parts.
		);

		if ( WPCPM_Mail_Templates::STATE_SET_ASIDE === $state['state'] ) {
			printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html( $state['reason'] . ' ' . __( 'The shipped text is sent until the edit is saved again in a form this email can use.', 'wpcredits-program-manager' ) ) );
		} elseif ( WPCPM_Mail_Templates::STATE_CHANGED === $state['state'] ) {
			printf( '<div class="notice notice-info inline"><p>%s</p></div>', esc_html__( 'An update changed the shipped text since this edit was saved. Your edit is still the one sent; the new shipped text is under the buttons, to compare. Saving your edit again clears this.', 'wpcredits-program-manager' ) );
		}

		printf(
			'<form method="post" action="%1$s" class="wpcpm-emails__form" data-wpcpm-once data-wpcpm-busy="%2$s">',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr__( 'Working', 'wpcredits-program-manager' )
		);
		wp_nonce_field( self::ACTION_SAVE );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( self::ACTION_SAVE ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD ), esc_attr( $id ) );

		if ( ! $footer ) {
			printf(
				'<p><label for="wpcpm-email-subject">%1$s</label><br /><input type="text" id="wpcpm-email-subject" name="subject" class="large-text" maxlength="%2$d" value="%3$s" /></p>',
				esc_html__( 'Subject', 'wpcredits-program-manager' ),
				(int) WPCPM_Mail_Templates::SUBJECT_MAX,
				esc_attr( $subject )
			);
		}

		printf(
			'<p><label for="wpcpm-email-body">%1$s</label><br /><textarea id="wpcpm-email-body" name="body" class="large-text code" rows="%2$d" maxlength="%3$d">%4$s</textarea></p>',
			esc_html( $footer ? __( 'Signature and footer', 'wpcredits-program-manager' ) : __( 'Body', 'wpcredits-program-manager' ) ),
			$footer ? 5 : 18,
			(int) WPCPM_Mail_Templates::BODY_MAX,
			esc_textarea( $body )
		);

		// Enter in the Subject presses the first button, Save.
		printf(
			'<p><button type="submit" class="button button-primary">%1$s</button> <button type="submit" class="button" name="%2$s" value="test">%3$s</button></p>',
			esc_html__( 'Save', 'wpcredits-program-manager' ),
			esc_attr( self::THEN ),
			esc_html__( 'Send me a test', 'wpcredits-program-manager' )
		);
		printf( '<p class="description">%s</p>', esc_html__( 'Send me a test sends the email as it is saved, filled with the example values and marked [Test], to your own address and to nobody else. Save what you typed first: pressed with typing not saved yet, it sends nothing.', 'wpcredits-program-manager' ) );
		echo '</form>';

		$this->render_placeholders( $footer ? WPCPM_Mail_Templates::footer_placeholders() : $entry['placeholders'] );
		$this->render_preview( $id );

		if ( null !== $edit ) {
			echo '<div class="wpcpm-emails__buttons">';
			$this->render_button( $id, self::ACTION_RESTORE, __( 'Restore the original', 'wpcredits-program-manager' ), __( 'Restoring', 'wpcredits-program-manager' ), __( 'Restore the original? Your edit is dropped, and the shipped text is sent from now on.', 'wpcredits-program-manager' ) );
			echo '</div>';

			printf( '<details class="wpcpm-emails__shipped"%s><summary>%s</summary>', WPCPM_Mail_Templates::STATE_CHANGED === $state['state'] ? ' open' : '', esc_html__( 'The shipped text', 'wpcredits-program-manager' ) );

			if ( ! $footer ) {
				printf( '<p><strong>%1$s</strong> %2$s</p>', esc_html__( 'Subject:', 'wpcredits-program-manager' ), esc_html( $ships['subject'] ) );
			}

			printf( '<pre class="wpcpm-emails__text">%s</pre></details>', esc_html( $ships['body'] ) );
		}

		echo '</div>';
	}

	/**
	 * The placeholders an email may use, each with what it means and its example, the required ones
	 * marked.
	 *
	 * @param array $placeholders Name to what the registry holds for each.
	 */
	private function render_placeholders( array $placeholders ) {
		printf( '<h3>%s</h3>', esc_html__( 'Placeholders', 'wpcredits-program-manager' ) );
		printf( '<p class="description">%s</p>', esc_html__( 'Each one is replaced by its value as the email is sent. A sentence whose placeholders are all empty is left out, and so is a line left with nothing on it, with a line ending in a colon above it. A sentence ends at a full stop followed by a space; a question or exclamation mark does not end one.', 'wpcredits-program-manager' ) );
		echo '<table class="widefat striped wpcpm-emails__placeholders"><thead><tr>';

		foreach ( array( __( 'Placeholder', 'wpcredits-program-manager' ), __( 'What it means', 'wpcredits-program-manager' ), __( 'Example', 'wpcredits-program-manager' ) ) as $column ) {
			printf( '<th scope="col">%s</th>', esc_html( $column ) );
		}

		echo '</tr></thead><tbody>';

		$examples = WPCPM_Mail_Templates::examples_of( $placeholders );

		foreach ( $placeholders as $name => $about ) {
			printf(
				'<tr><td><code>{%1$s}</code>%2$s</td><td>%3$s</td><td>%4$s</td></tr>',
				esc_html( $name ),
				empty( $about['required'] ) ? '' : ' <span class="wpcpm-emails__required">' . esc_html__( 'Required', 'wpcredits-program-manager' ) . '</span>',
				esc_html( (string) $about['means'] ),
				'' === $examples[ $name ] ? esc_html__( 'Empty here', 'wpcredits-program-manager' ) : esc_html( WPCPM_Typed_Text::one_line( $examples[ $name ] ) )
			);
		}

		echo '</tbody></table>';
	}

	/**
	 * The email as a recipient of its audience would read it: the text that goes out, filled with
	 * the example values, and the footer. For the footer, a short line above it.
	 *
	 * @param string $id A template's id, or `WPCPM_Mail_Templates::FOOTER`.
	 */
	private function render_preview( $id ) {
		if ( WPCPM_Mail_Templates::FOOTER === $id ) {
			$context = 'footer-test';
			$mail    = array(
				'subject' => '',
				'body'    => __( 'The text of the email.', 'wpcredits-program-manager' ),
			);
		} else {
			$context = $id;
			$mail    = WPCPM_Mail_Templates::render( $id, WPCPM_Mail_Templates::example_values( $id ) );
		}

		$body = WPCPM_Mail_Templates::add_footer( $mail['body'], null, $context, WPCPM_Mail_Templates::footer_examples( $context ) );

		printf( '<h3>%s</h3>', esc_html__( 'Preview', 'wpcredits-program-manager' ) );
		printf( '<p class="description">%s</p>', esc_html__( 'As it is sent now, with the example values and the signature and footer.', 'wpcredits-program-manager' ) );
		echo '<div class="wpcpm-emails__preview">';

		if ( '' !== $mail['subject'] ) {
			printf( '<p><strong>%1$s</strong> %2$s</p>', esc_html__( 'Subject:', 'wpcredits-program-manager' ), esc_html( $mail['subject'] ) );
		}

		printf( '<pre class="wpcpm-emails__text">%s</pre></div>', esc_html( $body ) );
	}

	/**
	 * A button of the editor's that is a form of its own posting the email's id, Restore the
	 * original's: locked at its first press, saying it is busy, and, given a question, asking it
	 * first.
	 *
	 * @param string $id       The email's id.
	 * @param string $action   The admin-post action.
	 * @param string $label    The button's words.
	 * @param string $busy     What it says while the press is on its way.
	 * @param string $question A question to ask first, or '' for none.
	 */
	private function render_button( $id, $action, $label, $busy, $question ) {
		printf(
			'<form method="post" action="%1$s" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="%2$s"%3$s>',
			esc_url( admin_url( 'admin-post.php' ) ),
			esc_attr( $busy ),
			'' === $question ? '' : ' data-wpcpm-confirm="' . esc_attr( $question ) . '"'
		);
		// The nonce in a field with an id of its own: `wp_nonce_field()` names its field's id after
		// the field, which two forms on one screen would give to two elements.
		printf( '<input type="hidden" id="%1$s" name="_wpnonce" value="%2$s" />', esc_attr( $action . '-nonce' ), esc_attr( wp_create_nonce( $action ) ) );
		printf( '<input type="hidden" name="action" value="%s" />', esc_attr( $action ) );
		printf( '<input type="hidden" name="%1$s" value="%2$s" />', esc_attr( self::FIELD ), esc_attr( $id ) );
		printf( '<button type="submit" class="button">%s</button>', esc_html( $label ) );
		echo '</form> ';
	}

	/**
	 * The name a refused save of an email keeps its typing under (`WPCPM_Typed_Text::keep()`).
	 *
	 * @param string $id The email's id.
	 * @return string
	 */
	private static function kept_form( $id ) {
		return 'email:' . $id;
	}

	/**
	 * The email a press names, when it is one the screen edits: a template of the registry's, or the
	 * footer; '' for anything else.
	 *
	 * @return string
	 */
	private static function posted_email() {
		$id = WPCPM_Request::posted_key( self::FIELD );

		return WPCPM_Mail_Templates::FOOTER === $id || null !== WPCPM_Mail_Templates::get( $id ) ? $id : '';
	}

	/**
	 * What each of the two forms asks first: the right to manage the program, then the nonce.
	 *
	 * @param string $action The button's admin-post action.
	 */
	private static function verify( $action ) {
		if ( ! current_user_can( WPCPM_Roles::CAP_MANAGE ) ) {
			wp_die( esc_html__( 'You do not have permission to manage the program.', 'wpcredits-program-manager' ), 403 );
		}

		check_admin_referer( $action );
	}

	/**
	 * Queue the notice a press leaves, and go back to the email's editor, or to the Templates tab for
	 * an email the screen does not edit.
	 *
	 * @param string $id   The email, or ''.
	 * @param string $type The notice's kind: success, info or error.
	 * @param string $text Its sentence.
	 */
	private function back( $id, $type, $text ) {
		WPCPM_Flash::set(
			self::FLASH,
			array(
				'type' => $type,
				'text' => $text,
			)
		);

		wp_safe_redirect( '' === $id ? add_query_arg( 'tab', self::TAB_TEMPLATES, $this->admin_url() ) : $this->editor_url( $id ) );
		exit;
	}

	/**
	 * Save: store the edit, or refuse it, store nothing, and keep what was typed for the editor to
	 * draw again, as every form of the plugin keeps a refused typing. Pressed with its second
	 * button, Send me a test (`THEN`), it stores nothing and sends the test instead
	 * (`send_test_of()`).
	 */
	public function handle_save() {
		self::verify( self::ACTION_SAVE );

		$id   = self::posted_email();
		$test = 'test' === WPCPM_Request::posted_key( self::THEN );

		if ( '' === $id ) {
			$this->back( '', 'error', $test ? __( 'Nothing was sent: the site has no such email.', 'wpcredits-program-manager' ) : __( 'Nothing was saved: the site has no such email.', 'wpcredits-program-manager' ) );
		}

		$footer  = WPCPM_Mail_Templates::FOOTER === $id;
		$subject = $footer ? null : WPCPM_Request::posted_raw( 'subject' );
		$body    = WPCPM_Request::posted_raw( 'body' );

		WPCPM_Typed_Text::forget( self::kept_form( $id ) );

		if ( $test ) {
			$this->send_test_of( $id, $subject, $body );
		}

		$saved = WPCPM_Mail_Templates::save( $id, $subject, $body, get_current_user_id() );

		if ( is_wp_error( $saved ) ) {
			self::keep_typing( $id, $subject, $body );

			$this->back( $id, 'error', $saved->get_error_message() );
		}

		if ( WPCPM_Mail_Templates::STATE_SHIPPED === WPCPM_Mail_Templates::state( $id )['state'] ) {
			$this->back( $id, 'success', __( 'Saved. It reads as the shipped text, so it is sent as shipped.', 'wpcredits-program-manager' ) );
		}

		$this->back( $id, 'success', $footer ? __( 'Saved. Every email ends with the signature and footer as you wrote it from now on.', 'wpcredits-program-manager' ) : __( 'Saved. The email is sent as you wrote it from now on.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Restore the original: drop the edit, after the question the button asks.
	 */
	public function handle_restore() {
		self::verify( self::ACTION_RESTORE );

		$id = self::posted_email();

		if ( '' === $id ) {
			$this->back( '', 'error', __( 'Nothing was restored: the site has no such email.', 'wpcredits-program-manager' ) );
		}

		if ( WPCPM_Mail_Templates::restore( $id ) ) {
			$this->back( $id, 'success', __( 'The original is back: the shipped text is sent from now on.', 'wpcredits-program-manager' ) );
		}

		$this->back( $id, 'info', __( 'There was no edit to restore: the shipped text is already the one sent.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Send me a test, the Save form's second button: the email as it is saved, filled with its
	 * example values, to the address of the Administrator who pressed and to nobody else, whatever
	 * the press posted. Only while the boxes read as the editor drew them: typing not saved yet is
	 * kept, as a refused save keeps it, and nothing is sent, since the test would show what no
	 * recipient gets.
	 *
	 * @param string      $id      The email's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @param string|null $subject The subject as posted, unslashed and not cleaned; null for the footer.
	 * @param string      $body    The body as posted, unslashed and not cleaned.
	 */
	private function send_test_of( $id, $subject, $body ) {
		if ( ! self::as_drawn( $id, $subject, $body ) ) {
			self::keep_typing( $id, $subject, $body );

			$this->back( $id, 'error', __( 'Nothing was sent: what you typed is not saved yet. Save it first, then send the test.', 'wpcredits-program-manager' ) );
		}

		$me = wp_get_current_user();

		if ( $me instanceof WP_User && $me->exists() && WPCPM_Mail_Templates::send_test( $id, $me ) ) {
			$this->back(
				$id,
				'success',
				sprintf(
					/* translators: %s: the Administrator's own email address. */
					__( 'The test is on its way to your own address, %s.', 'wpcredits-program-manager' ),
					$me->user_email
				)
			);
		}

		$this->back( $id, 'error', __( 'The test could not be sent: whatever handles mail on this site refused it, so the real email would not arrive either.', 'wpcredits-program-manager' ) );
	}

	/**
	 * Whether the boxes were posted as the editor drew them, when no typing was kept: the edit as
	 * stored, or the shipped text while there is none. What was posted is cleaned as a save would
	 * clean it, and the two are compared with each line break read as one
	 * (`WPCPM_Typed_Text::same_lines()`), the white space at either end let go.
	 *
	 * @param string      $id      The email's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @param string|null $subject The subject as posted; null for the footer.
	 * @param string      $body    The body as posted.
	 * @return bool
	 */
	private static function as_drawn( $id, $subject, $body ) {
		$edit = WPCPM_Mail_Templates::edit( $id );

		if ( null !== $edit ) {
			$drawn = array(
				'subject' => $edit['subject'],
				'body'    => $edit['body'],
			);
		} elseif ( WPCPM_Mail_Templates::FOOTER === $id ) {
			$drawn = array(
				'subject' => '',
				'body'    => WPCPM_Mail_Templates::footer_shipped(),
			);
		} else {
			$entry = WPCPM_Mail_Templates::get( $id );
			$drawn = array(
				'subject' => $entry['subject'],
				'body'    => $entry['body'],
			);
		}

		return WPCPM_Typed_Text::same_lines( sanitize_text_field( (string) $subject ), trim( $drawn['subject'] ) )
			&& WPCPM_Typed_Text::same_lines( sanitize_textarea_field( (string) $body ), trim( $drawn['body'] ) );
	}

	/**
	 * Keep what was typed in the editor's boxes for the editor to draw again, once, as every form of
	 * the plugin keeps a refused typing.
	 *
	 * @param string      $id      The email's id, or `WPCPM_Mail_Templates::FOOTER`.
	 * @param string|null $subject The subject as posted; null for the footer.
	 * @param string      $body    The body as posted.
	 */
	private static function keep_typing( $id, $subject, $body ) {
		WPCPM_Typed_Text::keep(
			self::kept_form( $id ),
			array(
				'subject' => (string) $subject,
				'body'    => $body,
			),
			array(
				'subject' => WPCPM_Mail_Templates::SUBJECT_MAX,
				'body'    => WPCPM_Mail_Templates::BODY_MAX,
			)
		);
	}

	/**
	 * The Log tab: the filters, a form of their own, then the count, the table and what its main
	 * status promises.
	 *
	 * The filter form is a GET form closed before the table, and its search box is its own field
	 * (`s`) rather than the table's `search_box()`, which core draws only while there is a search or
	 * a row: so the box never disappears, and no nonce or referer of the table's joins the filters'
	 * address. The table's page links carry the filters, which are the address's.
	 */
	private function render_log_tab() {
		if ( ! WPCPM_Mail_Log::exists() ) {
			printf( '<div class="notice notice-warning"><p>%s</p></div>', esc_html__( 'The email log is not ready yet. It is created the next time a page of the site loads; if this stays, tell whoever looks after the site.', 'wpcredits-program-manager' ) );

			return;
		}
```

and at the end of that method:

In `includes/tools/class-wpcpm-emails.php`, replace:

```php
		// Under the table: what the status most rows carry promises.
		printf( '<p class="description">%s</p>', esc_html__( '"Handed to the mail server" means the site passed the email on, not that it reached the inbox.', 'wpcredits-program-manager' ) );
		echo '</div>';
	}
```

with:

```php
		// Under the table: what the status most rows carry promises.
		printf( '<p class="description">%s</p>', esc_html__( '"Handed to the mail server" means the site passed the email on, not that it reached the inbox.', 'wpcredits-program-manager' ) );
	}
```

- [ ] **Step 5: The stylesheet**

In `assets/css/emails.css`, replace:

```css
/* The Emails screen: the Log's filters, its Email field, and the Test mark. Loaded on that screen
   only (WPCPM_Emails::enqueue_assets()), in wp-admin's own colors. */

/* The filters, as one row that wraps: each its label over its field, so a phone stacks them. */
.wpcpm-emails__filters {
```

with:

```css
/* The Emails screen: the Templates tab and the editor, the Log's filters, its Email field, and the
   Test mark. Loaded on that screen only (WPCPM_Emails::enqueue_assets()), in wp-admin's own colors. */

/* The filters, as one row that wraps: each its label over its field, so a phone stacks them. */
.wpcpm-emails__filters {
```

In `assets/css/emails.css`, replace:

```css
	white-space: nowrap;
	width: 1px;
}
```

with:

```css
	white-space: nowrap;
	width: 1px;
}

/* The Templates tab: each email's state as a chip. The words say the state; the color repeats them. */
.wpcpm-emails__templates td,
.wpcpm-emails__placeholders td {
	overflow-wrap: anywhere;
}

.wpcpm-emails__state {
	background: #f0f0f1;
	border-radius: 2px;
	display: inline-block;
	padding: 0 0.4em;
}

.wpcpm-emails__state--edited,
.wpcpm-emails__state--changed {
	background: #e7f5fe;
}

.wpcpm-emails__state--set-aside {
	background: #fcf9e8;
}

/* The editor: its parts spaced as the card's sections, the required placeholders by weight. */
.wpcpm-emails__editor h3 {
	margin-top: 1.5em;
}

.wpcpm-emails__required {
	font-weight: 600;
}

.wpcpm-emails__buttons {
	margin: 1.5em 0 1em;
}

/* The preview and the shipped text: the email's own lines, wrapped at the card's width. */
.wpcpm-emails__preview {
	background: #f6f7f7;
	border: 1px solid #dcdcde;
	padding: 0 1em;
}

.wpcpm-emails__text {
	font-family: Consolas, Monaco, monospace;
	max-width: 100%;
	overflow-wrap: anywhere;
	white-space: pre-wrap;
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php bin/test-emails-templates.php`
Expected: `ALL PASS (50 checks)`.

- [ ] **Step 7: Bring the Log's suite and the submit guard's to the tabbed tool, and see them pass**

Run, one per command: `php bin/test-emails-screen.php`, `php bin/test-submit-guard.php`
Expected: the first fails its check of the tool's hooks, then stops at `Class "WPCPM_Flash" not found`, which the screen now reads its notice from; the second ends `1 FAILURE(S)`: "every file under includes/ that prints a data-wpcpm-confirm mark has a row in the surface table".

`bin/test-emails-screen.php` gets five changes:

In `bin/test-emails-screen.php`, replace:

```php
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function add_query_arg( $k, $v, $u ) { return $u . ( false === strpos( $u, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
// Core's, as `selected()` writes it: a string comparison, so 7 and '7' are one value.
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
```

with:

```php
function wp_check_invalid_utf8( $s, $strip = false ) { return (string) $s; }
function number_format_i18n( $n, $d = 0 ) { return (string) $n; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
// Core's two forms: `add_query_arg( $key, $value, $url )`, the Log's own links, and
// `add_query_arg( $args, $url )`, the tab bar's.
function add_query_arg( ...$args ) {
	if ( is_array( $args[0] ) ) {
		$url = (string) $args[1];

		foreach ( $args[0] as $k => $v ) {
			$url .= ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v;
		}

		return $url;
	}

	return $args[2] . ( false === strpos( $args[2], '?' ) ? '?' : '&' ) . $args[0] . '=' . $args[1];
}
// The flash the screen reads a press's notice from: one Administrator's, in user meta.
$GLOBALS['umeta'] = array();
function get_current_user_id() { return 1; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['umeta'][ $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['umeta'][ $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['umeta'][ $id ][ $k ] ); return true; }
function wp_slash( $v ) { return $v; }
// Core's, as `selected()` writes it: a string comparison, so 7 and '7' are one value.
function selected( $a, $b, $echo = true ) {
	$out = ( (string) $a === (string) $b ) ? " selected='selected'" : '';
```

In `bin/test-emails-screen.php`, replace:

```php
$GLOBALS['wpdb']->create_mail_log();

require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
```

with:

```php
$GLOBALS['wpdb']->create_mail_log();

require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-screen-tabs.php';
require WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-dashboards.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-catalog.php';
require WPCPM_PLUGIN_DIR . 'includes/mail/class-wpcpm-mail-log.php';
```

In `bin/test-emails-screen.php`, replace:

```php
ck( 'the tool is Emails at wpcpm-tool-emails, always ready', array( $tool->id(), $tool->label(), $tool->page_slug(), $tool->is_ready() ), array( 'emails', 'Emails', 'wpcpm-tool-emails', true ) );

$tool->boot();
ck( 'it boots its stylesheet\'s hook and nothing else: the log boots from the plugin\'s bootstrap', array_map( function ( $h ) { return $h[0]; }, $GLOBALS['hooked'] ), array( 'admin_enqueue_scripts' ) );
$tool->enqueue_assets( 'index.php' );
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-emails' );
ck( 'its stylesheet loads on its screen alone', $GLOBALS['styles'], array( 'wpcpm-emails' => WPCPM_PLUGIN_URL . 'assets/css/emails.css' ) );
```

with:

```php
ck( 'the tool is Emails at wpcpm-tool-emails, always ready', array( $tool->id(), $tool->label(), $tool->page_slug(), $tool->is_ready() ), array( 'emails', 'Emails', 'wpcpm-tool-emails', true ) );

$tool->boot();
ck( 'it boots its stylesheet\'s hook and the editor\'s two forms, and nothing else: the log boots from the plugin\'s bootstrap', array_map( function ( $h ) { return $h[0]; }, $GLOBALS['hooked'] ), array( 'admin_enqueue_scripts', 'admin_post_wpcpm_email_save', 'admin_post_wpcpm_email_restore' ) );
$tool->enqueue_assets( 'index.php' );
$tool->enqueue_assets( 'wpcredits-program_page_wpcpm-tool-emails' );
ck( 'its stylesheet loads on its screen alone', $GLOBALS['styles'], array( 'wpcpm-emails' => WPCPM_PLUGIN_URL . 'assets/css/emails.css' ) );
```

In `bin/test-emails-screen.php`, replace:

```php
$form  = preg_match( '#<form method="get" class="wpcpm-emails__filters">(.*?)</form>#s', $html, $found ) ? $found[1] : '';
$words = words_of( $html );

ck( 'the row shows its time in the site\'s zone, its address, its person linked, its area and its email', array( false !== strpos( $html, '10 Oct 2026, 01:30' ), false !== strpos( $html, 'ada@example.test' ), false !== strpos( $html, '<a href="https://example.test/wp-admin/user-edit.php?user_id=7">Ada Lin</a>' ), false !== strpos( $html, 'Mentor calls' ), false !== strpos( $html, 'Call booked' ) ), array( true, true, true, true, true ) );
ck( 'the count is shown', false !== strpos( $html, '1 email matches.' ), true );
ck( 'the screen says, under the table, what Handed to the mail server means', strpos( $html, 'means the site passed the email on, not that it reached the inbox' ) > strpos( $html, '</table>' ), true );
```

with:

```php
$form  = preg_match( '#<form method="get" class="wpcpm-emails__filters">(.*?)</form>#s', $html, $found ) ? $found[1] : '';
$words = words_of( $html );

ck( 'the Log is the tab shown, between Templates and Signature and footer and its own link', array( 1 === preg_match( '#<a href="https://example.test/wp-admin/admin.php\?page=wpcpm-tool-emails&tab=log" class="nav-tab nav-tab-active" aria-current="page">Log</a>#', $html ), 1 === preg_match( '#>Templates</a><a [^>]*>Signature and footer</a><a [^>]*>Log</a></nav>#', $html ) ), array( true, true ) );
ck( 'the row shows its time in the site\'s zone, its address, its person linked, its area and its email', array( false !== strpos( $html, '10 Oct 2026, 01:30' ), false !== strpos( $html, 'ada@example.test' ), false !== strpos( $html, '<a href="https://example.test/wp-admin/user-edit.php?user_id=7">Ada Lin</a>' ), false !== strpos( $html, 'Mentor calls' ), false !== strpos( $html, 'Call booked' ) ), array( true, true, true, true, true ) );
ck( 'the count is shown', false !== strpos( $html, '1 email matches.' ), true );
ck( 'the screen says, under the table, what Handed to the mail server means', strpos( $html, 'means the site passed the email on, not that it reached the inbox' ) > strpos( $html, '</table>' ), true );
```

In `bin/test-emails-screen.php`, replace:

```php
// No table: the screen says the log is not ready, and draws no table.
$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
$missing = screen( array( 'page' => 'wpcpm-tool-emails' ) );
ck( 'with no table the screen says the log is not ready yet, and draws no table', array( false !== strpos( $missing, 'The email log is not ready yet' ), strpos( $missing, '<table' ) ), array( true, false ) );

// Only Administrators.
```

with:

```php
// No table: the screen says the log is not ready, and draws no table.
$GLOBALS['wpdb']->drop_mail_log();
WPCPM_Mail_Log::reset();
$missing = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'log' ) );
ck( 'with no table the screen says the log is not ready yet, and draws no table', array( false !== strpos( $missing, 'The email log is not ready yet' ), strpos( $missing, '<table' ) ), array( true, false ) );

// Only Administrators.
```

`bin/test-submit-guard.php` refuses a file that prints a `data-wpcpm-confirm` question without saying which loader brings the script that asks it; the Emails tool is an admin screen:

In `bin/test-submit-guard.php`, replace:

```php
	// fragment, which the Mentor Report Card and the Institution's student page insert after they load, is
	// read only and prints none (bin/test-report-images.php draws it).
	'includes/modules/class-wpcpm-student-report-form.php'               => array( 'student-report-card' ),
	'includes/tools/class-wpcpm-track-builder-screen.php'                => array( 'admin' ),
	'includes/tools/class-wpcpm-track-editor-screen.php'                 => array( 'admin' ),
);
```

with:

```php
	// fragment, which the Mentor Report Card and the Institution's student page insert after they load, is
	// read only and prints none (bin/test-report-images.php draws it).
	'includes/modules/class-wpcpm-student-report-form.php'               => array( 'student-report-card' ),
	'includes/tools/class-wpcpm-emails.php'                              => array( 'admin' ),
	'includes/tools/class-wpcpm-track-builder-screen.php'                => array( 'admin' ),
	'includes/tools/class-wpcpm-track-editor-screen.php'                 => array( 'admin' ),
);
```

Run, one per command: `php bin/test-emails-screen.php`, `php bin/test-submit-guard.php`, `php bin/test-institution-panel.php`
Expected: `ALL PASS (47 checks)`, with the Email field run by node as before (or skipped, with a note, where node is not on the path); `ALL PASS`; `ALL PASS` (the stylesheet's new rules hold no `!important`, no comment between selectors and no scroll box).

- [ ] **Step 8: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 9: Commit**

```bash
git add includes/tools/class-wpcpm-emails.php assets/css/emails.css bin/test-emails-templates.php bin/test-emails-screen.php bin/test-submit-guard.php
git commit -m "Emails E2: Templates, Signature and footer and Log tabs; the editor with Save, Send me a test and Restore the original"
```

---

### Task 8: Contact address in emails

The address `{program_contact}` names: the Emails tool's one setting, `mail_contact`, in the tool's own scope (`tool:emails`), drawn by the tool's Settings section at the top of the Signature and footer tab, beside the footer that names it. It ships empty, so the footer leaves its sentence out until the Administrators name the mailbox. Anything typed that is not an address keeps the one saved, and the tab says why. A tool's Settings section is wherever `WPCPM_Tool::settings_url()` says (the tool's screen, as before, for every other tool), so a Save comes back there and the Connection tab's first line, which now names four tools, links it.

**Files:**
- Modify: `includes/class-wpcpm-settings.php` (the default, the scope, the save's rule)
- Modify: `includes/tools/class-wpcpm-tool.php` (`settings_url()`)
- Modify: `includes/class-wpcpm-settings-screen.php` (`TOOLS_WITH_SETTINGS`, `return_url()`, `render_tools_lede()`)
- Modify: `includes/tools/class-wpcpm-emails.php` (the footer tab draws the Settings section; `settings_keys()`, `settings_url()`, `settings_intro()`, `render_settings_rows()`)
- Modify: `bin/test-settings.php`, `bin/test-admin-menu.php`, `bin/test-emails-templates.php`

**Interfaces:**
- Consumes: `WPCPM_Tool::render_settings()`; `WPCPM_Settings_Rows::text_row()`; `WPCPM_Flash::set()`, `take()`.
- Produces:
  - `WPCPM_Settings::defaults()['mail_contact']` (`''`), `scopes()['tool:emails']` (`array( 'mail_contact' )`); a save keeps an address, clears for a blank, and keeps the stored one for anything else, setting the flash `emails-contact-refused`.
  - `WPCPM_Tool::settings_url(): string` (`admin_url()` by default); `WPCPM_Emails::settings_url()` is the Signature and footer tab.
  - `WPCPM_Settings_Screen::TOOLS_WITH_SETTINGS` gains `emails`.

- [ ] **Step 1: Write the failing checks**

Every suite is plain PHP that prints an `ok` or `FAIL` line for each check through its `ck()` helper, ends on a count, and exits non-zero on a failure. The helper and the two closing lines, as a new suite writes them (an existing suite keeps its own, whose arguments come in the same order: the label, what was got, what was wanted):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

`bin/test-settings.php` holds every setting to a scope, a round trip, a control on one form and a Save that comes back to its form; eight changes bring the Emails tool's setting into each:

In `bin/test-settings.php`, replace:

```php
	'sponsor_notify'                => 'probe-one@example.org,probe-two@example.org',
	'logo_max_kb'                   => '2048',
	'offer_low_stock'               => '250',
);

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();
```

with:

```php
	'sponsor_notify'                => 'probe-one@example.org,probe-two@example.org',
	'logo_max_kb'                   => '2048',
	'offer_low_stock'               => '250',
	// The Emails tool's contact address (1.122.19).
	'mail_contact'                  => 'programs@example.test',
);

$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = WPCPM_Settings::defaults();
```

In `bin/test-settings.php`, replace:

```php
$channel = WPCPM_Settings::save( array( 'checker_slack_channel' => ' #mentors ' ), 'tool:mentor-status-checker' );
ck( 'the channel the message names is saved as typed, trimmed', $channel['checker_slack_channel'], '#mentors' );

// Only providers the plugin can actually talk to.
$saved = WPCPM_Settings::save( array( 'handbook_provider' => 'nonsense' ) );
ck( 'an unknown provider falls back to none', array( $saved['handbook_provider'] ), array( '' ) );
```

with:

```php
$channel = WPCPM_Settings::save( array( 'checker_slack_channel' => ' #mentors ' ), 'tool:mentor-status-checker' );
ck( 'the channel the message names is saved as typed, trimmed', $channel['checker_slack_channel'], '#mentors' );

// The Emails tool's Contact address in emails, which every email's footer names (1.122.19): an
// address, or blank for none; anything else keeps the one saved, and the Signature and footer tab
// is told why.
$contact = WPCPM_Settings::save( array( 'mail_contact' => ' programs@example.test ' ), 'tool:emails' );
$typo    = WPCPM_Settings::save( array( 'mail_contact' => 'programs@' ), 'tool:emails' );
ck( 'the contact address in emails is saved trimmed, and one that is not an address is refused: the one saved stays, and the tab is told',
    array( $contact['mail_contact'], $typo['mail_contact'], WPCPM_Flash::take( 'emails-contact-refused' ) ), array( 'programs@example.test', 'programs@example.test', true ) );
ck( 'and a blank one clears it', WPCPM_Settings::save( array( 'mail_contact' => '' ), 'tool:emails' )['mail_contact'], '' );

// Only providers the plugin can actually talk to.
$saved = WPCPM_Settings::save( array( 'handbook_provider' => 'nonsense' ) );
ck( 'an unknown provider falls back to none', array( $saved['handbook_provider'] ), array( '' ) );
```

In `bin/test-settings.php`, replace:

```php

ck( 'every setting is in exactly one scope, so a setting added without one, or put in two, fails here', $not_once, array() );
ck( 'and no scope names a setting that does not exist', array_values( array_diff( $in_scope, array_keys( $defaults ) ) ), array() );
ck( 'the scopes are the six tabs that save, in their order, then the three tools that keep settings; Mail saves nothing of its own',
    array_keys( $scopes ),
    array( 'connection', 'people', 'institutions', 'sponsors', 'security', 'advanced', 'tool:mentor-status-checker', 'tool:duplicate-finder', 'tool:handbook' ) );

$tool_scopes = array();

foreach ( array( new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder(), new WPCPM_Handbook() ) as $tool ) {
	$tool_scopes[] = 'tool:' . $tool->id();
}

```

with:

```php

ck( 'every setting is in exactly one scope, so a setting added without one, or put in two, fails here', $not_once, array() );
ck( 'and no scope names a setting that does not exist', array_values( array_diff( $in_scope, array_keys( $defaults ) ) ), array() );
ck( 'the scopes are the six tabs that save, in their order, then the four tools that keep settings; Mail saves nothing of its own',
    array_keys( $scopes ),
    array( 'connection', 'people', 'institutions', 'sponsors', 'security', 'advanced', 'tool:mentor-status-checker', 'tool:duplicate-finder', 'tool:handbook', 'tool:emails' ) );

$tool_scopes = array();

foreach ( array( new WPCPM_Mentor_Checker(), new WPCPM_Duplicate_Finder(), new WPCPM_Handbook(), new WPCPM_Emails() ) as $tool ) {
	$tool_scopes[] = 'tool:' . $tool->id();
}

```

In `bin/test-settings.php`, replace:

```php
	'tool:mentor-status-checker' => array_values( array_filter( array_keys( $defaults ), static function ( $key ) { return 0 === strpos( $key, 'checker_' ); } ) ),
	'tool:duplicate-finder'      => array( 'duplicate_delete_enabled' ),
	'tool:handbook'              => array_values( array_filter( array_keys( $defaults ), static function ( $key ) { return 0 === strpos( $key, 'handbook_' ); } ) ),
);

$sorted = static function ( array $map ) {
```

with:

```php
	'tool:mentor-status-checker' => array_values( array_filter( array_keys( $defaults ), static function ( $key ) { return 0 === strpos( $key, 'checker_' ); } ) ),
	'tool:duplicate-finder'      => array( 'duplicate_delete_enabled' ),
	'tool:handbook'              => array_values( array_filter( array_keys( $defaults ), static function ( $key ) { return 0 === strpos( $key, 'handbook_' ); } ) ),
	'tool:emails'                => array( 'mail_contact' ),
);

$sorted = static function ( array $map ) {
```

In `bin/test-settings.php`, replace:

```php
        'tool:mentor-status-checker' => array( 'success', 'Settings saved for the Mentor Status Checker tool.' ),
        'tool:duplicate-finder'      => array( 'success', 'Settings saved for the Student Duplicate Finder tool.' ),
        'tool:handbook'              => array( 'success', 'Settings saved for the Need help? tool.' ),
    ) );

$messages = WPCPM_Settings_Screen::settings_messages();
```

with:

```php
        'tool:mentor-status-checker' => array( 'success', 'Settings saved for the Mentor Status Checker tool.' ),
        'tool:duplicate-finder'      => array( 'success', 'Settings saved for the Student Duplicate Finder tool.' ),
        'tool:handbook'              => array( 'success', 'Settings saved for the Need help? tool.' ),
        'tool:emails'                => array( 'success', 'Settings saved for the Emails tool.' ),
    ) );

$messages = WPCPM_Settings_Screen::settings_messages();
```

In `bin/test-settings.php`, replace:

```php
$saving        = array( 'connection', 'people', 'institutions', 'sponsors', 'security', 'advanced' );
$settings_home = 'https://example.test/wp-admin/admin.php?page=wpcpm-settings';

// The three tools that keep settings, each by its ID, its scope and its screen's address.
$keeping = array(
	'mentor-status-checker' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-mentor-status-checker',
	'duplicate-finder'      => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder',
	'handbook'              => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-handbook',
);

ck( 'the screen has the design\'s seven tabs in its order, and no tab for the tools, whose settings are on their own screens',
```

with:

```php
$saving        = array( 'connection', 'people', 'institutions', 'sponsors', 'security', 'advanced' );
$settings_home = 'https://example.test/wp-admin/admin.php?page=wpcpm-settings';

// The four tools that keep settings, each by its ID, its scope and the address of its Settings
// section: its screen, or for the Emails tool the Signature and footer tab, where the footer that
// names its contact address is.
$keeping = array(
	'mentor-status-checker' => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-mentor-status-checker',
	'duplicate-finder'      => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-duplicate-finder',
	'handbook'              => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-handbook',
	'emails'                => 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer',
);

ck( 'the screen has the design\'s seven tabs in its order, and no tab for the tools, whose settings are on their own screens',
```

In `bin/test-settings.php`, replace:

```php
        'mentor-status-checker' => 'Settings saved for the Mentor Status Checker tool.',
        'duplicate-finder'      => 'Settings saved for the Student Duplicate Finder tool.',
        'handbook'              => 'Settings saved for the Need help? tool.',
    ) );

$GLOBALS['umeta'] = array();
```

with:

```php
        'mentor-status-checker' => 'Settings saved for the Mentor Status Checker tool.',
        'duplicate-finder'      => 'Settings saved for the Student Duplicate Finder tool.',
        'handbook'              => 'Settings saved for the Need help? tool.',
        'emails'                => 'Settings saved for the Emails tool.',
    ) );

$GLOBALS['umeta'] = array();
```

In `bin/test-settings.php`, replace:

```php
	}
}

ck( 'the Connection tab opens on one sentence naming the three tools that keep their settings on their own screens, each a link to its Settings section there, and no other tab says it',
    array( html_entity_decode( strip_tags( $connection_lede ), ENT_QUOTES ), $linked, $keeps, $ledes_elsewhere ),
    array(
        'The Mentor Status Checker, the Student Duplicate Finder and Need help? keep their settings on their own screens, under WPCredits Program > Tools.',
        array(
            array( 'Mentor Status Checker', $keeping['mentor-status-checker'] . '#settings' ),
            array( 'Student Duplicate Finder', $keeping['duplicate-finder'] . '#settings' ),
            array( 'Need help?', $keeping['handbook'] . '#settings' ),
        ),
        array_fill_keys( array_keys( $keeping ), true ),
        array(),
```

with:

```php
	}
}

ck( 'the Connection tab opens on one sentence naming the four tools that keep their settings on their own screens, each a link to its Settings section there, and no other tab says it',
    array( html_entity_decode( strip_tags( $connection_lede ), ENT_QUOTES ), $linked, $keeps, $ledes_elsewhere ),
    array(
        'The Mentor Status Checker, the Student Duplicate Finder, Need help? and Emails keep their settings on their own screens, under WPCredits Program > Tools.',
        array(
            array( 'Mentor Status Checker', $keeping['mentor-status-checker'] . '#settings' ),
            array( 'Student Duplicate Finder', $keeping['duplicate-finder'] . '#settings' ),
            array( 'Need help?', $keeping['handbook'] . '#settings' ),
            array( 'Emails', $keeping['emails'] . '#settings' ),
        ),
        array_fill_keys( array_keys( $keeping ), true ),
        array(),
```

`bin/test-admin-menu.php` holds the Connection tab's list to the registry's tools that keep settings:

In `bin/test-admin-menu.php`, replace:

```php
sort( $keeping_own );
sort( $named_there );

ck( 'the tools the Connection tab names as keeping their settings on their own screens are the registry\'s tools that keep settings, all three of them',
    array( $named_there, count( $keeping_own ) ), array( $keeping_own, 3 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

```

with:

```php
sort( $keeping_own );
sort( $named_there );

ck( 'the tools the Connection tab names as keeping their settings on their own screens are the registry\'s tools that keep settings, all four of them',
    array( $named_there, count( $keeping_own ) ), array( $keeping_own, 4 ) );

echo "\n" . ( $fail ? "$fail FAILURE(S)\n" : "ALL PASS\n" );

```

`bin/test-emails-templates.php` draws the footer tab with the real Settings section, so it loads the settings classes and the two functions they reach; three changes:

In `bin/test-emails-templates.php`, replace:

```php
function apply_filters( $h, $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) {}
// What the mail layer hands WordPress, with the id and the test mark the mail log's capture takes.
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'context' => WPCPM_Mail::take_context(), 'test' => WPCPM_Mail::take_test() );
```

with:

```php
function apply_filters( $h, $v ) { return $v; }
function wp_parse_args( $a, $d = array() ) { return array_merge( $d, (array) $a ); }
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false ) {}
function submit_button( $text = null, $type = 'primary', $name = 'submit', $wrap = true, $other = array() ) { echo '<p class="submit"><input type="submit" name="' . $name . '" id="' . ( $other['id'] ?? $name ) . '" class="button button-' . $type . '" value="' . esc_attr( (string) $text ) . '" /></p>'; }
// The row's help may hold <code>, which the row lets through as core's kses would.
function wp_kses( $s, $allowed = array() ) { return $s; }
// No page at the path the program manager guide is published at, so the Settings section links none.
function get_page_by_path( $path, $output = 'OBJECT', $type = 'page' ) { return null; }
// What the mail layer hands WordPress, with the id and the test mark the mail log's capture takes.
function wp_mail( $to, $subject, $body, $headers = array(), $attachments = array() ) {
	$GLOBALS['mail'][] = array( 'to' => $to, 'subject' => $subject, 'body' => $body, 'headers' => $headers, 'context' => WPCPM_Mail::take_context(), 'test' => WPCPM_Mail::take_test() );
```

In `bin/test-emails-templates.php`, replace:

```php
require_once __DIR__ . '/stubs/cleaners.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
```

with:

```php
require_once __DIR__ . '/stubs/cleaners.php';

require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-roles.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-rows.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-settings-screen.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-request.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-flash.php';
require_once WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-typed-text.php';
```

In `bin/test-emails-templates.php`, replace:

```php
new_administrator();
ck( 'and Restore the original brings the shipped footer back', array( press( 'handle_restore', array( 'wpcpm_email' => 'footer' ) ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'] ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', 'shipped' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

with:

```php
new_administrator();
ck( 'and Restore the original brings the shipped footer back', array( press( 'handle_restore', array( 'wpcpm_email' => 'footer' ) ), WPCPM_Mail_Templates::state( WPCPM_Mail_Templates::FOOTER )['state'] ), array( 'redirect: https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer', 'shipped' ) );

echo "\n=== Contact address in emails ===\n";

new_administrator();
$settings_part = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) );
preg_match( '#<div class="wpcpm-card"><h2 id="settings">Settings</h2>.*?</form>#s', $settings_part, $section );
ck( 'the tab opens on the tool\'s Settings section, above the footer: one form posting the tool\'s scope, holding Contact address in emails, empty as it ships', array( isset( $section[0] ), false !== strpos( $section[0] ?? '', 'name="wpcpm_tab" value="tool:emails"' ), 1 === preg_match( '#<label for="wpcpm-mail_contact">Contact address in emails</label></th><td><input type="email" id="wpcpm-mail_contact" name="mail_contact" value=""#', $section[0] ?? '' ), strpos( $settings_part, 'id="settings"' ) < strpos( $settings_part, '<h2>Signature and footer</h2>' ) ), array( true, true, true, true ) );
ck( 'while it is empty, the preview leaves out the sentence that names it', 1 === preg_match( '#<div class="wpcpm-emails__preview">[^<]*<pre class="wpcpm-emails__text">[^<]*Questions\?#', $settings_part ), false );

new_administrator();
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array( 'mail_contact' => 'programs@example.test' );
$set = screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) );
ck( 'set, the field holds it, and the footer names it', array( false !== strpos( $set, 'name="mail_contact" value="programs@example.test"' ), false !== strpos( $set, 'Questions? Write to programs@example.test.</pre>' ), substr( WPCPM_Mail_Templates::add_footer( 'Hi.', null, 'institution-applied' ), -strlen( 'Questions? Write to programs@example.test.' ) ) ), array( true, true, 'Questions? Write to programs@example.test.' ) );
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array();

new_administrator();
WPCPM_Flash::set( 'emails-contact-refused', true );
ck( 'and a save refused for what was typed is said on the tab', false !== strpos( screen( array( 'page' => 'wpcpm-tool-emails', 'tab' => 'footer' ) ), '<div class="notice notice-error"><p>Contact address in emails was not saved: what was typed is not an email address. The one saved before is kept.</p></div>' ), true );
ck( 'the tool keeps that one setting, and its Settings section is on the Signature and footer tab', array( ( new WPCPM_Emails() )->settings_keys(), ( new WPCPM_Emails() )->settings_url() ), array( array( 'mail_contact' ), 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=footer' ) );

printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

- [ ] **Step 2: Run them to verify they fail**

Run, one per command: `php bin/test-settings.php`, `php bin/test-admin-menu.php`, `php bin/test-emails-templates.php`
Expected: `bin/test-settings.php` fails eight checks ("mail_contact survives the save", "and every probe names a real setting", the two Contact address checks, the three checks of the scopes and the one of the notice that names the tool saved), then stops at a `TypeError` in `sort()`, where it reads the settings of a scope, `tool:emails`, that does not exist yet; `bin/test-admin-menu.php` ends `1 FAILURE(S)` (three tools keep settings where four are wanted); `bin/test-emails-templates.php` fails the three checks of its Contact address in emails section, then stops at `Call to undefined method WPCPM_Emails::settings_url()`.

- [ ] **Step 3: The setting**

The default, after the Mentor Status Checker's:

In `includes/class-wpcpm-settings.php`, replace:

```php
			// default because this code is published and the channel is private.
			'checker_slack_webhook'         => '',
			'checker_slack_channel'         => '',
		);
	}

```

with:

```php
			// default because this code is published and the channel is private.
			'checker_slack_webhook'         => '',
			'checker_slack_channel'         => '',

			// Tool - Emails. The address the signature and footer every email ends with names for
			// questions (`{program_contact}`), which the Administrators read. Empty until they name the
			// mailbox, and the footer then leaves that sentence out.
			'mail_contact'                  => '',
		);
	}

```

its scope, after Need help?'s:

In `includes/class-wpcpm-settings.php`, replace:

```php
				'handbook_access',
				'handbook_limit',
			),
		);
	}

```

with:

```php
				'handbook_access',
				'handbook_limit',
			),
			'tool:emails'                => array(
				'mail_contact',
			),
		);
	}

```

and the save's rule, before the timeline filter's:

In `includes/class-wpcpm-settings.php`, replace:

```php
			}
		}

		if ( isset( $input['checker_timeline_filter'] ) ) {
			$clean['checker_timeline_filter'] = ( 'all' === $input['checker_timeline_filter'] ) ? 'all' : 'meta';
		}
```

with:

```php
			}
		}

		// The contact address the emails' footer names: an address, or blank to name none. Anything else
		// keeps the one saved, and the Emails tool's Signature and footer tab says why: a footer naming
		// a mistyped address would send every question nowhere.
		if ( isset( $input['mail_contact'] ) ) {
			$contact = is_string( $input['mail_contact'] ) ? trim( wp_unslash( $input['mail_contact'] ) ) : null;

			if ( '' === $contact ) {
				$clean['mail_contact'] = '';
			} elseif ( is_string( $contact ) && is_email( $contact ) && sanitize_email( $contact ) === $contact ) {
				$clean['mail_contact'] = $contact;
			} else {
				WPCPM_Flash::set( 'emails-contact-refused', true );
			}
		}

		if ( isset( $input['checker_timeline_filter'] ) ) {
			$clean['checker_timeline_filter'] = ( 'all' === $input['checker_timeline_filter'] ) ? 'all' : 'meta';
		}
```

- [ ] **Step 4: Where a tool's Settings section is**

A tool's Settings section is on its screen, unless the tool says it is elsewhere:

In `includes/tools/class-wpcpm-tool.php`, replace:

```php
	 */
	protected function render_settings_rows() {}

	/**
	 * Admin page slug.
	 *
```

with:

```php
	 */
	protected function render_settings_rows() {}

	/**
	 * Where the tool's Settings section is drawn, which a Save of it comes back to and the Settings
	 * screen's Connection tab links: the tool's screen, or a tab of it.
	 *
	 * @return string
	 */
	public function settings_url() {
		return $this->admin_url();
	}

	/**
	 * Admin page slug.
	 *
```

On the Settings screen, the tools that keep settings are four,

In `includes/class-wpcpm-settings-screen.php`, replace:

```php
	 * names them (`render_tools_lede()`): each keeps them in the Settings section of its own screen.
	 * bin/test-admin-menu.php holds the list to the registry's tools that keep settings.
	 */
	const TOOLS_WITH_SETTINGS = array( 'mentor-status-checker', 'duplicate-finder', 'handbook' );

	/**
	 * Boolean settings that no settings form draws yet, neither a tab of this screen nor a tool's
```

with:

```php
	 * names them (`render_tools_lede()`): each keeps them in the Settings section of its own screen.
	 * bin/test-admin-menu.php holds the list to the registry's tools that keep settings.
	 */
	const TOOLS_WITH_SETTINGS = array( 'mentor-status-checker', 'duplicate-finder', 'handbook', 'emails' );

	/**
	 * Boolean settings that no settings form draws yet, neither a tab of this screen nor a tool's
```

a tool's Save comes back to its Settings section,

In `includes/class-wpcpm-settings-screen.php`, replace:

```php
		$tool = self::tool_of( $scope );

		if ( null !== $tool ) {
			return $tool->admin_url();
		}

		return 0 === strpos( (string) $scope, 'tool:' ) ? self::settings_url() : self::settings_url( $scope );
```

with:

```php
		$tool = self::tool_of( $scope );

		if ( null !== $tool ) {
			return $tool->settings_url();
		}

		return 0 === strpos( (string) $scope, 'tool:' ) ? self::settings_url() : self::settings_url( $scope );
```

and the Connection tab's first line names the four, each linked where its section is:

In `includes/class-wpcpm-settings-screen.php`, replace:

```php
				return;
			}

			$links[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $tool->admin_url() . '#settings' ), esc_html( $tool->label() ) );
		}

		$sentence = sprintf(
			/* translators: 1: Mentor Status Checker, 2: Student Duplicate Finder, 3: Need help?, each a tool's name as a link to the Settings section of its screen. */
			esc_html__( 'The %1$s, the %2$s and %3$s keep their settings on their own screens, under WPCredits Program > Tools.', 'wpcredits-program-manager' ),
			$links[0],
			$links[1],
			$links[2]
		);

		printf( '<p class="wpcpm-lede">%s</p>', $sentence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The sentence escaped above, and each link built from escaped parts.
```

with:

```php
				return;
			}

			$links[] = sprintf( '<a href="%1$s">%2$s</a>', esc_url( $tool->settings_url() . '#settings' ), esc_html( $tool->label() ) );
		}

		$sentence = sprintf(
			/* translators: 1: Mentor Status Checker, 2: Student Duplicate Finder, 3: Need help?, 4: Emails, each a tool's name as a link to the Settings section of its screen. */
			esc_html__( 'The %1$s, the %2$s, %3$s and %4$s keep their settings on their own screens, under WPCredits Program > Tools.', 'wpcredits-program-manager' ),
			$links[0],
			$links[1],
			$links[2],
			$links[3]
		);

		printf( '<p class="wpcpm-lede">%s</p>', $sentence ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- The sentence escaped above, and each link built from escaped parts.
```

- [ ] **Step 5: The Emails tool keeps the setting, on the Signature and footer tab**

In `includes/tools/class-wpcpm-emails.php`, replace:

```php
	}

	/**
	 * The Signature and footer tab: the one block every plugin email ends with, in the editor every
	 * template has.
	 */
	private function render_footer_tab() {
		$this->render_editor( WPCPM_Mail_Templates::FOOTER );
	}

	/**
	 * Who receives an email, as the Templates tab says it.
	 *
```

with:

```php
	}

	/**
	 * The Signature and footer tab: the tool's Settings section, with the contact address the footer
	 * names, then the one block every plugin email ends with, in the editor every template has.
	 */
	private function render_footer_tab() {
		if ( WPCPM_Flash::take( 'emails-contact-refused' ) ) {
			printf( '<div class="notice notice-error"><p>%s</p></div>', esc_html__( 'Contact address in emails was not saved: what was typed is not an email address. The one saved before is kept.', 'wpcredits-program-manager' ) );
		}

		$this->render_settings();
		$this->render_editor( WPCPM_Mail_Templates::FOOTER );
	}

	/**
	 * The tool's one setting, Contact address in emails, which the footer names.
	 *
	 * @return string[]
	 */
	public function settings_keys() {
		return array( 'mail_contact' );
	}

	/**
	 * The Settings section is on the Signature and footer tab, beside the footer that uses it.
	 *
	 * @return string
	 */
	public function settings_url() {
		return add_query_arg( 'tab', self::TAB_FOOTER, $this->admin_url() );
	}

	/**
	 * What the Settings section says under its heading.
	 *
	 * @return array
	 */
	protected function settings_intro() {
		return array(
			'sentence' => __( 'The address every email names for questions, in its signature and footer.', 'wpcredits-program-manager' ),
			'details'  => '',
		);
	}

	/**
	 * The one row: Contact address in emails.
	 */
	protected function render_settings_rows() {
		WPCPM_Settings_Rows::text_row(
			'mail_contact',
			__( 'Contact address in emails', 'wpcredits-program-manager' ),
			(string) WPCPM_Settings::get_value( 'mail_contact', '' ),
			__( 'A mailbox the Administrators read; left empty, the footer leaves out the sentence that names it.', 'wpcredits-program-manager' ),
			'email'
		);
	}

	/**
	 * Who receives an email, as the Templates tab says it.
	 *
```

- [ ] **Step 6: Run the tests to verify they pass**

Run, one per command: `php bin/test-settings.php`, `php bin/test-admin-menu.php`, `php bin/test-emails-templates.php`, `php bin/test-settings-choices.php`
Expected: `ALL PASS`; `ALL PASS`; `ALL PASS (55 checks)`; `All passed.`

- [ ] **Step 7: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 8: Commit**

```bash
git add includes/class-wpcpm-settings.php includes/tools/class-wpcpm-tool.php includes/class-wpcpm-settings-screen.php includes/tools/class-wpcpm-emails.php bin/test-settings.php bin/test-admin-menu.php bin/test-emails-templates.php
git commit -m "Emails E2: Contact address in emails, the Emails tool's setting, on the Signature and footer tab"
```

---

### Task 9: Settings > Mail without its sample buttons

The four "Email me the ... invitation" buttons and their handler go (ruling E2-6): Send me a test, on each template's editor, does that now, with the invitation as it is saved. The Mail tab keeps what is waiting to be sent and Recent mail, and its Invitations section links the templates; the guide's part on the tab, its anchor and the built guide move with it. The catalog keeps the four `test-*` ids, to name the rows they left in the Log; nothing sends them any more.

**Files:**
- Modify: `includes/class-wpcpm-mail.php` (`ACTION_TEST`, its hook and `handle_test()` go)
- Modify: `includes/class-wpcpm-settings-screen.php` (`render_mail_card()`; the two sample outcomes and the docblocks that named them)
- Modify: `includes/class-wpcpm-settings.php` (the docblock of `scopes()`, line 238: the Mail tab saves nothing, ruling PR-11)
- Modify: `bin/test-settings.php`, `bin/test-handlers.php`, `bin/test-mail.php`, `bin/test-mail-catalog.php`, `bin/test-submit-guard.php`
- Modify: `docs/sections/31-admin-settings.md` (the Mail tab's part, its heading now "Invitations waiting to be sent"), `docs/sections/30-admin-wpadmin.md` (the forms that lock at the first press)
- Regenerate: `docs/*.md` and `docs/build/*.html` with `php bin/build-docs.php`

**Interfaces:**
- Consumes: `WPCPM_Tools::get( 'emails' )`; `WPCPM_Emails::admin_url()`.
- Produces: `WPCPM_Settings_Screen::settings_messages()` without `test-sent` and `test-failed`; the Mail tab's Invitations section anchors `invitations-waiting-to-be-sent`. Gone: `WPCPM_Mail::ACTION_TEST`, `WPCPM_Mail::handle_test()`, the `admin_post_wpcpm_send_test_mail` hook.

- [ ] **Step 1: Write the failing checks**

Every suite is plain PHP that prints an `ok` or `FAIL` line for each check through its `ck()` helper, ends on a count, and exits non-zero on a failure. The helper and the two closing lines, as a new suite writes them (an existing suite keeps its own, whose arguments come in the same order: the label, what was got, what was wanted):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

`bin/test-settings.php` drew the four sample forms and their nonces; it now holds the tab to no form, two links and the waiting count. Eight changes:

In `bin/test-settings.php`, replace:

```php
	}
}

/** The mail layer, for the Mail tab: what is queued, and the sample buttons' action. */
class WPCPM_Mail {
	const ACTION_TEST = 'wpcpm_send_test_mail';

	public static $queued = 0;

	public static function queued() {
```

with:

```php
	}
}

/** The mail layer, for the Mail tab: what is queued. */
class WPCPM_Mail {
	public static $queued = 0;

	public static function queued() {
```

In `bin/test-settings.php`, replace:

```php
press_tab( 'no-such-tab', array( 'logo_max_kb' => '4096' ) );
$scope_after_refused = $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null;

// The Mail tab's sample buttons post to a handler of their own, which queues its outcome on the same
// channel, through the same door: the sample's notice cannot be read with a scope another save left.
$scope_after_sample = null;
$mail_source        = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php' );

if ( is_callable( array( 'WPCPM_Settings_Screen', 'flash_outcome' ) ) ) {
	WPCPM_Flash::set( 'settings-scope', 'sponsors' );
	WPCPM_Settings_Screen::flash_outcome( 'test-sent' );
	$scope_after_sample = array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings'] ?? null, $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null );
}

ck( 'a scope left queued by a notice nobody saw is replaced on every outcome, so it cannot name another tab\'s save, a refusal or a sample invitation, which the mail handler queues through the same door',
    array( $scope_after_tab, $scope_after_none, $scope_after_refused, $scope_after_sample, substr_count( method_source( $mail_source, 'handle_test' ), 'WPCPM_Settings_Screen::flash_outcome( ' ), substr_count( $mail_source, "WPCPM_Flash::set( 'settings'" ) ),
    array( 'people', '', '', array( 'test-sent', '' ), 1, 0 ) );

// The tracks are compiled against the two status lists, so only a save that changed one of them
// has anything to compile (decision 14).
```

with:

```php
press_tab( 'no-such-tab', array( 'logo_max_kb' => '4096' ) );
$scope_after_refused = $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null;

// An outcome that names no scope, the lists read again, queues its notice through the same door,
// which sets the scope with it. The mail layer queues none since 1.122.19: the Mail tab's sample
// buttons, whose handler did, are gone, and Send me a test in the Emails tool says its own.
$scope_after_lists = null;
$mail_source       = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'includes/class-wpcpm-mail.php' );

if ( is_callable( array( 'WPCPM_Settings_Screen', 'flash_outcome' ) ) ) {
	WPCPM_Flash::set( 'settings-scope', 'sponsors' );
	WPCPM_Settings_Screen::flash_outcome( 'lists-read' );
	$scope_after_lists = array( $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings'] ?? null, $GLOBALS['umeta'][1][ WPCPM_Flash::META ]['settings-scope'] ?? null );
}

ck( 'a scope left queued by a notice nobody saw is replaced on every outcome, so it cannot name another tab\'s save, a refusal or the lists read again, and the mail layer queues no notice of this screen\'s',
    array( $scope_after_tab, $scope_after_none, $scope_after_refused, $scope_after_lists, substr_count( $mail_source, 'WPCPM_Settings_Screen::flash_outcome( ' ), substr_count( $mail_source, "WPCPM_Flash::set( 'settings'" ) ),
    array( 'people', '', '', array( 'lists-read', '' ), 0, 0 ) );

// The tracks are compiled against the two status lists, so only a save that changed one of them
// has anything to compile (decision 14).
```

In `bin/test-settings.php`, replace:

```php

ck( 'every outcome the settings flash can carry has words, the refusal among them as an error that says what the form asked for',
    array( array_keys( $messages ), $messages['refused'] ),
    array( array( 'saved', 'refused', 'test-sent', 'test-failed', 'log-cleared', 'lists-read' ), array( 'error', 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.' ) ) );

WPCPM_Tracks::$live = array();
$GLOBALS['umeta']   = array();
```

with:

```php

ck( 'every outcome the settings flash can carry has words, the refusal among them as an error that says what the form asked for',
    array( array_keys( $messages ), $messages['refused'] ),
    array( array( 'saved', 'refused', 'log-cleared', 'lists-read' ), array( 'error', 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.' ) ) );

WPCPM_Tracks::$live = array();
$GLOBALS['umeta']   = array();
```

In `bin/test-settings.php`, replace:

```php
		'Two-factor authentication' => 'two-factor-authentication',
	),
	'mail'         => array(
		'Invitations' => 'sending-yourself-an-invitation',
		'Recent mail' => 'recent-mail',
	),
	'advanced'     => array(
```

with:

```php
		'Two-factor authentication' => 'two-factor-authentication',
	),
	'mail'         => array(
		'Invitations' => 'invitations-waiting-to-be-sent',
		'Recent mail' => 'recent-mail',
	),
	'advanced'     => array(
```

In `bin/test-settings.php`, replace:

```php
		}
	}

	ck( sprintf( 'the %s tab\'s sections are the design\'s, in its order, each its heading, one sentence ending in a link to its own section of the program manager guide on this site, named for a screen reader, then its rows in a form table%s', $want_tabs[ $slug ], 'mail' === $slug ? ' (Mail\'s are its forms and its log)' : '' ),
	    array( $drawn, preg_match_all( '#<h2[ >]#', preg_replace( '#<nav class="nav-tab-wrapper[^"]*"[^>]*>.*?</nav>#s', '', $html ) ) ),
	    array( $want, count( $headings ) ) );
}
```

with:

```php
		}
	}

	ck( sprintf( 'the %s tab\'s sections are the design\'s, in its order, each its heading, one sentence ending in a link to its own section of the program manager guide on this site, named for a screen reader, then its rows in a form table%s', $want_tabs[ $slug ], 'mail' === $slug ? ' (Mail\'s are its links)' : '' ),
	    array( $drawn, preg_match_all( '#<h2[ >]#', preg_replace( '#<nav class="nav-tab-wrapper[^"]*"[^>]*>.*?</nav>#s', '', $html ) ) ),
	    array( $want, count( $headings ) ) );
}
```

In `bin/test-settings.php`, replace:

```php

$GLOBALS['pages'] = array();

// Mail saves nothing of its own: each control is a form posting to its own handler, and a Save there
// would post a tab that holds no settings.
WPCPM_Mail::$queued = 3;
$mail_html          = draw_settings( 'mail' );
WPCPM_Mail::$queued = 0;
$drawn              = array();

foreach ( forms_of( $mail_html ) as $form ) {
	$drawn[] = array(
		false !== strpos( $form['open'], 'action="https://example.test/wp-admin/admin-post.php"' ),
		preg_match( '#name="action" value="([^"]*)"#', $form['body'], $posted ) ? $posted[1] : null,
		preg_match( '#name="kind" value="([^"]*)"#', $form['body'], $posted ) ? $posted[1] : null,
		$form['tab'],
		$form['saves'],
	);
}

ck( 'the Mail tab has no Save: its four sample buttons are each a form posting to their own handler, one for each audience',
    $drawn,
    array(
        array( true, 'wpcpm_send_test_mail', 'student', null, 0 ),
        array( true, 'wpcpm_send_test_mail', 'mentor', null, 0 ),
        array( true, 'wpcpm_send_test_mail', 'institution', null, 0 ),
        array( true, 'wpcpm_send_test_mail', 'sponsor', null, 0 ),
    ) );
// A second press mailed the presser a second sample, so each form carries the double-press guard,
// which locks it at the first press and swaps the button's words for its busy word.
ck( 'and each sample form locks at its first press and says Sending while its mail is on the way',
    array_map( function ( $form ) { return trim( $form['open'] ); }, forms_of( $mail_html ) ),
    array_fill( 0, 4, 'method="post" action="https://example.test/wp-admin/admin-post.php" class="wpcpm-inline-form" data-wpcpm-once data-wpcpm-busy="Sending"' ) );
ck( 'and it draws what is waiting to be sent and the way to the email log, with no Save anywhere on it',
    array( false !== strpos( $mail_html, '3 invitations are waiting to be sent.' ), false !== strpos( $mail_html, '<a class="button" href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log">Open the email log</a>' ), substr_count( $mail_html, 'value="Save settings"' ) ),
    array( true, true, 0 ) );

// Recent mail points to the Emails tool's Log, which keeps every email for 30 days, and says how many
// failed in that time, linked to them; with none failed it says nothing of failures.
```

with:

```php

$GLOBALS['pages'] = array();

// Mail saves nothing: it says what is waiting to be sent, and links where the invitations' wording is
// and where the record of what went out is. Its four sample buttons, a form each, went in 1.122.19,
// with Send me a test on each template's editor in the Emails tool in their place.
WPCPM_Mail::$queued = 3;
$mail_html          = draw_settings( 'mail' );
WPCPM_Mail::$queued = 0;

ck( 'the Mail tab has no form and no Save: its sample buttons are gone, and with them their action',
    array( forms_of( $mail_html ), substr_count( $mail_html, 'value="Save settings"' ), false !== strpos( $mail_html, 'wpcpm_send_test_mail' ) ),
    array( array(), 0, false ) );
ck( 'it draws what is waiting to be sent, the way to the email templates and the way to the email log',
    array( false !== strpos( $mail_html, '3 invitations are waiting to be sent.' ), false !== strpos( $mail_html, '<a class="button" href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates">Open the email templates</a>' ), false !== strpos( $mail_html, '<a class="button" href="https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log">Open the email log</a>' ) ),
    array( true, true, true ) );

// Recent mail points to the Emails tool's Log, which keeps every email for 30 days, and says how many
// failed in that time, linked to them; with none failed it says nothing of failures.
```

In `bin/test-settings.php`, replace:

```php
	$log_links[ $case ] = array(
		preg_match( '#<a class="button" href="([^"]*)">Open the email log</a>#', $drawn_mail, $open ) ? $open[1] : null,
		preg_match( '#<a href="([^"]*)">Show the failed emails</a>#', $drawn_mail, $failed_link ) ? $failed_link[1] : null,
	);
}

```

with:

```php
	$log_links[ $case ] = array(
		preg_match( '#<a class="button" href="([^"]*)">Open the email log</a>#', $drawn_mail, $open ) ? $open[1] : null,
		preg_match( '#<a href="([^"]*)">Show the failed emails</a>#', $drawn_mail, $failed_link ) ? $failed_link[1] : null,
		preg_match( '#<a class="button" href="([^"]*)">Open the email templates</a>#', $drawn_mail, $templates_link ) ? $templates_link[1] : null,
	);
}

```

In `bin/test-settings.php`, replace:

```php
WPCPM_Tools::$removed   = array();
WPCPM_Mail_Log::$failed = 0;

ck( 'Recent mail links the Log at the Emails tool\'s own address with the Log tab, as the registry has it, and at the address the tool would have when it is filtered out',
    $log_links,
    array(
        'the tool registered' => array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log&status=failed' ),
        'at another address'  => array( 'https://example.test/wp-admin/admin.php?page=moved-emails&tab=log', 'https://example.test/wp-admin/admin.php?page=moved-emails&tab=log&status=failed' ),
        'filtered out'        => array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log&status=failed' ),
    ) );

// Each sample form carries its nonce under the name its handler reads it by, in a field with an id of
// its own: core's nonce field takes its id from its name, which gave four elements of the tab one id.
$mail_nonces = array();

foreach ( forms_of( $mail_html ) as $form ) {
	$mail_nonces[] = preg_match( '#<input type="hidden" id="([^"]*)" name="wpcpm_send_test_mail" value="([^"]*)" />#', $form['body'], $nonce ) ? array( $nonce[1], $nonce[2] ) : null;
}

ck( 'each sample form carries the nonce its handler checks, under the name the handler reads, each in a field with an id of its own',
    $mail_nonces,
    array(
        array( 'wpcpm_send_test_mail-student', 'nonce:wpcpm_send_test_mail' ),
        array( 'wpcpm_send_test_mail-mentor', 'nonce:wpcpm_send_test_mail' ),
        array( 'wpcpm_send_test_mail-institution', 'nonce:wpcpm_send_test_mail' ),
        array( 'wpcpm_send_test_mail-sponsor', 'nonce:wpcpm_send_test_mail' ),
    ) );

$twice = array();
```

with:

```php
WPCPM_Tools::$removed   = array();
WPCPM_Mail_Log::$failed = 0;

ck( 'Recent mail links the Log, and Invitations the templates, at the Emails tool\'s own address with their tab, as the registry has it, and at the address the tool would have when it is filtered out',
    $log_links,
    array(
        'the tool registered' => array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log&status=failed', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates' ),
        'at another address'  => array( 'https://example.test/wp-admin/admin.php?page=moved-emails&tab=log', 'https://example.test/wp-admin/admin.php?page=moved-emails&tab=log&status=failed', 'https://example.test/wp-admin/admin.php?page=moved-emails&tab=templates' ),
        'filtered out'        => array( 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=log&status=failed', 'https://example.test/wp-admin/admin.php?page=wpcpm-tool-emails&tab=templates' ),
    ) );

$twice = array();
```

`bin/test-mail.php`'s section on the sample handler becomes a check that it is gone:

In `bin/test-mail.php`, replace everything from the line `/* ---- the sample invitation ---------------------------------------------- */` up to, not including, the line `/* ---- the meeting link -------------------------------------------------- */`, with:

```php
/* ---- the sample invitation, gone ---------------------------------------- */

echo "\n=== The sample invitation is gone ===\n";

// Settings > Mail's four "Email me the ... invitation" buttons and their handler went in 1.122.19:
// Send me a test, on each template's editor in WPCredits Program > Tools > Emails, sends the
// invitation as it is saved, with its example values, and its tests are bin/test-mail-templates.php's
// and bin/test-emails-templates.php's.
ck( 'the mail layer has no sample handler, no action for one, and hooks none',
    array( method_exists( 'WPCPM_Mail', 'handle_test' ), defined( 'WPCPM_Mail::ACTION_TEST' ), isset( $GLOBALS['filters']['admin_post_wpcpm_send_test_mail'] ) ),
    array( false, false, false ) );

```

`bin/test-mail-catalog.php` scanned the samples' ids, built from a kind; nothing builds them now, and the four stay in the catalog for the rows they left. Two changes:

In `bin/test-mail-catalog.php`, replace:

```php
	if ( preg_match( '/mail_manager\(\s*[^,()]+,\s*\$\w+\s*\)/', $src ) ) {
		$contexts['sponsor-interest'] = true;
	}
	foreach ( array( 'invite-', 'test-' ) as $built ) {
		if ( false !== strpos( $src, "'" . $built . "' . " ) ) {
			foreach ( array( 'student', 'mentor', 'institution', 'sponsor' ) as $k ) {
				$contexts[ $built . $k ] = true;
			}
		}
	}
}
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 43 of them (it is not reading nothing)', count( $contexts ), 43 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
```

with:

```php
	if ( preg_match( '/mail_manager\(\s*[^,()]+,\s*\$\w+\s*\)/', $src ) ) {
		$contexts['sponsor-interest'] = true;
	}
	if ( false !== strpos( $src, "'invite-' . " ) ) {
		foreach ( array( 'student', 'mentor', 'institution', 'sponsor' ) as $k ) {
			$contexts[ 'invite-' . $k ] = true;
		}
	}
	if ( false !== strpos( $src, "'test-' . " ) ) {
		$contexts['test- built from a kind'] = true;
	}
}
$missing = array_values( array_diff( array_keys( $contexts ), array_keys( $emails ) ) );
sort( $missing );
ck( 'every context the source passes to the mail layer is in the catalog', $missing, array() );
ck( 'and the scan found all 39 of them (it is not reading nothing)', count( $contexts ), 39 );

// The template registry's ids, read from its definition files as each file writes them: one at the
// start of each line of the array the file returns. An invitation is named by its template's id,
```

In `bin/test-mail-catalog.php`, replace:

```php
	$templates = array_merge( $templates, $t[1] );
}
ck( 'every template the registry defines is in the catalog', array_values( array_diff( $templates, array_keys( $emails ) ) ), array() );
$plugin_ids = array_keys( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) );
$sent_ids   = array_unique( array_merge( array_keys( $contexts ), $templates ) );
sort( $plugin_ids );
sort( $sent_ids );
ck( 'and every email of the plugin\'s the catalog names is one the source sends or a template the registry holds', $plugin_ids, $sent_ids );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
$unnamed = array_values( array_diff( array_values( $filters ), array_keys( $emails ) ) );
```

with:

```php
	$templates = array_merge( $templates, $t[1] );
}
ck( 'every template the registry defines is in the catalog', array_values( array_diff( $templates, array_keys( $emails ) ) ), array() );
// The four samples Settings > Mail sent until 1.122.19 stay in the catalog, so the Log names the rows
// they left, which it keeps for 30 days; nothing sends them now: Send me a test logs a test under its
// template's own id.
$retired    = array( 'test-student', 'test-mentor', 'test-institution', 'test-sponsor' );
$plugin_ids = array_values( array_diff( array_keys( array_filter( $emails, function ( $e ) { return ! in_array( $e['module'], array( 'wordpress', 'two-factor' ), true ); } ) ), $retired ) );
$sent_ids   = array_unique( array_merge( array_keys( $contexts ), $templates ) );
sort( $plugin_ids );
sort( $sent_ids );
ck( 'and every email of the plugin\'s the catalog names is one the source sends or a template the registry holds, but the four samples Settings > Mail sent before 1.122.19', $plugin_ids, $sent_ids );
ck( 'which nothing sends any more', array_values( array_intersect( $retired, $sent_ids ) ), array() );

$filters = WPCPM_Mail_Catalog::wordpress_filters();
$unnamed = array_values( array_diff( array_values( $filters ), array_keys( $emails ) ) );
```

`bin/test-handlers.php` pressed the sample handler beside the settings save; three changes:

In `bin/test-handlers.php`, replace:

```php
$GLOBALS['nonce_fails'] = false;
$_POST                  = array();

echo "\n=== The Settings screen's save and the Mail tab's samples check the nonce before the right ===\n";

// The screen's three handlers answer in one order: the nonce first, then whether the person may manage
// the program. The settings save still asks first whether a settings form was posted at all, since it
// runs on every admin request, and a request without the settings nonce's field is not its to answer.
$GLOBALS['caps']        = false;
$GLOBALS['nonce_fails'] = true;
```

with:

```php
$GLOBALS['nonce_fails'] = false;
$_POST                  = array();

echo "\n=== The Settings screen's save checks the nonce before the right ===\n";

// The screen's handlers answer in one order: the nonce first, then whether the person may manage the
// program (the button that reads the lists again is pressed above; the Mail tab's sample buttons went
// in 1.122.19). The settings save still asks first whether a settings form was posted at all, since it
// runs on every admin request, and a request without the settings nonce's field is not its to answer.
$GLOBALS['caps']        = false;
$GLOBALS['nonce_fails'] = true;
```

In `bin/test-handlers.php`, replace:

```php
run( 'handle_settings_save (no capability, and the nonce fails too)', array( new WPCPM_Settings_Screen(), 'handle_settings_save' ) );
$first_answers['handle_settings_save'] = $GLOBALS['died_with'];

$_POST = array( 'action' => WPCPM_Mail::ACTION_TEST, 'kind' => 'student' );
run( 'WPCPM_Mail::handle_test (no capability, and the nonce fails too)', array( 'WPCPM_Mail', 'handle_test' ) );
$first_answers['handle_test'] = $GLOBALS['died_with'];

$GLOBALS['nonce_fails'] = false;

$_POST = array( WPCPM_Settings_Screen::SETTINGS_NONCE => 'x', WPCPM_Settings_Screen::SETTINGS_TAB_FIELD => 'sponsors' );
run( 'handle_settings_save (no capability)', array( new WPCPM_Settings_Screen(), 'handle_settings_save' ) );
$first_answers['handle_settings_save, the nonce good'] = $GLOBALS['died_with'];

$_POST = array( 'action' => WPCPM_Mail::ACTION_TEST, 'kind' => 'student' );
run( 'WPCPM_Mail::handle_test (no capability)', array( 'WPCPM_Mail', 'handle_test' ) );
$first_answers['handle_test, the nonce good'] = $GLOBALS['died_with'];

// And with no settings form posted, the save does nothing at all, whatever the nonce or the right.
$GLOBALS['nonce_fails'] = true;
$_POST                  = array( 'something_else' => '1' );
```

with:

```php
run( 'handle_settings_save (no capability, and the nonce fails too)', array( new WPCPM_Settings_Screen(), 'handle_settings_save' ) );
$first_answers['handle_settings_save'] = $GLOBALS['died_with'];

$GLOBALS['nonce_fails'] = false;

$_POST = array( WPCPM_Settings_Screen::SETTINGS_NONCE => 'x', WPCPM_Settings_Screen::SETTINGS_TAB_FIELD => 'sponsors' );
run( 'handle_settings_save (no capability)', array( new WPCPM_Settings_Screen(), 'handle_settings_save' ) );
$first_answers['handle_settings_save, the nonce good'] = $GLOBALS['died_with'];

// And with no settings form posted, the save does nothing at all, whatever the nonce or the right.
$GLOBALS['nonce_fails'] = true;
$_POST                  = array( 'something_else' => '1' );
```

In `bin/test-handlers.php`, replace:

```php
$GLOBALS['caps']        = true;
$_POST                  = array();

check( 'the settings save and the sample invitation answer a failed nonce before the right, and a missing right once the nonce holds, and the save leaves a request with no settings form alone',
    array( $first_answers, $ignored ),
    array(
        array(
            'handle_settings_save'                 => 'The link you followed has expired.',
            'handle_test'                          => 'The link you followed has expired.',
            'handle_settings_save, the nonce good' => 'You do not have permission to manage the program.',
            'handle_test, the nonce good'          => 'You do not have permission to manage the program.',
        ),
        'returned',
    ) );
```

with:

```php
$GLOBALS['caps']        = true;
$_POST                  = array();

check( 'the settings save answers a failed nonce before the right, and a missing right once the nonce holds, and leaves a request with no settings form alone',
    array( $first_answers, $ignored ),
    array(
        array(
            'handle_settings_save'                 => 'The link you followed has expired.',
            'handle_settings_save, the nonce good' => 'You do not have permission to manage the program.',
        ),
        'returned',
    ) );
```

`bin/test-submit-guard.php` held the sample forms to the double-press guard; it holds the Emails editor's forms to it instead. Two changes:

In `bin/test-submit-guard.php`, replace:

```php
    ),
    array( true, true ) );

// The wp-admin forms whose second press does harm and that carried no guard: the sample
// invitations on Settings > Mail, one form drawn for each audience, where a second press mails
// the presser again, and the Track Builder's Create the track and Make the copy, where two presses
// that overlap can make two tracks. Each method draws one form, and it carries the attribute and
// its busy word, translated and escaped for the attribute, with a `<button>` to show the word on:
// the guard swaps a button's text, and an input's label is its value, which it leaves alone.
$settings_src = (string) file_get_contents( $root . '/includes/class-wpcpm-settings-screen.php' );
$builder_src  = (string) file_get_contents( $root . '/includes/tools/class-wpcpm-track-builder-screen.php' );
$mail_card    = method_code( $settings_src, 'private function render_mail_card()' );
$marked       = array();

foreach ( array(
	'Settings > Mail'  => array( $mail_card, 'Sending' ),
	'Create the track' => array( method_code( $builder_src, 'public static function render_new( array $args )' ), 'Creating' ),
	'Make the copy'    => array( method_code( $builder_src, 'public static function render_duplicate( array $args )' ), 'Copying' ),
) as $form => $drawn ) {
```

with:

```php
    ),
    array( true, true ) );

// The wp-admin forms whose second press does harm and that carried no guard: the Emails tool's
// Save form, whose second button, Send me a test, mails the presser again at a second press (the
// sample invitations of Settings > Mail did, until 1.122.19 put Send me a test in their place), and
// the Track Builder's Create the track and Make the copy, where two presses that overlap can make
// two tracks. Each method draws one form, and it carries the attribute and its busy word, translated
// and escaped for the attribute, with a `<button>` to show the word on: the guard swaps a button's
// text, and an input's label is its value, which it leaves alone. The Save form has two buttons, and
// its busy word, Working, holds for either.
$emails_src  = (string) file_get_contents( $root . '/includes/tools/class-wpcpm-emails.php' );
$builder_src = (string) file_get_contents( $root . '/includes/tools/class-wpcpm-track-builder-screen.php' );
$marked      = array();

foreach ( array(
	'the Emails editor\'s Save' => array( method_code( $emails_src, 'private function render_editor( $id )' ), 'Working' ),
	'Create the track' => array( method_code( $builder_src, 'public static function render_new( array $args )' ), 'Creating' ),
	'Make the copy'    => array( method_code( $builder_src, 'public static function render_duplicate( array $args )' ), 'Copying' ),
) as $form => $drawn ) {
```

In `bin/test-submit-guard.php`, replace:

```php
	);
}

ck( 'the Settings > Mail sample forms, Create the track and Make the copy each carry data-wpcpm-once and their busy word, Sending, Creating and Copying, on a button',
    $marked,
    array(
        'Settings > Mail'  => array( 1, true, 1, 1 ),
        'Create the track' => array( 1, true, 1, 1 ),
        'Make the copy'    => array( 1, true, 1, 1 ),
    ) );
ck( 'and the Mail tab draws its one form once for each of the four audiences',
    (bool) preg_match( '/foreach \( array\(\s*\'student\'\s+=>.*?\'mentor\'\s+=>.*?\'institution\'\s+=>.*?\'sponsor\'\s+=>.*?\) as \$kind => \$label \) \{\s*printf\(\s*\'<form /s', $mail_card ) );

/*
 * Every confirm is a `data-wpcpm-confirm` sentence forms.js asks (1.122.6), so nothing under
```

with:

```php
	);
}

ck( 'the Emails editor\'s Save form, Create the track and Make the copy each carry data-wpcpm-once and their busy word, Working, Creating and Copying, on a button: two on the Save form, Save and Send me a test',
    $marked,
    array(
        'the Emails editor\'s Save' => array( 1, true, 1, 2 ),
        'Create the track'          => array( 1, true, 1, 1 ),
        'Make the copy'             => array( 1, true, 1, 1 ),
    ) );

// Restore the original is a form of its own, drawn by one method, which carries the attribute and the
// busy word its caller hands it, Restoring, translated there, on a button; and the Mail tab draws no
// form at all any more.
$button = method_code( $emails_src, 'private function render_button( $id, $action, $label, $busy, $question )' );
ck( 'and Restore the original is a form of its own, locked at the first press with the busy word its caller translates, on a button; the Mail tab draws none',
    array(
        substr_count( $button, '<form' ),
        (bool) preg_match( '/<form [^>]*data-wpcpm-once data-wpcpm-busy="%2\$s"/', $button ),
        substr_count( $button, '<button type="submit"' ),
        substr_count( $emails_src, "__( 'Restoring', 'wpcredits-program-manager' )" ),
        substr_count( method_code( (string) file_get_contents( $root . '/includes/class-wpcpm-settings-screen.php' ), 'private function render_mail_card()' ), '<form' ),
    ),
    array( 1, true, 1, 1, 0 ) );

/*
 * Every confirm is a `data-wpcpm-confirm` sentence forms.js asks (1.122.6), so nothing under
```

- [ ] **Step 2: Run them to verify they fail**

Run, one per command: `php bin/test-settings.php`, `php bin/test-mail.php`, `php bin/test-mail-catalog.php`, `php bin/test-submit-guard.php`, `php bin/test-handlers.php`
Expected: `bin/test-settings.php` fails two checks, then stops at `Undefined constant WPCPM_Mail::ACTION_TEST`: its stand-in for the mail layer no longer carries the action the Mail tab still prints; `bin/test-mail.php` ends `1 FAILURE(S)` (the handler is still there); `bin/test-mail-catalog.php` ends `3 FAILURE(S) (21 checks)` (the scan still finds the samples built from a kind); `bin/test-submit-guard.php` ends `1 FAILURE(S)` (the Mail tab still draws its forms); `bin/test-handlers.php` ends `ALL HANDLERS REACHED A NORMAL OUTCOME`, its sample presses gone.

- [ ] **Step 3: The handler goes**

The action and its hook:

In `includes/class-wpcpm-mail.php`, replace:

```php
	 */
	const RUN_OPTION = 'wpcpm_invite_run';

	/** Admin-post action for the sample send. */
	const ACTION_TEST = 'wpcpm_send_test_mail';

	/** Admin-post action that cancels whatever is left of a bulk invite. */
	const ACTION_STOP = 'wpcpm_stop_invites';

```

with:

```php
	 */
	const RUN_OPTION = 'wpcpm_invite_run';

	/** Admin-post action that cancels whatever is left of a bulk invite. */
	const ACTION_STOP = 'wpcpm_stop_invites';

```

In `includes/class-wpcpm-mail.php`, replace:

```php
	public static function init() {
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'welcome_email' ), 10, 3 );
		add_action( self::CRON_QUEUE, array( __CLASS__, 'drain_queue' ) );
		add_action( 'admin_post_' . self::ACTION_TEST, array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_' . self::ACTION_STOP, array( __CLASS__, 'handle_stop' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS, array( __CLASS__, 'handle_dismiss' ) );

```

with:

```php
	public static function init() {
		add_filter( 'wp_new_user_notification_email', array( __CLASS__, 'welcome_email' ), 10, 3 );
		add_action( self::CRON_QUEUE, array( __CLASS__, 'drain_queue' ) );
		add_action( 'admin_post_' . self::ACTION_STOP, array( __CLASS__, 'handle_stop' ) );
		add_action( 'admin_post_' . self::ACTION_DISMISS, array( __CLASS__, 'handle_dismiss' ) );

```

and the handler, the class's last method:

In `includes/class-wpcpm-mail.php`, delete the method `handle_test()`, its docblock and the blank line above it included.

- [ ] **Step 4: The Mail tab**

Only this screen's handlers queue its notices now:

In `includes/class-wpcpm-settings-screen.php`, replace:

```php

	/**
	 * Queue the outcome a notice of this screen says, and the scope it names, always together: every
	 * writer of the `settings` channel queues through here, this screen's handlers and the Mail tab's
	 * sample invitations (`WPCPM_Mail::handle_test()`).
	 *
	 * The scope is set on every outcome, to nothing for an outcome that names none, a refusal, the
	 * lists read again or a sample sent: a scope still queued from a notice that was never drawn would
	 * otherwise name the next save.
	 *
	 * @param string $outcome A key of `settings_messages()`.
	 * @param string $scope   The scope saved: a key of `WPCPM_Settings::scopes()`, or ''.
```

with:

```php

	/**
	 * Queue the outcome a notice of this screen says, and the scope it names, always together: every
	 * writer of the `settings` channel queues through here, this screen's handlers alone.
	 *
	 * The scope is set on every outcome, to nothing for an outcome that names none, a refusal or the
	 * lists read again: a scope still queued from a notice that was never drawn would otherwise name
	 * the next save.
	 *
	 * @param string $outcome A key of `settings_messages()`.
	 * @param string $scope   The scope saved: a key of `WPCPM_Settings::scopes()`, or ''.
```

and the two sample outcomes go:

In `includes/class-wpcpm-settings-screen.php`, replace:

```php
		return array(
			'saved'       => array( 'success', $saved ),
			'refused'     => array( 'error', __( 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.', 'wpcredits-program-manager' ) ),
			'test-sent'   => array( 'success', __( 'The sample invitation is on its way to your own address.', 'wpcredits-program-manager' ) ),
			'test-failed' => array( 'error', __( 'The sample could not be sent. Whatever handles mail on this site refused it, so a real invitation would not arrive either.', 'wpcredits-program-manager' ) ),
			'log-cleared' => array( 'success', __( 'The mail log is empty.', 'wpcredits-program-manager' ) ),
			'lists-read'  => array( 'success', __( 'The lists were read again.', 'wpcredits-program-manager' ) ),
		);
```

with:

```php
		return array(
			'saved'       => array( 'success', $saved ),
			'refused'     => array( 'error', __( 'Nothing was saved: the form asked to save settings this screen does not have. Reload the page and save again.', 'wpcredits-program-manager' ) ),
			'log-cleared' => array( 'success', __( 'The mail log is empty.', 'wpcredits-program-manager' ) ),
			'lists-read'  => array( 'success', __( 'The lists were read again.', 'wpcredits-program-manager' ) ),
		);
```

In `includes/class-wpcpm-settings-screen.php`, replace:

```php
	 * The notice a settings save left for the screen it went back to, naming what it saved: this
	 * screen, or a tool's own, whose Settings section saves through the same handler
	 * (`WPCPM_Tool::render_settings()`); or the notice another outcome of this screen left, a
	 * refusal, the lists read again or a sample invitation. Read once, as every flash is.
	 */
	public static function render_outcome_notice() {
		$status   = (string) WPCPM_Flash::take( 'settings' );
```

with:

```php
	 * The notice a settings save left for the screen it went back to, naming what it saved: this
	 * screen, or a tool's own, whose Settings section saves through the same handler
	 * (`WPCPM_Tool::render_settings()`); or the notice another outcome of this screen left, a
	 * refusal or the lists read again. Read once, as every flash is.
	 */
	public static function render_outcome_notice() {
		$status   = (string) WPCPM_Flash::take( 'settings' );
```

The settings' scopes say why the Mail tab has none, which was its forms of their own:

In `includes/class-wpcpm-settings.php`, replace:

```php
	 * setting is in exactly one scope, and the Mail tab, whose controls are forms of their own, has
	 * none (bin/test-settings.php holds both). The tables and columns no screen drew sit with the
```

with:

```php
	 * setting is in exactly one scope, and the Mail tab, which saves nothing, has none
	 * (bin/test-settings.php holds both). The tables and columns no screen drew sit with the
```

The Invitations section says how many are waiting and links the templates, through the registry as Recent mail finds the Log:

In `includes/class-wpcpm-settings-screen.php`, replace the method `render_mail_card()`, its docblock included, with:

```php
	/**
	 * The Mail tab: what is waiting to be sent, where the emails' wording is, and what has gone out.
	 *
	 * No Save: nothing here is a setting. What each invitation says, and a test of it sent to
	 * yourself, are in the Emails tool, on its Templates tab, since 1.122.19; the tab's sample
	 * buttons went with that.
	 */
	private function render_mail_card() {
		$this->section_heading(
			__( 'Invitations', 'wpcredits-program-manager' ),
			__( 'What each invitation says, and a test of it sent to your own address, are in WPCredits Program > Tools > Emails, on its Templates tab.', 'wpcredits-program-manager' ),
			'invitations-waiting-to-be-sent'
		);

		$queued = WPCPM_Mail::queued();

		if ( $queued ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %s: number of invitations. */
						_n(
							'%s invitation is waiting to be sent. They go out a few at a time in the background.',
							'%s invitations are waiting to be sent. They go out a few at a time in the background.',
							$queued,
							'wpcredits-program-manager'
						),
						number_format_i18n( $queued )
					)
				)
			);
		}

		// The Templates tab, whichever way the tool is reached, as Recent mail finds the Log.
		$tool = WPCPM_Tools::get( 'emails' );

		printf(
			'<p><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( add_query_arg( 'tab', 'templates', $tool ? $tool->admin_url() : admin_url( 'admin.php?page=wpcpm-tool-emails' ) ) ),
			esc_html__( 'Open the email templates', 'wpcredits-program-manager' )
		);

		$this->render_mail_log();
	}
```

- [ ] **Step 5: The guide's part on the Mail tab, and the built guide**

In `docs/sections/31-admin-settings.md`, replace:

```markdown

### The Mail tab

Two sections, headed on the screen **Invitations** and **Recent mail**. No settings and no Save: each
button is a form of its own.

#### Sending yourself an invitation

The student, mentor, institution and sponsor invitations say different things, so you can send
yourself any of the four before real people read it, each with a button of its own: **Email me the
student invitation** and its three siblings. The sample goes to your own address. Above the
buttons, how many invitations are still waiting to be sent, when any are; they go out a few at a
time in the background.

#### Recent mail

```

with:

```markdown

### The Mail tab

Two sections, headed on the screen **Invitations** and **Recent mail**. No settings and no Save:
each points to the **Emails** tool, where the emails are edited and their record is kept.

#### Invitations waiting to be sent

How many invitations are still waiting to be sent, when any are; they go out a few at a time in the
background. What each invitation says, and a test of it sent to your own address, are in
**WPCredits Program > Tools > Emails**, on its **Templates** tab, which **Open the email templates**
opens. Until 1.122.19 this section had a button for each of the four invitations that emailed you a
sample; **Send me a test** on each template's editor does that now, with the invitation as it is
saved.

#### Recent mail

```

The forms that lock at the first press name the editor's three buttons in the sample buttons' place:

In `docs/sections/30-admin-wpadmin.md`, replace:

```markdown
application, on the Sponsors and Institutions screens; on the Sponsors screen, the offer and code
forms, a sponsor's **Attach account**, **Remove** and posting switch, and the agreement forms; on
the Institutions screen, the signed agreement's upload and withdrawal and the semester report
buttons; the test invitations on **Settings > Mail**; and the Track Builder's **Create the track**
and **Make the copy**. The Sponsors screen's sync buttons lock without changing their words. Every
other form works as it always has, searching, filtering, a list's **Apply** and **Screen Options**
among them.

```

with:

```markdown
application, on the Sponsors and Institutions screens; on the Sponsors screen, the offer and code
forms, a sponsor's **Attach account**, **Remove** and posting switch, and the agreement forms; on
the Institutions screen, the signed agreement's upload and withdrawal and the semester report
buttons; **Save**, **Send me a test** and **Restore the original** in
**WPCredits Program > Tools > Emails**; and the Track Builder's **Create the track** and
**Make the copy**. The Sponsors screen's sync buttons lock without changing their words. Every
other form works as it always has, searching, filtering, a list's **Apply** and **Screen Options**
among them.

```

Run: `php bin/build-docs.php`
Expected: a line per guide and `Done.`; `docs/administrators.md` and `docs/build/administrators.html` change, and the built guide holds `id="invitations-waiting-to-be-sent"`.

- [ ] **Step 6: Run the tests to verify they pass**

Run, one per command: `php bin/test-settings.php`, `php bin/test-mail.php`, `php bin/test-mail-catalog.php`, `php bin/test-submit-guard.php`, `php bin/test-handlers.php`, `php bin/test-admin-menu.php`, `php bin/check-spelling.php`
Expected: `ALL PASS` (its check that every section's anchor is an id of guide 31's own part of the built guide reads the new one); `ALL PASS`; `ALL PASS (21 checks)`; `ALL PASS`; `ALL HANDLERS REACHED A NORMAL OUTCOME`; `ALL PASS`; `... US English throughout.`

- [ ] **Step 7: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 8: Commit**

```bash
git add includes/class-wpcpm-mail.php includes/class-wpcpm-settings-screen.php includes/class-wpcpm-settings.php bin/test-settings.php bin/test-handlers.php bin/test-mail.php bin/test-mail-catalog.php bin/test-submit-guard.php docs/
git commit -m "Emails E2: Settings > Mail without its sample buttons; Send me a test is on each template"
```

---

### Task 10: The guide, the version, the changelog and the template

**Files:**
- Modify: `docs/sections/32-admin-tools.md` (the Emails part: its tabs, Templates, the editor, Signature and footer, Settings, Log)
- Modify: `docs/sections/31-admin-settings.md`, `docs/sections/30-admin-wpadmin.md` (four tools keep settings of their own)
- Modify: `bin/test-settings.php` (its check of the guides' tools with settings reads four)
- Regenerate: `docs/*.md` and `docs/build/*.html` with `php bin/build-docs.php`
- Modify: `wpcredits-program-manager.php` (`Version:` at line 6, `WPCPM_VERSION` at line 22), `readme.txt` (`Stable tag` at line 7, the 1.122.19 entry above 1.122.18's)
- Regenerate: `languages/wpcredits-program-manager.pot` with `sh bin/make-pot.sh`

**Interfaces:**
- Consumes: the strings Tasks 2 to 9 added, which the template collects.
- Produces: nothing new in code; the guide, version 1.122.19, its changelog entry and the template.

- [ ] **Step 1: The guide's Emails part, and the tools that keep settings**

Every suite is plain PHP that prints an `ok` or `FAIL` line for each check through its `ck()` helper, ends on a count, and exits non-zero on a failure. The helper and the two closing lines, as a new suite writes them (an existing suite keeps its own, whose arguments come in the same order: the label, what was got, what was wanted):

```php
$fails = 0;
$total = 0;

function ck( $label, $got, $want ) {
	global $fails, $total;

	++$total;

	if ( $got === $want ) {
		printf( "ok   %s\n", $label );
		return;
	}

	++$fails;
	printf( "FAIL %s\n     got:  %s\n     want: %s\n", $label, var_export( $got, true ), var_export( $want, true ) );
}
```

```php
printf( "\n%s (%d checks)\n", $fails ? sprintf( '%d FAILURE(S)', $fails ) : 'ALL PASS', $total );

exit( $fails ? 1 : 0 );
```

The guide calls the menu path "WPCredits Program > Tools > Emails", as it calls every tool's, and says **Area**, never module. Two changes in `docs/sections/32-admin-tools.md`:

The part opens on the tool's three tabs, then Templates, Signature and footer, Settings, and the Log, whose text is unchanged but for its first sentence and the Test mark:

In `docs/sections/32-admin-tools.md`, replace:

```markdown

### Emails

**WPCredits Program > Tools > Emails.** A record of every email the site sends, kept for 30 days:
the plugin's own, WordPress's (a password reset, for example) and the Two Factor plugin's sign-in
codes. Use it to answer "did they get the email?".

Each row is one recipient of one email: when it was sent, in the site's time zone; the address as
plain text, and under it the person's name when the address belonged to an account at the time,
```

with:

```markdown

### Emails

**WPCredits Program > Tools > Emails.** The plugin's emails you can edit, by area. One you have not
edited is sent as the plugin ships it. Every email the plugin sends ends with the signature and
footer. The tool also keeps a record of every email the site sends, for 30 days. Three tabs,
**Templates**, **Signature and footer** and **Log**; the tool's own address opens Templates.

#### Templates

The plugin's emails, grouped by area. In this version the area is **Invitations**: the student's,
the mentor's and the sponsor's invitation, and the institution's two, one for an institution whose
Collaboration Agreement is still to be signed and one for an institution whose agreement is on file.
Each row names the email, who receives it and what sends it, its state, and who edited it last and
when. The states:

- **Shipped text**: the email is sent as the plugin ships it, which is word for word what it said
  before 1.122.19.
- **Edited**: it is sent as an Administrator wrote it, to everybody, in that wording.
- **Edited, and the shipped text has changed since**: an update changed the shipped text after the
  edit was saved. The edit is still the one sent, and its editor shows the new shipped text beside
  it, to compare. Saving your edit again clears this state.
- **Edit set aside**: the edit uses a placeholder the email no longer has, lacks one it now needs, or
  has one in its subject that goes in the body, so the shipped text is sent until the edit is saved
  again; the state says which placeholder.

**Edit** opens the email's editor: a **Subject** and a **Body**, the email's placeholders, each with
what it means and an example, and a **Preview** of the email filled with the examples and ending
with the signature and footer. A placeholder, such as `{set_password_link}`, is a word in braces that
is replaced by its value as the email is sent. A sentence whose placeholders are all empty is left
out, and so is a line left with nothing on it, with the line ending in a colon above it: an
invitation with no dashboard page published leaves out the line that would name it, as it always
did. A sentence ends at a full stop followed by a space; a question or exclamation mark does not end
one. So in "Thanks! Write to {program_contact}." with no contact address set, the whole of it goes,
"Thanks!" with it, and in "e.g. " the full stop ends a sentence.

- **Save** stores the subject and the body. It is refused, nothing is stored, and the editor comes
  back with what you typed, when a box is empty, a required placeholder is missing (every invitation
  needs `{set_password_link}`, the link that sets the password, in its body, never in the subject:
  a subject is not a link anybody can follow, and the Log keeps it), a placeholder is not one of the
  email's (a misspelled one is named), WordPress would remove part of the text, or the subject is
  longer than 200 characters or the body longer than 10,000. A save of the shipped text as it stands
  stores nothing, so the email stays shipped text.
- **Send me a test**, beside **Save**, sends the email as it is saved, filled with the example values
  and with "[Test]" before its subject, to your own address and to nobody else. The Log marks it
  **Test**. Save what you typed first: pressed with typing not saved yet, it sends nothing, and the
  editor comes back with your typing and says so.
- **Restore the original** is there once the email is edited. It asks first, then drops the edit,
  and the shipped text is sent from then on.

#### Signature and footer

The one block every email the plugin sends ends with, after a blank line and a line holding `-- `.
It is not added to the emails WordPress and the Two Factor plugin send themselves. Its placeholders
are `{site_name}`; `{dashboard_link}`, the recipient's dashboard, which is empty for somebody with
no account, such as an applicant, and for an email that names the dashboard already, as every
invitation does, so its line is left out and no email names the address twice; and
`{program_contact}`, the address in **Contact address in emails**, which is empty until it is set,
so its sentence is left out. It ships as three lines, in the wording the owner approved:

- `WordPress Credits, WordPress Education`
- `{dashboard_link}`
- `This email comes from the WordPress Credits program. Questions? Write to {program_contact}.`

The tab edits it with the editor every template has, without a subject, with **Save**, **Send me a
test** (a short email that ends with the block) and **Restore the original**. An empty block is not
saved, since every email ends with it.

#### Settings

At the top of the Signature and footer tab, the tool's one setting, **Contact address in emails**:
a mailbox the Administrators read, which the footer names for questions. Left empty, the footer
leaves that sentence out. An address that is not one is not saved, and the tab says so.

#### Log

A record of every email the site sends, kept for 30 days: the plugin's own, WordPress's (a password
reset, for example) and the Two Factor plugin's sign-in codes. Use it to answer "did they get the
email?".

Each row is one recipient of one email: when it was sent, in the site's time zone; the address as
plain text, and under it the person's name when the address belonged to an account at the time,
```

and the Test mark names the tests that make it now:

In `docs/sections/32-admin-tools.md`, replace:

```markdown
- **Not confirmed**: another plugin took the email over before WordPress could say what happened,
  or the request ended, with a fatal error for example, before it did.

A **Test** mark beside the status shows a sample you sent yourself from Settings > Mail. It is not
a fourth status: the sample has one of the three as well, and the **Status** filter has a **Test**
choice that lists the samples.

To find an email, type part of an address, a name or a subject into the search box, or narrow the
list by **Area**, **Recipient type**, **Status**, **Email** or **When** (today, the last 7 days, the
```

with:

```markdown
- **Not confirmed**: another plugin took the email over before WordPress could say what happened,
  or the request ended, with a fatal error for example, before it did.

A **Test** mark beside the status shows a test you sent yourself with **Send me a test** (or, before
1.122.19, a sample from Settings > Mail). It is not a fourth status: the test has one of the three
as well, and the **Status** filter has a **Test** choice that lists the tests.

To find an email, type part of an address, a name or a subject into the search box, or narrow the
list by **Area**, **Recipient type**, **Status**, **Email** or **When** (today, the last 7 days, the
```

Guide 31 says four tools keep settings of their own:

In `docs/sections/31-admin-settings.md`, replace:

```markdown
as it always was, and a sentence under it says why. A value saved that its list does not hold is
still offered, after the list and marked *(current)*, so a Save never drops it.

Three tools keep settings of their own, the **Mentor Status Checker**, the **Student Duplicate Finder**
and **Need help?**, and each keeps them on its own screen, in a **Settings** section at the top with a
Save of its own, rather than on this one. The Connection tab's first line names the three, each a
link to its Settings section, and the next part of this guide, on the tools, says what each of those
settings does.

### The Connection tab

Its first line names the three tools that keep their settings on their own screens. Then two
sections, **Airtable** and **Tables**, the tab's **Save settings**, and under it a button of its own,
**Read the lists from Airtable again**.

```

with:

```markdown
as it always was, and a sentence under it says why. A value saved that its list does not hold is
still offered, after the list and marked *(current)*, so a Save never drops it.

Four tools keep settings of their own, the **Mentor Status Checker**, the **Student Duplicate Finder**,
**Need help?** and **Emails**, and each keeps them on its own screen, in a **Settings** section with a
Save of its own, rather than on this one: at the top of the screen, or for Emails at the top of its
**Signature and footer** tab. The Connection tab's first line names the four, each a link to its
Settings section, and the next part of this guide, on the tools, says what each of those settings
does.

### The Connection tab

Its first line names the four tools that keep their settings on their own screens. Then two
sections, **Airtable** and **Tables**, the tab's **Save settings**, and under it a button of its own,
**Read the lists from Airtable again**.

```

and so does the part on the plugin in wp-admin:

In `docs/sections/30-admin-wpadmin.md`, replace:

```markdown
The **Tools** submenu lists the parts of the program that are run and configured on their own
rather than belonging to one audience - currently **Header notices**, **Emails**, **Need help?**, the
**Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
behind an *Open tool* button. Three of them, the **Mentor Status Checker**, the
**Student Duplicate Finder** and **Need help?**, keep their settings in a **Settings** section at the
top of that screen, rather than on the Settings screen.
```

with:

```markdown
The **Tools** submenu lists the parts of the program that are run and configured on their own
rather than belonging to one audience - currently **Header notices**, **Emails**, **Need help?**, the
**Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
behind an *Open tool* button. Four of them, the **Mentor Status Checker**, the
**Student Duplicate Finder**, **Need help?** and **Emails**, keep their settings in a **Settings**
section on that screen, rather than on the Settings screen.
```

`bin/test-settings.php` holds guide 31 to the tools that keep settings and guide 32 to a Settings section for each; it reads four now:

In `bin/test-settings.php`, replace:

```php

echo "\n=== The guides say where each tool's settings are ===\n";

// Guide 31 is the Settings screen's: it has no Tools tab, and says where the three tools' settings
// went. Guide 32 is the tools': each of the three has a Settings section there, as on its screen.
$guide_31 = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/31-admin-settings.md' );
$guide_32 = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/32-admin-tools.md' );

$tool_parts = array();

foreach ( array( 'Mentor Status Checker', 'Student Duplicate Finder', 'Need help?' ) as $tool_name ) {
	$part                     = preg_match( '/^### ' . preg_quote( $tool_name, '/' ) . '\n(.*?)(?=^### |\z)/ms', $guide_32, $found ) ? $found[1] : '';
	$tool_parts[ $tool_name ] = 1 === preg_match( '/^#### Settings$/m', $part );
}

ck( 'guide 31 has no Tools tab and says the three tools keep their settings on their own screens, and guide 32 gives each of the three a Settings section',
    array( 1 === preg_match( '/^### The Tools tab$/m', $guide_31 ), false !== strpos( $guide_31, 'eighth' ), 1 === preg_match( '/Mentor Status Checker.*Student Duplicate Finder.*Need help\?.*own screen/s', $guide_31 ), $tool_parts ),
    array( false, false, true, array_fill_keys( array( 'Mentor Status Checker', 'Student Duplicate Finder', 'Need help?' ), true ) ) );

echo "\n=== airtable_record_url() ===\n";
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array_merge( WPCPM_Settings::defaults(), array( 'base_id' => 'appTEST', 'students_table' => 'tbl STU', 'reports_table' => '' ) );
```

with:

```php

echo "\n=== The guides say where each tool's settings are ===\n";

// Guide 31 is the Settings screen's: it has no Tools tab, and says where the four tools' settings
// went. Guide 32 is the tools': each of the four has a Settings section there, as on its screen.
$guide_31 = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/31-admin-settings.md' );
$guide_32 = (string) file_get_contents( WPCPM_PLUGIN_DIR . 'docs/sections/32-admin-tools.md' );

$tool_parts = array();

foreach ( array( 'Mentor Status Checker', 'Student Duplicate Finder', 'Need help?', 'Emails' ) as $tool_name ) {
	$part                     = preg_match( '/^### ' . preg_quote( $tool_name, '/' ) . '\n(.*?)(?=^### |\z)/ms', $guide_32, $found ) ? $found[1] : '';
	$tool_parts[ $tool_name ] = 1 === preg_match( '/^#### Settings$/m', $part );
}

ck( 'guide 31 has no Tools tab and says the four tools keep their settings on their own screens, and guide 32 gives each of the four a Settings section',
    array( 1 === preg_match( '/^### The Tools tab$/m', $guide_31 ), false !== strpos( $guide_31, 'eighth' ), 1 === preg_match( '/Mentor Status Checker.*Student Duplicate Finder.*Need help\?.*Emails.*own screen/s', $guide_31 ), $tool_parts ),
    array( false, false, true, array_fill_keys( array( 'Mentor Status Checker', 'Student Duplicate Finder', 'Need help?', 'Emails' ), true ) ) );

echo "\n=== airtable_record_url() ===\n";
$GLOBALS['opts'][ WPCPM_Settings::OPT_NAME ] = array_merge( WPCPM_Settings::defaults(), array( 'base_id' => 'appTEST', 'students_table' => 'tbl STU', 'reports_table' => '' ) );
```

Run: `php bin/build-docs.php` (the plain build, so the committed guides keep their relative image paths), then, one per command, `php bin/test-cli.php`, `php bin/check-spelling.php`, `php bin/test-settings.php`, `php bin/test-admin-menu.php`
Expected: the build prints a line per guide and `Done.`; `ALL PASS (14 checks)`; `... US English throughout.`; `ALL PASS`; `ALL PASS` (no guide section says module, and every menu path steps from WPCredits Program with ">").

- [ ] **Step 2: The version and the entry**

In `wpcredits-program-manager.php`, the header (line 6):

In `wpcredits-program-manager.php`, replace:

```php
 * Version:           1.122.18
```

with:

```php
 * Version:           1.122.19
```

and the constant (line 22):

In `wpcredits-program-manager.php`, replace:

```php
define( 'WPCPM_VERSION', '1.122.18' );
```

with:

```php
define( 'WPCPM_VERSION', '1.122.19' );
```

In `readme.txt`, the stable tag (line 7):

In `readme.txt`, replace:

```text
Stable tag: 1.122.18
```

with:

```text
Stable tag: 1.122.19
```

and the entry above 1.122.18's:

In `readme.txt`, replace:

```text

== Changelog ==

= 1.122.18 =

* WPCredits Program > Tools > Emails: a record of every email the site sends, kept for 30 days. Each row is one recipient of one email: when, to whom (with the person's name when the address belongs to an account), the recipient type, the area that sent it, which email it was, its subject, and whether WordPress handed it to the mail server, failed, or did not say. Search by address, name or subject, and filter by area, recipient type, status, email and time; the Email filter is one field to type into and pick from. It covers the plugin's emails, WordPress's own and the Two Factor plugin's sign-in codes, and never keeps an email's text, attachments or anything secret.
```

with:

```text

== Changelog ==

= 1.122.19 =

* WPCredits Program > Tools > Emails has three tabs: Templates, Signature and footer, and Log. The tool's own address opens Templates; every link to the Log keeps working.
* Templates: the plugin's emails as text an Administrator edits, grouped by area, each with who receives it, what sends it, its state (Shipped text, Edited, Edited and the shipped text has changed since, or an edit set aside, with why) and who edited it last and when. In this version they are the Invitations: the student's, the mentor's and the sponsor's, and the institution's two, for an institution whose Collaboration Agreement is still to be signed and for one whose agreement is on file. The shipped text is word for word what the invitations said before.
* The editor: a Subject and a Body, the email's placeholders with what each means and an example, and a preview filled with the examples. Save refuses, stores nothing and gives back what was typed, when a box is empty, a required placeholder is missing (every invitation needs its set-password link, in its body, never in the subject), a placeholder is not the email's, WordPress would remove part of the text, or the subject passes 200 characters or the body 10,000. Send me a test, beside Save, sends the email as it is saved, with "[Test]" before its subject, to the Administrator who pressed and to nobody else, and the Log marks it Test; pressed with typing not saved yet, it sends nothing and keeps the typing. Restore the original asks first, then drops the edit. An edit that an update no longer fits is set aside and the shipped text sent.
* Signature and footer: every email the plugin sends now ends with one block, after a line holding "-- ", which names the program, the recipient's dashboard (left out for somebody with no account, and for an email that names it already, as an invitation does) and, once it is set, the program's contact address. It is edited, tested and restored on its own tab, an empty one is not saved, and it is never added to WordPress's or the Two Factor plugin's emails.
* A new setting, Contact address in emails, at the top of the Signature and footer tab: the address the footer names for questions. It ships empty, and the footer leaves that sentence out until it is set.
* Settings > Mail: the four "Email me the ... invitation" buttons are gone; Send me a test on each template's editor takes their place. The Invitations section now says how many invitations are waiting and links the templates; Recent mail is unchanged.
* Invitations: a "%" in the site's name, or in a subject an Administrator writes, no longer stops the invitation going out. WordPress reads the subject as a format a second time, and the "%" made that fail.
* For developers: `WPCPM_Mail_Templates` (includes/mail/class-wpcpm-mail-templates.php, with each area's emails in includes/mail/templates/) holds every editable email by its catalog id, and `render()`, `add_footer()` and `send_test()` are its doors; a placeholder marked `body_only`, as the set-password link is, goes in the body alone; the edits are kept in the `wpcpm_mail_templates` option, not autoloaded, and deleted on uninstall. `WPCPM_Mail_Catalog` gains `invite-institution-settled` and `footer-test`; the four `test-*` ids stay, to name the rows they left in the Log. `WPCPM_Mail::send()`'s builder may now return `test` and `footer_values`, and `WPCPM_Mail::take_test()` hands the test mark to the capture. `WPCPM_Tool::settings_url()` says where a tool's Settings section is. Gone: `WPCPM_Mail::handle_test()` and `WPCPM_Mail::ACTION_TEST`.

= 1.122.18 =

* WPCredits Program > Tools > Emails: a record of every email the site sends, kept for 30 days. Each row is one recipient of one email: when, to whom (with the person's name when the address belongs to an account), the recipient type, the area that sent it, which email it was, its subject, and whether WordPress handed it to the mail server, failed, or did not say. Search by address, name or subject, and filter by area, recipient type, status, email and time; the Email filter is one field to type into and pick from. It covers the plugin's emails, WordPress's own and the Two Factor plugin's sign-in codes, and never keeps an email's text, attachments or anything secret.
```

- [ ] **Step 3: The template**

Run: `sh bin/make-pot.sh`
Expected: `Success: POT file successfully generated.` The new strings arrive in the template, each with its translators comment, among them the footer's three lines as one string, `Nothing was saved: this email needs %s.`, `Nothing was saved: %s goes in the body, never in the subject.`, `Nothing was sent: what you typed is not saved yet. Save it first, then send the test.`, `[Test] %s` and `Contact address in emails`; the five labels above a dashboard's address and the two sentences on the link and the username carry the comments Task 2 gave them; the four `Email me the ... invitation` strings and the sample's text leave it.

- [ ] **Step 4: Run the release's suites**

Run, one per command: `php bin/test-tooling.php`, `php bin/check-spelling.php`, `php bin/test-fixtures.php`, `php bin/test-admin-menu.php`, `php bin/test-mail-templates.php`, `php bin/test-emails-templates.php`, `php bin/test-invitation-text.php`
Expected: each passes. Then search the release's diff for dashes, with perl, since macOS's grep has no `-P` and would print nothing whatever the diff holds: `git diff main -- . | perl -CSD -ne 'print if /[\x{2013}\x{2014}]/'` prints nothing.

- [ ] **Step 5: Run the whole battery**

Run, from the plugin root, every suite in its own process, then the references check:

```bash
for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done
php bin/check-references.php
```

Expected: the loop prints nothing, and the last line of the references check ends `- all resolve`.

- [ ] **Step 6: Commit**

```bash
git add docs/ bin/test-settings.php wpcredits-program-manager.php readme.txt languages/wpcredits-program-manager.pot
git commit -m "1.122.19: the email templates, the signature and footer, Send me a test; the guide, the version, the changelog and the template"
```

---

## After the tasks (the controller's, not an implementer's)

1. The whole-release review, one fix wave, a scoped re-review.
2. The gate on a frozen copy: no conflict markers, `php -l` on every file, `php bin/check-references.php`, the whole battery (`for f in bin/test-*.php; do php "$f" > /dev/null 2>&1 || echo "FAIL $f"; done`, printing nothing), `sh bin/check-standards.sh` with no more errors than 1.122.18 (2), and `php bin/check-dead-annotations.php` with none dead, all green. `php bin/test-invitation-text.php` passing is the word-for-word proof: every invitation is the recording of 1.122.18, then the footer.
3. The zip from a clean clone (`bash bin/build ~/GitHub/wpcredits-program-manager.zip`).
4. The local copy (localhost:8883): snapshot the database; install; then try, as an Administrator:
   - WPCredits Program > Tools > Emails opens on **Templates**: the Invitations, five rows, each Shipped text and Not edited, each Edit opening its editor; the **Log** tab and every old link to it (Settings > Mail's **Open the email log**, the Administrator Dashboard's health card) still land on the Log.
   - On the student invitation: the preview reads as today's invitation, then a blank line, `-- ` and the footer in the owner's wording ("This email comes from the WordPress Credits program."), with no dashboard line, since the invitation names the Student Report Card's address in its last lines already, and no "Questions?" sentence. **Send me a test**, beside **Save**: one email, to your own address, "[Test] " before the subject; in the Log, **Not confirmed** (the copy's safety plugin takes every email over through `pre_wp_mail`), marked **Test**, as "Invitation: student", area Invitations.
   - Save refused, each time with its sentence and with what you typed still in the boxes: the `{set_password_link}` line deleted; `{set_pasword_link}`; `{studnet_name}` in the subject; `{set_password_link}` copied into the subject ("goes in the body, never in the subject"); "Kids <12 free, adults >18 pay" in the body; the subject cleared. Then type a sentence into the body and press **Send me a test** without saving: nothing is sent, the editor says "Nothing was sent: what you typed is not saved yet. Save it first, then send the test." and your sentence is still in the box. Then a real edit (a sentence added): Save, the state reads Edited with your name and the time, Send me a test sends the edit, the Templates list says Edited; then **Restore the original**, which asks first, and the state is Shipped text again.
   - Invite a test account of each kind (Students, Mentors, Institutions with and without an agreement on file, Sponsors, as the copy allows) and read each invitation in the copy's safety log: today's words, then the footer with no second copy of the dashboard's address, the institution's two named apart in the Log.
   - **Signature and footer**: Contact address in emails holds nothing; type "programs@localhost" and Save (refused, the old value kept, the tab says why: the browser takes the address, and the site refuses one with no dot in its domain); type `programs@example.test` and Save (the preview's footer gains "Questions? Write to programs@example.test."); clear it again, since the release ships it empty. Empty the footer's box and Save (refused: "Nothing was saved: the signature and footer is empty."). Edit the footer, Send me a test (a short email ending with it, "Test of the signature and footer" in the Log, area Other), then Restore the original.
   - Settings > Mail: no sample buttons; **Open the email templates** and **Open the email log** land where they say; the waiting count shows when an invitation is queued.
   - An email to an applicant (a question about an institution application): the footer has no dashboard line.
   - At 375px: the tabs, the Templates table and the editor wrap without the page scrolling sideways.
   Put the database back.
5. The owner reads the footer on the local copy before anything is deployed (ruling PR-2): as it ends an invitation, an "Application declined" email (decline an institution application on the copy) and an Administrator's notice ("New institution application, to Administrators"), each read in the copy's safety log. The wording is the one the owner approved on 10 October 2026, so this confirms it as recipients will read it rather than choosing it; the deploy waits on the owner's yes, and a change asked for here is a wording commit, with the four suites that pin the footer, before the deploy.
6. The mirror push and the live deploy on the owner's yes, with the usual recipe, the live version read first (`wp plugin get wpcredits-program-manager --field=version` reads 1.122.18). After the install: the Templates tab lists the five invitations as Shipped text, `wp option get wpcpm_mail_templates` has nothing stored, Contact address in emails is empty, and one Send me a test arrives with "[Test] " and the footer and shows in the Log as **Handed to the mail server**, marked Test. That test is pressed from an Administrator account whose address is administrator@example.test, checked first with `wp user get <id> --field=user_email` (ruling PR-10: every test mail on the site goes to that address and to no other). Then republish the administrators' guide (page 560) from a `--base=` build.
7. Open for the owner: the templates' names, which are this design's suggestions. Settled on 10 October 2026: the footer's wording (shipped as approved), and Contact address in emails, which ships empty, so its sentence is left out until the Administrators name a mailbox.
