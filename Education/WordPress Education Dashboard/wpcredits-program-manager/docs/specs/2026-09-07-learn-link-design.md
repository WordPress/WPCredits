# Learn Link: the Dashboard half and the contract to freeze

**Date:** 7 September 2026
**Component:** `wpcredits-program-manager` 1.98.1 today; the module described here is `WPCPM_Learn_Progress`, planned as 1.99.0 and later, with matching `wpcredits-theme` rules. Its counterpart, `learn-progress-api`, is a Sensei extension that runs on learn.wordpress.org. Both halves are built in the public `learn-link` repository by one partner institution's student cohort, October to December 2026.
**Status:** groundwork, not an approved design. It turns the roadmap's decisions into a contract and a class map the cohort can build against. Section 8 lists what the product owners (Maciej Pilarski and Isotta Peira) still have to decide; nothing in sections 2 to 7 should be treated as final until they have.
**Depends on:** the Learn Link roadmap (`~/GitHub/learn-link-roadmap.html`, 31 August 2026); the working agreement that every student on the program has a WordPress.org profile; the Student Report Card's movable modules (`WPCPM_Module_Order`, since 1.95.11); `WPCPM_Secret` (since 1.94.0); the fetch and parse split of `WPCPM_WPorg_Profile`.

House rules that apply to every line below: comments explain why and name the decision behind a rule; no em dashes; full product names ("Student Report Card", "Mentor Report Card", "Institution Dashboard", "Administrator Dashboard"); US English in every string a person reads; every plugin release bumps the version in every spot and rebuilds the zip; nothing here touches the live site until the product owners say so.

---

## 1. Settled before this document

These come from the roadmap and the vault note of 31 August and 1 September 2026. They are repeated here because they are easy to get wrong later, and because a builder reading only this file must not have to reconstruct them.

1. **Learn has no read API.** Every REST namespace on learn.wordpress.org was enumerated: Sensei exposes setup, import and export, course structure and batch writes, and nothing that reads a learner's results. New code has to run on Learn itself. Two things are free and need no new code on Learn: the course module tree at `sensei-internal/v1/course-structure/297853` is public (course `wordpress-credits` is post 297853), and WordPress.org profiles publicly record "Completed the course ..." entries.
2. **Identity is the WordPress.org profile.** Creating one is the first onboarding step, so every student has one. The Dashboard already knows each student's username (the `username` key of `WPCPM_Students_Sync::get_program()`). Nothing in Learn Link keys on an Airtable record ID.
3. **A student authorizes once, and only a program administrator can withdraw it.** The security case rests on scope, not on revocability: the endpoint returns only the token holder's own results for a fixed list of courses, and takes no user parameter, so it cannot disclose anyone else.
4. **Scope is the student's track course plus the required foundational courses.** Configured on the Dashboard side, enforced on the Learn side.
5. **Cached pull, plus a scheduled warmer** that fills the same cache for every connected student so the Mentor Report Card renders locally.
6. **A scoped token of its own, not core Application Passwords**, which grant everything that user can do.
7. **The builders are participants**, so they are inside the data set they build for. That is contained by the endpoint's shape, not by policy.
8. **There is no staging copy of wordpresseducation.org.** Standing up a practice copy with invented records is the first Phase 0 item. Students never receive production credentials.
9. **Meta review is the long pole** and outside the project's control. Every student deliverable stays demonstrable without Meta merging anything.

---

## 2. The contract, version 1

This is the page the roadmap asks for in Phase 0: "agree exactly what Learn will send, in writing, and then stop changing it." Once the product owners approve it, it is copied into the `learn-link` repository as `contract/learn-progress-v1.md` with the worked example beside it, and changes only by a new version number.

### 2.1 Reading progress

```
GET https://learn.wordpress.org/wp-json/learn-progress/v1/me
Authorization: Bearer <token>
Accept: application/json
```

No query parameters are needed. The course list is fixed on the Learn side for this client (the whitelist is the whole scope; the Dashboard cannot widen it). A `courses` parameter, when sent, may only narrow the list to a subset of the whitelist; anything outside it is ignored, never an error, so a probe learns nothing about the whitelist.

Response, `200`:

```json
{
  "contract": "learn-progress/1",
  "learner": {
    "wporg_username": "adaexample"
  },
  "generated_at": "2026-10-14T09:12:33Z",
  "courses": [
    {
      "course_id": 297853,
      "status": "in-progress",
      "enrolled_at": "2026-09-02T14:03:00Z",
      "completed_at": null,
      "progress_percent": 40,
      "lessons": [
        {
          "lesson_id": 297901,
          "status": "complete",
          "completed_at": "2026-09-20T18:44:10Z",
          "quiz": {
            "quiz_id": 297950,
            "status": "passed",
            "grade_percent": 85,
            "passmark_percent": 70,
            "graded_at": "2026-09-20T18:44:10Z"
          }
        },
        {
          "lesson_id": 297902,
          "status": "in-progress",
          "completed_at": null,
          "quiz": {
            "quiz_id": 297951,
            "status": "ungraded",
            "grade_percent": null,
            "passmark_percent": 70,
            "graded_at": null
          }
        },
        {
          "lesson_id": 297903,
          "status": "not-started",
          "completed_at": null,
          "quiz": null
        }
      ]
    },
    {
      "course_id": 123456,
      "status": "not-started",
      "enrolled_at": null,
      "completed_at": null,
      "progress_percent": 0,
      "lessons": []
    }
  ]
}
```

Rules that make the shape safe and stable:

- **IDs only, no titles, no module tree.** Learn sends what only Learn knows: status, dates and grades. Names and the module grouping come from the public course-structure endpoint, read by the Dashboard, so a curriculum rename changes nothing in the contract and nothing personal travels that does not have to.
- **Every whitelisted course appears**, including one the learner never enrolled in (`status` `not-started`, `enrolled_at` null, `lessons` empty). "Not connected" and "connected but not started" must be distinguishable on the Report Card, and the second one has to come from Learn.
- **Vocabulary, closed lists.** Course `status`: `not-started`, `in-progress`, `complete`. Lesson `status`: `not-started`, `in-progress`, `complete`, `ungraded` (the learner submitted, nobody has graded yet). Quiz `status`: `not-taken`, `ungraded`, `passed`, `failed`. `quiz` is null for a lesson without a quiz. Percentages are integers 0 to 100; `grade_percent` is null until graded.
- **Times are UTC, RFC 3339.** The Dashboard formats them in the site's timezone.
- **The learner block carries the WordPress.org username** as Learn knows it through WordPress.org single sign-on. The Dashboard compares it with the student's known username on every read (section 4.2). Learn's own numeric user ID is not sent.
- **Version in the body** (`contract`), so a later version can be recognized without a header.

Errors, always JSON with `code` and `message`:

| Status | `code` | Meaning for the Dashboard |
| --- | --- | --- |
| 401 | `learn_progress_invalid_token` | The token is unknown or malformed. Treat as not connected and tell a manager. |
| 403 | `learn_progress_revoked` | The grant was withdrawn on Learn. Show "connection withdrawn", never 0 percent. |
| 429 | `learn_progress_slow_down` | Too many reads. Honor `Retry-After`. |
| 503 | `learn_progress_unavailable` | Learn cannot read results right now. Keep the cache, say so. |

Rate expectation: the Dashboard reads a student at most once per cache window (section 4.4) plus one warmer pass a day. Learn may refuse more than sixty reads per token per hour.

### 2.2 Granting access

Modeled on core's `authorize-application.php`, with two changes: a one-time code instead of a token in the redirect, and a code verifier instead of a client secret, so that no secret ever has to live in the public codebase and no token ever appears in a browser address bar, a referrer or a server log.

1. **Start.** The student presses "Connect your Learn account" on their own Student Report Card. The Dashboard creates `state` (32 random bytes, base64url) and `code_verifier` (43 to 128 characters), stores both in a transient keyed by `state` for 15 minutes together with the student's user ID, computes `code_challenge = base64url( sha256( code_verifier ) )`, and redirects to:

```
https://learn.wordpress.org/learn-link/authorize/
  ?client_id=wpcredits
  &redirect_uri=https://wordpresseducation.org/wp-admin/admin-post.php?action=wpcpm_learn_callback
  &state=<state>
  &code_challenge=<code_challenge>
  &code_challenge_method=S256
```

2. **Approve.** Learn requires the person to be signed in (WordPress.org single sign-on). The screen says, in plain words, what will be shared (course progress, lesson status and quiz grades for the named courses, and nothing else), with whom (the WPCredits program on wordpresseducation.org), for how long (the length of the program), and that a program administrator can withdraw it. `redirect_uri` must match one of the URIs registered for `client_id` on Learn (the live site and the practice copy); anything else is refused before the screen renders. On approval, Learn stores a grant row (learner, client, courses, `code_challenge`, created), mints a one-time `code` (10 minutes), lists the grant on the learner's Learn profile so they can always see it exists, and redirects to `redirect_uri` with `code` and `state`.

3. **Exchange.** The Dashboard's callback checks `state` against the transient (single use: the transient is deleted on first read), then, server to server:

```
POST https://learn.wordpress.org/wp-json/learn-progress/v1/token
{ "grant_type": "authorization_code", "client_id": "wpcredits", "code": "<code>", "code_verifier": "<code_verifier>" }
```

Learn verifies `sha256( code_verifier )` against the stored challenge, burns the code, mints the token (32 random bytes, base64url), stores only its hash (as Application Passwords do), and answers `{ "token": "...", "learner": { "wporg_username": "adaexample" } }`.

4. **Identity check, before anything is stored.** The Dashboard compares `learner.wporg_username` with the student's known username, case-insensitively. A mismatch is refused, nothing is saved, the token is revoked at once (section 2.3), and the student sees: "The Learn account you approved belongs to @otherperson. Your Student Report Card is for @adaexample. Sign in to Learn as @adaexample and try again." This is the one place a participant building the system could connect someone else's account to their own card, and it is closed by data, not by policy.

5. **Store.** The token is sealed with `WPCPM_Secret::seal_for_option()` into user meta `_wpcpm_learn_token`; `_wpcpm_learn_connected_at` records when. An audit row of kind `learn_connect` is written (section 5.5).

### 2.3 Withdrawing access

```
DELETE https://learn.wordpress.org/wp-json/learn-progress/v1/token
Authorization: Bearer <token>
```

Learn deletes the grant and answers `204`; a later read with that token is `401`. Only the Dashboard's manager action calls this (section 4.3). Learn may also let its own administrators withdraw a grant from the learner's profile screen; the Dashboard then learns of it through `403 learn_progress_revoked` on the next read and shows it truthfully.

### 2.4 The worked example

`contract/example-in-progress.json` in the `learn-link` repository is the response above, extended to a realistic learner: one course of eight lessons in the states complete with a passed quiz, complete with a failed quiz, ungraded, in progress, not started; a second whitelisted course not started. Beside it: `example-not-started.json` (enrolled, nothing done), `example-complete.json`, and the four error bodies. The Dashboard suites run against exactly these files.

---

## 3. What a student and a mentor see

### 3.1 Student Report Card

The results live in the **My course** module (`WPCPM_Students_Dashboard::MODULE_COURSE`), which today holds the course link and the self-reported hours field (`render_links()`). Under those, a block "Your progress on Learn" in one of six states. Each state has its own words; a withdrawn or unreachable connection must never render as 0 percent.

| State | What is shown |
| --- | --- |
| Not connected | One sentence on what connecting does and a primary button "Connect your Learn account". Only the student themself sees the button; a manager viewing as the student sees "Not connected" and no button. |
| Connected, nothing started | "Connected on 2 October. You have not started the course yet." |
| Connected, in progress | A progress bar per course (percent), then the modules from the course structure with the lesson count done under each, then a lessons table: lesson, status, quiz result ("85%, passed", "Waiting to be graded", "Not taken"). |
| Connected, complete | The same, with the completion date first. |
| Connection withdrawn | "A program administrator withdrew this connection on 14 November. Ask your mentor if you think this is a mistake." No numbers. |
| Learn did not answer | The last cached picture with a line "Learn did not answer just now. These are your results as of 13 November, 09:12." If there is no cache yet: "Learn did not answer just now. Try again in a few minutes." |

A "Last updated" line closes the block whenever numbers are shown. Everything the block prints is escaped as text; nothing from Learn is trusted as markup.

### 3.2 Mentor Report Card

`WPCPM_Mentors_Dashboard::render_mentee()` gains one line per student: "Learn: 40% of the track course, 3 of 8 lessons, last quiz 85%" for a connected student, "Learn: not connected" otherwise, and "Learn: connection withdrawn" when it was. The line reads from the cache only; the Mentor Report Card never calls Learn. The triage search (`triage_data()`) gains a `learn` facet so a mentor can list "not connected" students.

The lessons table itself is one press away, on the student's Student Report Card, which the mentor already opens for their own students.

### 3.3 Administrator Dashboard

One tile in the programs strip, "Connected to Learn: 41 of 120 students", and one line on the Syncs card for the warmer (last run, students refreshed, failures). A folded list "Completed on Learn, not connected: 6 students" built from the public profile timelines (the same "Completed the course" parsing the mentor checker uses) is the nudge list the roadmap describes; it is a cross-check across the whole cohort because every student has a profile.

### 3.4 Institution Dashboard

Nothing in version 1. Grades are personal data of the student, and the institution's roster is about enrollment and reports. If the product owners want a "connected" count per institution later, it is one more roster column and no new data.

---

## 4. The Dashboard module: `WPCPM_Learn_Progress`

Copy the shape, share the primitives: the module is written to the contracts the Sponsors and Institutions modules already use, and adds no new primitive.

### 4.1 Files

| File | Responsibility |
| --- | --- |
| `includes/modules/class-wpcpm-learn-progress.php` | The module: settings keys, the three handlers (`wpcpm_learn_connect`, `wpcpm_learn_callback`, `wpcpm_learn_withdraw`), the state machine of section 3.1, the identity check, meta keys, audit rows. |
| `includes/modules/class-wpcpm-learn-client.php` | The HTTP client. `fetch( $token )` returns the decoded array or a `WP_Error` whose code is the contract's `code`; `parse( array $body )` is a pure function from the body to the plugin's normalized shape and is what the suites test against the fixtures. Fetch and parse are split exactly as `WPCPM_WPorg_Profile::get()` and `parse()` are. |
| `includes/modules/class-wpcpm-learn-structure.php` | Reads the public course structure (`sensei-internal/v1/course-structure/<id>`), caches it for 24 hours in an option per course, and maps lesson IDs to titles and modules. Unreachable structure degrades to a flat lessons list with IDs as titles, never to an error. |
| `includes/modules/class-wpcpm-learn-progress-card.php` | Renders the six states into the My course module and the mentor line. Takes the normalized shape and the structure; touches no network. |
| `includes/modules/class-wpcpm-learn-progress-sync.php` | The warmer, a `WPCPM_Sync_Module` like the sponsors sync: nightly, one read per connected student, honoring `Retry-After`, recording failures per student without stopping the run; also the retry queue for withdrawals that did not reach Learn (section 4.3). |
| `assets/css/learn.css` | Base rules for the block (progress bar, modules, table); the theme restates sizes and colors in tokens as it does for `sponsor.css`. |
| `bin/fixtures/learn-*.json`, `bin/test-learn-client.php`, `bin/test-learn-progress.php`, `bin/test-learn-card.php` | The contract examples and three suites (section 6). |

### 4.2 Settings

Added to `WPCPM_Settings::defaults()`, all under the existing Settings screen in a "Learn Link" section, none of them secret:

| Key | Default | Meaning |
| --- | --- | --- |
| `learn_enabled` | off | Nothing renders and no handler answers while off. |
| `learn_base_url` | `https://learn.wordpress.org` | The practice copy points this at its local Learn. |
| `learn_client_id` | `wpcredits` | The client registered on Learn. |
| `learn_courses` | `297853` | Comma-separated course IDs, the track course first. Must equal the whitelist Learn holds for the client; a course listed here that Learn does not send renders as "not part of your track". |
| `learn_cache_hours` | 6 | How long a student's own page trusts the cache before reading Learn again. |

There is no client secret: the exchange is protected by the code verifier (section 2.2), so the public repository can hold every line of the Dashboard half.

### 4.3 Handlers and who may call them

| Handler | Who | Checks |
| --- | --- | --- |
| `wpcpm_learn_connect` (POST) | The student, for their own card | Signed in, nonce, `learn_enabled`, the acting user IS the card's student (a manager viewing as a student is refused with "Only the student can connect their own account."), not already connected. Creates `state` and the verifier, redirects to Learn. |
| `wpcpm_learn_callback` (GET, from Learn) | The browser returning from Learn | `state` present and matching a live transient, the transient's user ID equals the signed-in user, the code exchange succeeds, the identity check passes. Every refusal has its own message and lands the student back on their Student Report Card with a flash. |
| `wpcpm_learn_withdraw` (POST) | A program manager | Nonce and `manage_options`, the target student connected. Calls DELETE on Learn; on success deletes the token and stamps `_wpcpm_learn_withdrawn_at` and `_wpcpm_learn_withdrawn_by`; on failure deletes the token anyway (the Dashboard must stop reading at once), stamps `_wpcpm_learn_revoke_pending`, and the warmer retries the DELETE nightly until Learn answers 204 or 401. The student is told by mail, in the manager's name, with the reason the manager typed. |

A student cannot disconnect. The card says so in the connected state's footnote: "This connection lasts for the program. Ask your mentor or a program manager if it must be withdrawn."

### 4.4 Freshness

Per student, user meta `_wpcpm_learn_cache` holds the normalized shape plus `fetched_at`; `_wpcpm_learn_last_error` holds the last error code and time. The Student Report Card reads Learn when the cache is older than `learn_cache_hours` or absent, and otherwise renders the cache. The warmer refreshes every connected student once a night for the Mentor Report Card and the Administrator Dashboard, which never call Learn during a page view. A read that fails keeps the cache and records the error; the card then shows the cached picture with the "Learn did not answer" line. Grades are personal data, so the cache is deleted when the connection is withdrawn, when the account is deleted (`deleted_user`), and at uninstall (added to `uninstall.php`).

### 4.5 Data the Dashboard never stores

Learn's numeric user ID (not sent), lesson titles (read from the public structure on demand), anything about courses outside the whitelist, and the token in clear (sealed, and only its fingerprint is ever logged, through `WPCPM_Secret::fingerprint()`).

---

## 5. The Learn half, as the Dashboard needs it

The cohort owns this design; the following is what the contract requires of it, so both halves can be built without waiting for each other.

1. **Storage research first.** Sensei keeps lesson status as comments of type `sensei_lesson_status` with the grade in comment meta, or in its own tables, depending on a site setting that cannot be seen from outside. The read function is written against whichever Learn uses, behind one interface, with the other as a fallback.
2. **The read function** returns one learner's results for one course and is the only place that touches Sensei data. Everything else is transport.
3. **The authorize screen** is a front-end page (not wp-admin) so that a learner with no Learn capabilities can approve. It refuses unknown `client_id`, unregistered `redirect_uri`, and a missing or malformed `code_challenge`.
4. **Grants** are a custom post type or table with the hash of the token, the learner, the client, the courses, created and last-used times. Listed on the learner's profile screen. Deletable by a Learn administrator.
5. **The endpoint** identifies the learner from the token hash and nothing else. It never reads a `user` parameter. It never returns another learner's data even when asked for it, because there is no way to ask.
6. **Fixtures** for the seeded practice class: twenty invented learners across every state in section 2.1.

### 5.5 Audit on the Dashboard

Rows of kind `learn_connect`, `learn_withdraw`, `learn_identity_refused`, `learn_fetch_failed` (one per student per day at most), written through the existing audit primitive, readable on the Administrator Dashboard's Activity card.

---

## 6. Tests

No suite touches the network. The three suites stub WordPress functions at their top like every other `bin/test-*.php`.

- `bin/test-learn-client.php`: `parse()` against every fixture; unknown vocabulary values refused with a `WP_Error`; percentages clamped; a body with a wrong `contract` refused; each error status mapped to its code.
- `bin/test-learn-progress.php`: the state machine of section 3.1 from meta alone; the identity check refuses a mismatch and revokes; `state` single use; a manager viewing as the student gets no connect button; withdrawal with Learn down marks the retry and deletes the token; the warmer records a failure and continues to the next student.
- `bin/test-learn-card.php`: each of the six states renders its own sentence and nothing numeric in the withdrawn and unavailable states; every printed value passes through an escaping function (mutation: remove one `esc_html()` in a scratch copy and the suite must fail).

---

## 7. Phase mapping

| Roadmap phase | Dashboard half in this document |
| --- | --- |
| 0 (September, product owners) | Freeze section 2 as `contract/learn-progress-v1.md` with the four example files; create the repository; stand up the practice copy of the site; open the Meta and Sensei conversations with section 2 attached. |
| 1 (weeks 1 to 3) | `WPCPM_Learn_Client::parse()`, `WPCPM_Learn_Structure`, `WPCPM_Learn_Progress_Card` against fixtures; `test-learn-client` and `test-learn-card` green. |
| 2 (weeks 4 to 6) | The three handlers, `state`, the code exchange, the identity check, sealed storage, the withdrawal with its retry; `test-learn-progress` green; integration day against a tunneled local Learn. |
| 3 (weeks 7 to 9) | Modules from the structure, multi-course scope, the warmer, the mentor line and triage facet, the Administrator Dashboard tile and nudge list, accessibility and translation passes. |
| 4 (weeks 10 to 12) | Hardening, documentation, the pilot with real students on the live site behind `learn_enabled`. |

---

## 8. Open questions for the product owners

1. **Code exchange with a verifier (section 2.2) or the roadmap's "redirected back with a token"?** The exchange keeps tokens out of URLs and needs no secret in the repository. It costs the Learn half one more endpoint. Recommendation: the exchange.
2. **What does a mentor see?** This document gives mentors the roll-up line and one click to the student's full lessons table, which they can already open. Alternatives: the roll-up only, or the full table on the roster.
3. **Institutions see nothing in version 1.** Confirm, or ask for a connected count per institution.
4. **Where on the card?** Inside the My course module (the roadmap's choice, kept here) or as a seventh movable module of its own. The first keeps the course, the hours and the results together; the second lets a student move it.
5. **Which courses are foundational?** `learn_courses` needs the list; the track course 297853 is the only certain entry today.
6. **The cache window.** Six hours for the student's own page is a guess between freshness and Learn's load.
7. **Can a Learn administrator withdraw a grant on Learn's side too?** This document assumes yes and shows it truthfully on the card.
8. **The nudge list on the Administrator Dashboard** reads public profile timelines for every student nightly. Confirm that this cross-check is wanted at cohort scale.
9. **Who tells the student when a manager withdraws?** This document mails the student in the manager's name with the typed reason.
