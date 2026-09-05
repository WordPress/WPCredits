# Dashboard Consistency Pass Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the five dashboards read as one product: the same heading treatment for every run of fields on the Student Report Card, the Mentor Report Card's notes and groups aligned with its rows, the Administrator Dashboard's tables and request cards spaced like the rest, the Institution Dashboard's student rows shaped like the Mentor Report Card's, one button size everywhere, and the colleague-invite flow finally shown on the institution's page.

**Architecture:** Plugin 1.94.7 changes three renderers (the report form's lead headings, the institution roster row's avatar, the People card's invite wiring) and one JavaScript insertion point (triage.js's ordering note). Theme 1.21.0 is a set of rule edits in `assets/css/dashboard.css`, each copying a value that already exists on another dashboard (the Institution Dashboard is the reference for spacing, the Mentor Report Card for rows).

**Tech Stack:** PHP 7.4+ WordPress plugin, plain-PHP suites in `bin/`, phpcs via `bash bin/check-standards.sh` (also dashes and US spelling), the theme's `php bin/check-selectors.php` (classes, tokens, dashes), `bash bin/build`.

**Spec:** the owner's six annotated screenshots of 6 September 2026 (in the vault note *Institutions module*, section "Dashboard consistency pass") and the review artifact *WPCredits Dashboard UX Review*. Measured basis (server-side captures at 1400px): the report card's lead headings are 600 14px sentence case with no rule while its sub-headings are 700 13px uppercase with a rule above; the mentor page's ordering note is appended after the list with a border above it; the past-students box is inset differently from the rows above it and touches the section below; its notice is centered in a band; the admin table cells are `vertical-align: top` beside 38px buttons; the request textareas touch their buttons (0px); the institution rows stack name, badge and dates on two lines with no avatar while the mentor rows are one line with a 44px avatar; every `.wpcpm-button` on the Sponsor Dashboard renders at 13px (plugin base 0.9em); the feedback form's selects are the browser's own.

## Global Constraints

- No em or en dashes anywhere (plain hyphens); `check-standards.sh` and `check-selectors.php` fail on one.
- US English in every string a person reads (`bin/check-spelling.php`); full product names: "Student Report Card", "Mentor Report Card", "Institution Dashboard", "Sponsor Dashboard", "Administrator Dashboard".
- Reading text 14px or more, never `--wpc-ink-50`; chips, counts and eyebrows 12px or 13px; every control border `--wpc-control-border`; outlines use `--wpc-brand`, never `--wpc-brand-border`.
- Dashboards look alike: a shared block copies the reference dashboard's values value for value; a block class name belongs to one dashboard's markup only; every class the theme dresses is printed by the plugin (`check-selectors.php`).
- Declarations alphabetical inside a rule; comments explain why; tabs; Yoda conditions.
- Versions: plugin **1.94.7** (`wpcredits-program-manager.php` header and `WPCPM_VERSION`, `readme.txt` Stable tag and a `= 1.94.7 =` entry), theme **1.21.0** (`style.css`, `readme.txt`). Separate git repositories, each committed on its own.
- Every test send or test account uses `maciej@a8c.com`. Nothing destructive against wordpresseducation.org. Deploy, mirror and live verification are the controller's, after the final review and the merge.

---

### Task 1: One heading for every run on the Student Report Card, and dressed selects on the feedback form

**Files:**
- Modify: `includes/modules/class-wpcpm-student-report-form.php` (the `lead` branch of the group loop in `render_body()`)
- Modify: `assets/css/calendar.css` (remove the `.wpcpm-report__lead` rule and its selector in the `+ *` attach rule)
- Modify (theme): `assets/css/dashboard.css` (remove `.wpc-dashboard-page .wpcpm-report__lead`; add the feedback select rule)
- Test: `bin/test-report-form.php`

**Interfaces:**
- Produces: a `lead` prints `<h4 class="wpcpm-report__sub">`, exactly what a `subgroup` prints; the class `wpcpm-report__lead` no longer exists anywhere.

- [ ] **Step 1: Failing tests**

In `bin/test-report-form.php`, every assertion that names `wpcpm-report__lead` (the 1.94.3 lead checks, the Task 8 reflection and Developer Track checks) changes its needle to `<h4 class="wpcpm-report__sub">`, keeping the text and the position logic; add:

```php
ck( 'no heading of a second kind is left', strpos( $edit, 'wpcpm-report__lead' ), false );
```

Run `php bin/test-report-form.php 2>&1 | grep -c "^FAIL"`: the changed checks fail.

- [ ] **Step 2: Implement**

In the loop, `printf( '<h4 class="wpcpm-report__lead">%s</h4>', esc_html( $spec['lead'] ) );` becomes `printf( '<h4 class="wpcpm-report__sub">%s</h4>', esc_html( $spec['lead'] ) );` and the comment above it says why: a run's opening sentence and a lesson's name are the same kind of heading, and the owner wanted one treatment (capitals, the rule above) for both. Remove the `.wpcpm-report__lead` block from `assets/css/calendar.css` and drop `.wpcpm-report__lead + *,` from the attach rule. Remove `.wpc-dashboard-page .wpcpm-report__lead { ... }` and its comment from the theme.

Feedback selects: read `includes/modules/class-wpcpm-student-feedback.php` for the class on the element that wraps the form (`wpcpm-feedback` or the like) and add to the theme, next to the `.wpc-dashboard-page .wpcpm-report input` rule:

```css
/* The feedback form's selects were the browser's own beside the theme's text boxes: the same
   control treatment as every other field (consistency pass, 1.21.0). */
.wpc-dashboard-page .wpcpm-feedback select {
	background: var( --wpc-surface );
	border: 1px solid var( --wpc-control-border );
	border-radius: var( --wpc-radius-control );
	font: 14px/20px var( --wpc-font );
	padding: 6px 8px;
}
```

(with the real wrapper class in place of `.wpcpm-feedback` if it differs).

- [ ] **Step 3: Checks**

`php bin/test-report-form.php 2>&1 | tail -1` (ALL PASS); `bash bin/check-standards.sh 2>&1 | grep -E "FOUND [1-9]|British|Em or en"` (nothing); from the theme root `php bin/check-selectors.php | tail -3` (0 printed by nothing, 0 undefined, no dashes; the check would fail if the theme still dressed the removed class).

- [ ] **Step 4: Commit (plugin and theme)**

```bash
git add -A && git commit -m "Report form: one heading treatment for every run; the feedback selects dressed"
cd ../wpcredits-theme && git add -A && git commit -m "The report card's run headings are one class; the feedback selects dressed"
```

---

### Task 2: The Mentor Report Card's note, header band and past-students box

**Files:**
- Modify: `assets/js/triage.js` (`note()`)
- Modify (theme): `assets/css/dashboard.css` (`.wpc-dash__ordering`, `.wpc-dash__band`, a `.wpcpm-group--past` block, the notice inside it)
- Test: `bin/test-triage.php` if it asserts the note's position (grep `wpc-dash__ordering` in `bin/`); otherwise the JavaScript has no suite and the measurement after deploy is the check.

**Interfaces:**
- Produces: the ordering note is inserted before the list (`list.parentNode.insertBefore( el, list )`), so it reads as the list's introduction under the toolbar; no border above it. The header band's bottom edge meets the first section. The past-students box aligns with the student cards above it (their inset is `margin: 0 20px` on `.wpcpm-mentee__summary` and on the open disclosure) and has 20px below it; its notice is a plain note.

- [ ] **Step 1: triage.js**

In `note( list )`: `list.parentNode.insertBefore( el, list.nextSibling );` becomes `list.parentNode.insertBefore( el, list );` and the docblock's "once, under it" becomes "once, above it: it says how what follows is ordered, which a reader wants before the list, not after".

- [ ] **Step 2: Theme**

`.wpc-dash__ordering { border-top: 1px solid var( --wpc-line-soft ); }` becomes `.wpc-dash__ordering { padding-top: 0; }` (the shared `.wpc-dash__hint, .wpc-dash__ordering` rule keeps `padding: 14px 32px`; the note now sits 14px under the toolbar and 14px above the first group), with the comment reworded: above the list, no rule.

`.wpc-dash__band` (about line 118): add `margin-bottom: 0;` if a margin is set on it (the capture measured 14px below the band); and add, right after, `.wpc-dash__band + .wpcpm-calls { border-top: 0; }` so the band's own bottom border is the one line between header and first section (read the band rule first: it has `border-bottom: 1px solid var( --wpc-line )`).

Past students, in the mentor page's group rules (near `.wpc-dashboard-page .wpcpm-group`):

```css
/* The past-students box lines up with the student cards above it (their inset is 20px) and
   leaves the same room below that a card leaves, instead of touching the section line
   (consistency pass, 1.21.0). */
.wpc-dashboard-page .wpcpm-group--past {
	margin: 0 20px 20px;
}

/* Its notice is a note like every other note on the page, not a centered band. */
.wpc-dashboard-page .wpcpm-group--past .wpcpm-dashboard__empty {
	background: none;
	border-top: 0;
	color: var( --wpc-ink-60 );
	font: 14px/20px var( --wpc-font );
	padding: 12px 12px 4px;
	text-align: left;
}
```

Read the existing `.wpcpm-group--past`, `.wpcpm-group__disclosure` and `.wpcpm-dashboard__empty` rules first; if the disclosure already carries an inset, adjust the margin so the box's outer edges land at the section's 20px inset exactly once.

- [ ] **Step 3: Checks**

`node -e "new Function(require('fs').readFileSync('assets/js/triage.js','utf8'))"` (parses); any `bin/test-triage*.php` suite; theme `php bin/check-selectors.php | tail -3`.

- [ ] **Step 4: Commit (plugin and theme)**

```bash
git add -A && git commit -m "Mentor Report Card: the ordering note introduces the list"
cd ../wpcredits-theme && git add -A && git commit -m "Mentor Report Card: the header band meets the first section, the past-students box lines up"
```

---

### Task 3: The Administrator Dashboard's table rows and request cards

**Files:**
- Modify (theme): `assets/css/dashboard.css` (`.wpc-administrator-page .wpcpm-admin-table td`, `.wpcpm-request__decide`, `.wpcpm-administrator__item`)

**Interfaces:**
- Produces: table cells vertically centered on their buttons; 12px between a request's textarea and its buttons; a hairline and 18px between request cards.

- [ ] **Step 1: Implement**

Next to `.wpc-administrator-page .wpcpm-admin-table th`:

```css
/* A row's text sits on the same line as its button, not at the top of a 55px cell. */
.wpc-administrator-page .wpcpm-admin-table td {
	vertical-align: middle;
}
```

Next to the `.wpc-administrator-page .wpcpm-request__decide textarea` rule:

```css
/* The note field and the buttons under it touched; the same 12px every form on the site puts
   between a control and its button row. */
.wpc-administrator-page .wpcpm-request__decide textarea {
	margin-bottom: 12px;
}

/* One request from the next: a hairline and room, as the institution's people rows have. */
.wpc-administrator-page .wpcpm-administrator__item + .wpcpm-administrator__item {
	border-top: 1px solid var( --wpc-line-soft );
	padding-top: 18px;
}
```

Read the plugin's `assets/css/administrator.css` `.wpcpm-administrator__item` rules first (they set `padding-bottom: 14px` and a `:first-of-type` variant); if they already draw a border between items, change only the spacing so no line is doubled. The `margin-bottom: 12px` merges into the existing textarea rule if one exists (one rule per selector, declarations alphabetical).

- [ ] **Step 2: Check and commit**

`php bin/check-selectors.php | tail -3`; `git add -A && git commit -m "Administrator Dashboard: rows centered on their buttons, requests spaced"`.

---

### Task 4: The Institution Dashboard's student rows shaped like the Mentor Report Card's

**Files:**
- Modify: `includes/modules/class-wpcpm-mentors-dashboard.php` (`render_avatar()` becomes `public static`)
- Modify: `includes/modules/class-wpcpm-institution-roster-view.php` (`render_student()`)
- Modify (theme): `assets/css/dashboard.css` (rules under `.wpc-institution-page .wpcpm-roster__card`)
- Test: `bin/test-institution-roster-view.php`

**Interfaces:**
- Consumes: `WPCPM_Mentors_Dashboard::render_avatar( $username, $email, $name, $size )` (public after this task) and `WPCPM_Mentors_Dashboard::avatar_url()`.
- Produces: each roster row's summary prints, in order, the toggle slot, `<img class="wpcpm-avatar" width="44" height="44">` (when a username or email is known), the name, the preview (dates and mentor) and the badge; on the institution page the identity block is `display: contents` so those become one flex row, the preview fills the middle and the badge sits at the right, one line tall like the mentor page's rows.

- [ ] **Step 1: Failing test**

In `bin/test-institution-roster-view.php`, find the fixture row that renders a current student (a row with a `user_id`) and give it `'username' => 'zhaslankyzy'` (or the key the roster rows use for the WordPress.org username: read `render_student()`'s `$row` and the `cells()` helper; line ~991 reads `$get( 'username' )`) and `'email' => 'maciej@a8c.com'`. Add:

```php
ck( 'a student row carries the same 44px avatar as the mentor page, before the name', preg_match( '#<summary class="wpcpm-mentee__summary"><img class="wpcpm-avatar" src="[^"]*zhaslankyzy[^"]*" srcset="[^"]*" width="44" height="44" alt="[^"]*" title="[^"]*" loading="lazy" decoding="async" /><div class="wpcpm-mentee__identity">#', $html ) === 1, true );
```

(`$html` being the rendered roster the suite already inspects; use its variable name.) A row with neither username nor email prints no `<img>`: assert that on a fixture row without them.

- [ ] **Step 2: Implement**

`render_avatar()` in the mentors dashboard: `private static` becomes `public static`, and its docblock gains one line: shared with the Institution Dashboard's roster, so both pages draw one avatar. In `render_student()`, after `echo '<summary class="wpcpm-mentee__summary">';` and before the identity div:

```php
		// The same portrait the Mentor Report Card draws for this student, at the row size it
		// uses (44px): one student, one face, on both pages (consistency pass, 1.94.7).
		WPCPM_Mentors_Dashboard::render_avatar(
			isset( $row['username'] ) ? (string) $row['username'] : '',
			isset( $row['email'] ) ? (string) $row['email'] : '',
			$name,
			44
		);
```

(`render_avatar()` returns without printing when `avatar_url()` yields ''; check that a blank username and blank email give '' rather than a Gravatar of an empty string; if `avatar_url()` returns the default silhouette for a blank email, guard the call with `if ( '' !== $username || '' !== $email )`.)

Theme, after the `.wpc-dashboard-page .wpcpm-roster__group + .wpcpm-roster__group` rule:

```css
/* The institution's student rows take the mentor page's row: avatar, name, the facts in the
   middle, the badge at the right, one line tall. The markup is the same; only the mentor
   page had the layout (consistency pass, 1.21.0). */
.wpc-institution-page .wpcpm-roster__card > .wpcpm-mentee__summary .wpcpm-mentee__identity {
	display: contents;
}

.wpc-institution-page .wpcpm-roster__card > .wpcpm-mentee__summary .wpcpm-mentee__preview {
	flex: 1;
	min-width: 0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.wpc-institution-page .wpcpm-roster__card > .wpcpm-mentee__summary .wpcpm-badge {
	order: 3;
}

.wpc-institution-page .wpcpm-roster__card > .wpcpm-mentee__summary .wpcpm-mentee__toggle {
	order: 4;
}
```

Read the mentor page's toggle rule first: if the toggle is placed first with `order: -1` there, mirror that (toggle first, then avatar, name, preview, badge) rather than `order: 4`; the aim is the mentor row's order.

- [ ] **Step 3: Checks**

`php bin/test-institution-roster-view.php 2>&1 | tail -1`; `php bin/check-references.php | tail -1` (the new cross-class call resolves); `bash bin/check-standards.sh 2>&1 | grep -E "FOUND [1-9]|British|Em or en"`; theme `php bin/check-selectors.php | tail -3`.

- [ ] **Step 4: Commit (plugin and theme)**

```bash
git add -A && git commit -m "Institution Dashboard: the student rows carry the mentor page's avatar"
cd ../wpcredits-theme && git add -A && git commit -m "Institution Dashboard: student rows laid out like the Mentor Report Card's"
```

---

### Task 5: One button on every dashboard

**Files:**
- Modify (theme): `assets/css/dashboard.css`

**Interfaces:**
- Produces: every `.wpcpm-button` on a dashboard page is `600 14px/20px`, `padding: 6px 14px`, `border-radius: var( --wpc-radius-control )`, unless a more specific rule already gives it a deliberate size (the Student Report Card's course button and report submit at 17px, the hours entry, the notes form, the availability copy).

- [ ] **Step 1: Implement**

Before the first `.wpcpm-button` rule the theme has (line ~355), add:

```css
/* The plugin's button is 0.9em of its container, 12.6px on a 14px card: one size for every
   button on a dashboard page, the size the Administrator Dashboard's decisions already use.
   The Student Report Card's two large buttons keep their own rule below. */
.wpc-dashboard-page .wpcpm-button {
	border-radius: var( --wpc-radius-control );
	font: 600 14px/20px var( --wpc-font );
	padding: 6px 14px;
}
```

Then read every later `.wpcpm-button` rule (355, 768, 812, 2040, 2380, 2903 and any in the sponsor and institution sections) and remove any declaration that now merely repeats this one, keeping the deliberate differences. If the plugin base sets `border-radius: 8px` or the theme's brand button uses 8px elsewhere, keep the radius consistent with what the Administrator Dashboard's `.button` rule uses (read it: `.wpc-administrator-page .wpcpm-app-action .button` has `border-radius: 8px`); pick one radius for all buttons and say which in the comment.

- [ ] **Step 2: Check and commit**

`php bin/check-selectors.php | tail -3`; `git add -A && git commit -m "One button size on every dashboard"`.

---

### Task 6: The Institution Dashboard shows the colleague-invite flow it already has

**Files:**
- Modify: `includes/modules/class-wpcpm-institution-people.php` (`render()`, the note block around line 182)
- Test: `bin/test-institution-people.php`, `bin/test-institution-invite.php`

**Interfaces:**
- Consumes: `WPCPM_Institution_Invite::render_message( $record_id )`, `::render_pending( $record_id, $origin )`, `::render_form( $record_id, $origin )` (public; each decides for itself through `WPCPM_Institution_Policy::ACT_MANAGE_MEMBERS` and prints nothing for a viewer the policy refuses).
- Produces: on the institution's own dashboard the People card prints, in place of the sentence "Inviting a colleague from this page ships with a later release. Until then a program manager adds an account for them.", the invite flash, the pending invitations and the invite form; the manager-only link to the Institutions screen stays.

- [ ] **Step 1: Failing test**

In `bin/test-institution-people.php`, render the card as a member who may manage members (the suite has such a fixture; read how it sets the policy stand-in) and assert `strpos( $html, 'Inviting a colleague from this page ships with a later release' )` is false and `strpos( $html, 'name="action" value="' . WPCPM_Institution_Invite::ACTION_INVITE . '"' )` is not false; render as a viewer the policy refuses and assert the form is absent. If the suite's stubs do not load `WPCPM_Institution_Invite`, require it the way the suite requires its other classes and stub what its renderers need (read `render_form()`: it needs the policy, `WPCPM_Mentors_Sync::is_record_id()`, nonce and URL functions the suite very likely already stubs).

- [ ] **Step 2: Implement**

Replace the `printf( '<p class="wpcpm-people__note">%s</p>', esc_html__( 'Inviting a colleague ...' ) );` block with:

```php
		// The invite flow shipped with the module's handlers and suite but was never drawn here;
		// the card said a later release would. This is that release (1.94.7). Each renderer
		// decides for itself through the policy, so a viewer who may not manage members sees
		// nothing of it.
		WPCPM_Institution_Invite::render_message( $record_id );
		WPCPM_Institution_Invite::render_pending( $record_id );
		WPCPM_Institution_Invite::render_form( $record_id );
```

Check `render()`'s arguments for the record id variable's name. Remove the now-unused translated sentence. Check the class is loaded wherever the People card is (it is initialised from `class-wpcpm-institutions.php`; confirm the autoload or require order so the dashboard page has the class).

- [ ] **Step 3: Checks**

`php bin/test-institution-people.php 2>&1 | tail -1`; `php bin/test-institution-invite.php 2>&1 | tail -1`; `php bin/check-references.php | tail -1`; `bash bin/check-standards.sh 2>&1 | grep -E "FOUND [1-9]|British|Em or en"`. Docs: `grep -rn "later release" docs/sections/` and reword any sentence that still says the invite ships later (then `php bin/build-docs.php`).

- [ ] **Step 4: Commit**

```bash
git add -A && git commit -m "Institution Dashboard: the colleague-invite flow is on the card"
```

---

### Task 7: Versions, changelogs, docs, battery, build

**Files:**
- Modify: `wpcredits-program-manager.php`, `readme.txt`; theme `style.css`, `readme.txt`

- [ ] **Step 1:** plugin 1.94.7 and theme 1.21.0 in every spot; `= 1.94.7 =` and `= 1.21.0 =` entries describing Tasks 1 to 6 for a reader (US English, plain hyphens, full product names).
- [ ] **Step 2:** `php bin/build-docs.php` if any `docs/sections/` file changed in Task 6.
- [ ] **Step 3:** the battery (`for f in bin/test-*.php; do r=$(php "$f" 2>&1 | tail -1); case "$r" in *PASS*|*"NORMAL OUTCOME"*) ;; *) echo "$f: $r";; esac; done` prints nothing), `bash bin/check-standards.sh 2>&1 | grep -E "FOUND [1-9]|British|Em or en"` (nothing), `bash bin/check-standards.sh --dead | tail -1` (0 dead), `php bin/check-references.php | tail -1`, theme `php bin/check-selectors.php | tail -3`, `grep -rnE "font(-size)?:[^;]*\b(8|9|10|11)px" assets/css/ style.css` in the theme (nothing).
- [ ] **Step 4:** `bash bin/build` (version inside 1.94.7); theme zip from `/Users/maciejpilarski/GitHub`: `rm -f wpcredits-theme.zip && zip -rq wpcredits-theme.zip wpcredits-theme -x "wpcredits-theme/.git/*" "*/.DS_Store" "wpcredits-theme/node_modules/*" "*.map" "wpcredits-theme/.superpowers/*"` and `unzip -p wpcredits-theme.zip wpcredits-theme/style.css | grep "^Version:"` (1.21.0).
- [ ] **Step 5:** commit both repositories. Deploy, live measurement (the header band meets the first section; the ordering note sits above the list; the past-students box edges equal the cards' edges with 20px below; admin cells centered; 12px under request textareas; institution rows one line with a 44px avatar; every button 14px on the Sponsor Dashboard; the invite form on Krakow's card as a manager and absent for a stranger), mirror, vault and memory are the controller's.

---

## Self-review

- **Spec coverage:** screenshot 1 (gap: Task 2 band; spacing and ordering note: Task 2), screenshot 2 (lead headings: Task 1), screenshot 3 and 6 (past students: Task 2), screenshot 4 (rows: Task 4; the semester report Draft control is manager-only by code already, `render_generate_form()` prints it only under `$can_manage`, so nothing changes there and the owner is told; invites: Task 6), screenshot 5 (table and request spacing: Task 3), the Sponsor Dashboard (buttons: Task 5), the feedback selects (Task 1).
- **Placeholders:** none; each step names its file and its values. Where a value depends on a rule the implementer must read first (the band's margin, the disclosure's inset, the toggle's order, the roster row's username key), the step says what to read and what the result must be.
- **Type consistency:** `wpcpm-report__sub` in Task 1 is the existing class; `WPCPM_Mentors_Dashboard::render_avatar( $username, $email, $name, $size )` in Task 4 matches the method's current signature; the three invite renderers in Task 6 match their current public signatures with `$origin` defaulting to ''.
