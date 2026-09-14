# A series of group sessions, planned at once and joined at once

**Date:** 14 September 2026
**Component:** `wpcredits-program-manager` 1.107.1, ships as 1.108.0
**Status:** approved by the product owner on 14 September 2026, relaying a mentor's request (Celi): "Could we let students book several group sessions? As a mentor, I would like to leave all the group sessions created so they can join all of them", read again as "an option to create multiple group sessions for students at once for multiple dates, so the students can have them planned in advance in their calendars, and not book them one by one".
**Depends on:** group sessions as `WPCPM_Group_Sessions` and `WPCPM_Mentor_Calls` ship them (one call post per session, a capacity above one and repeated attendee rows), and on 1.107.1, where joining a session stopped counting against the mentor's per-student call limit, without which a student could not hold a series.

House rules that apply to every line below: comments explain why and name the decision behind a rule; no em dashes; full product names; US English in every string a person reads; every handler checks capability first, then nonce; everything a person can see is escaped on output; tests are written first.

---

## 1. Settled by the product owner

1. **A series is a list of dates** (14 September 2026). The planning form takes the dates one by one rather than a weekly rule: more flexible, and the common weekly case is eight date pickers filled in. Nine dates in one form at most, the first plus eight more; a longer series is planned in two goes.
2. **Join all, leave any** (14 September 2026). One press puts a student on every session of the series that still has a place. A student can still join or leave a single session, as today. No "Leave the series" for now.
3. **One email with every session in it** (14 September 2026). A series join sends one message listing every date, with one calendar file that holds each session as its own event, so importing it once plans them all; a later change to one session sends that session's update alone, as today.
4. **A series is a tag on ordinary sessions** (14 September 2026). Each session of a series stays the call post it is today, with one meta naming the series. A recurring event of its own and a bare "join every open session" were set aside: the first breaks the property that made group sessions cheap, the second cannot tell a Tuesday series from a one-off workshop.
5. **The mentor's side keeps every per-session action** (14 September 2026). A series is grouped on the panel; change, cancel and the note stay per session; no "Cancel the remaining sessions" for now.
6. **The prerequisite stands** (14 September 2026, 1.107.1). The per-student limit counts one-to-one calls alone; a session's places are its own limit.

## 2. What the code says today

- A group session is a `wpcpm_mentor_call` post, `private`, with `_wpcpm_call_start`, `_wpcpm_call_end`, `_wpcpm_call_mentor`, `_wpcpm_call_capacity` (above 1 marks a session), `_wpcpm_call_zone` (the mentor's timezone at planning time), the topic as `post_content`, the title "Group session - Y-m-d H:i", and attendees as repeated `_wpcpm_call_student` rows with their `_wpcpm_call_student_record` rows. `WPCPM_Mentor_Calls::details()` answers `id`, `start`, `end`, `mentor_id`, `student_id`, `record`, `name`, `zone`, `topic`, `booked`, `capacity`, `attendees`, `is_group`, `places`.
- `WPCPM_Group_Sessions::handle_create()` reads `date`, `time`, `minutes`, `capacity`, `topic` and `mentor`, validates the date and time through `WPCPM_Mentor_Availability::date_string()` and `time_string()`, builds the start in the mentor's zone, refuses the past (`session-past`), a start another session or call of the mentor already holds (`session-clash`, the exact-start rule of `clashes_with_another()`), a length outside 5 to 480 minutes and a capacity outside 2 to 50, and bounces `session-created`.
- `handle_join()` checks the session exists and has not started (`session-gone`), that it is the student's own mentor's (`session-not-yours`), that the student is not on it (`session-already`), the reasons a student cannot book other than the limit (`blocked`, through `why_not_bookable( $student_id, $mentor, false )`), takes the mentor's booking lock (`busy` when held), checks the room (`session-full`), adds the attendee, releases the lock, and sends the invitation through `notify_joined()`, which is `notify_booked()`: one message to the student and one to the mentor, each with the session's calendar file from `WPCPM_ICS::build()`.
- `WPCPM_ICS::build()` writes one `VCALENDAR` with one `VEVENT`: the UID `wpcpm-call-<post id>@<host>`, the `SEQUENCE` (0 for a booking, the session's revision for a move, 1 or more for a cancellation), UTC `DTSTART` and `DTEND`, the summary, the description, the place, the organizer and the two attendees. A calendar matches later moves and cancellations by that UID.
- The outcome of every press is a flash on the `call` channel, read on the next page; `WPCPM_Mentor_Calls::messages()` maps each key to a status and a sentence.
- The mentor's panel lists `for_mentor()` in date order, one row a session with who joined, the note, Change and Cancel; the student's list lists `for_student()` the same way with places, the topic, and Join this session or Leave the session.
- The reminder sweep, the diary, the ICS updates on a move, the cancellation mail and the blocking of one-to-one booking all work per call post and need nothing new.

## 3. The series

- **One meta.** `WPCPM_Group_Sessions::META_SERIES = '_wpcpm_session_series'`, an integer: the post ID of the first session of the series, on every session of the series including the first. A session planned alone carries none, and nothing that exists changes.
- **Two readers.** `series_of( $call_id )`: the series ID, or 0 for a lone session. `series_members( $series_id, $upcoming = true )`: the sessions of the series in date order, upcoming only by default, through the calls query with a meta clause on the series; a canceled session is no longer a member because it is no longer a call post.
- **Grouping is a view.** The panel and the student's list group rows that share a series ID; a series whose remaining sessions number one still shows as a series, since its meta says what it is.

## 4. Planning a series

- **The form.** The planning form keeps its fields and gains **More dates**: eight `<input type="date" name="more_dates[]">` under the first date, in a compact grid, with the note "Leave the ones you do not need empty. The same time, length, places and topic apply to every date." Nothing else on the form changes but its button, which reads "Create the sessions" now that the form can make several (amended after the final review of 14 September 2026: the plan changed the label and the spec records it).
- **The rules, on the whole list.** The first date plus every filled `more_dates[]` entry is the list, each read through `date_string()`; an entry that is not a real date refuses the form (`session-when`). Every date takes the one time, in the mentor's zone as today, so a series keeps its clock time across a daylight-saving change. Refused, naming the date in the message: a date in the past (`series-past`), a date given twice (`series-twice`), a date whose start another session or call of the mentor already holds (`series-clash`), and, before any of those, more than nine dates (`series-many`, unreachable through the form and refused all the same). A refusal creates nothing: all or nothing, so a mentor fixes the list rather than ending up with half a series.
- **Creation.** The sessions are created in date order exactly as one is today (the same post fields, the same five meta), then the series meta is written to every one of them with the first created ID. A single date creates a lone session with no series meta, as today, and bounces `session-created` as today; two or more bounce `series-planned`, whose sentence says how many.
- **The messages.** `series-planned`: "Your %d sessions are planned. Your students can see the series and join it." `series-past`: "One of the dates has passed: %s." `series-twice`: "One of the dates is given twice: %s." `series-clash`: "Something else of yours already starts on %s at that time." `series-many`: "A series holds nine sessions at most; plan the rest in a second go." Dates in the messages print through `wp_date( 'Y-m-d' )`. The flash carries the key and the date, so `messages()` learns to format a key with one argument.

## 5. The student's list and Join all

- **Grouping.** Under *Group sessions with your mentor*, the sessions that share a series sit together under a heading: the topic, then "%1$d sessions, %2$s to %3$s" with the first and last upcoming dates in the student's timezone. A lone session keeps its row as today, with no heading. Each session of a series keeps its whole row: the time, the places, and its own **Join this session** or **Leave the session**.
- **Join all.** Above the rows of a series, one form posting `wpcpm_join_series` with the series ID and the nonce, while there is at least one session of the series the student is not on that still has a place. It is not drawn when every session with a place already holds the student, or when every remaining session is full.
- **The handler.** `handle_join_series()` checks, in this order: logged in; the series exists and has an upcoming session (`session-gone`); the sessions are the student's own mentor's (`session-not-yours`); the reasons a student cannot book other than the limit (`blocked`, through `why_not_bookable( $student_id, $mentor, false )`); then the mentor's booking lock (`busy`). Under the lock it walks the upcoming sessions in date order, skips one the student is on, skips one with no room, and adds the student to the rest; it releases the lock and sends one invitation through `notify_joined_series()` for the sessions it took. Outcomes: `series-joined` when it took every session it could and none was full: "You are on all %d sessions. They are in your list above, and one email holds them all for your calendar." `series-joined-some` when some were full: "You are on %1$d of the %2$d sessions; %3$d had no place left." `series-nothing` when nothing was taken: "There was nothing to join: you are on every session of the series that has a place."
- **Leaving** stays per session, with today's `handle_leave()`, mail and cancellation file.

## 6. The email and the calendar file

- **The file.** `WPCPM_ICS::build_many( array $facts_list, $method, $mentor, $student, $summary, $body, $where = '' )`: one `VCALENDAR` with one `VEVENT` per session, each with its own UID from its own post ID, `SEQUENCE:0`, its own UTC `DTSTART` and `DTEND`, the shared summary, the shared description, the place, the organizer and the two attendees, so every event in the file is the event that session's single invitation would carry and a later move or cancellation of that session lands on it. `build()` is untouched; `build_many()` reuses its line builders.
- **The messages.** `WPCPM_Mentor_Calls::notify_joined_series( array $call_ids, WP_User $mentor, $student )` sends, through `WPCPM_Mail::send()` as `notify_booked()` does, one message to the student and one to the mentor. Subject: "Group sessions with %1$s: %2$d dates" to the student and "%1$s joined your series: %2$d sessions" to the mentor. Body: the topic, then one line a session with its date and time in the recipient's own timezone, then the meeting place when the mentor has set one, then the sentence that the attached file adds them all to a calendar. Attachment: the one file from `build_many()`, written and cleaned up as a single file is.
- **Unchanged.** Joining a single session of a series by itself sends today's single message; a move sends that session's update with its revision; a cancellation sends that session's cancellation; the reminder 24 hours before goes out per session.

## 7. The mentor's panel

Under *Group sessions* on the Mentor Report Card, the rows of one series sit together under the same heading the student sees, the topic and "%1$d sessions, %2$s to %3$s" in the mentor's timezone. Every row keeps everything on it: who has joined, the note, Change and Cancel. A change to one session's time, length or places touches that session alone and it stays in the series; a cancellation tells everybody on that session and leaves the rest standing.

## 8. Tests, written first

- `bin/test-group-sessions.php`: a series of three planned from a first date and two more dates carries the first ID as its series meta on all three, in date order, with the same time, length, places and topic; a lone session carries no series meta; an empty `more_dates[]` entry is ignored; a date that is not a date, a past date, a date given twice and a clash each refuse the whole list, create nothing and name the date; ten dates are refused; `series_of()` and `series_members()` answer as section 3 says and a canceled member drops out. Join all: a student is put on every session with a place, a full one is skipped and counted, a session already joined is left alone, the lock is taken once and released. The calendar file holds one event per session with distinct UIDs, `SEQUENCE:0` on each, and the same summary. The suite's stand-ins already model repeated meta rows; they gain a post status.
- `bin/test-handlers.php`: `handle_create` with more dates reaching a normal outcome for the valid list and for each refusal; `handle_join_series` for no such series, not their mentor's, nothing to join, and a valid join, with the flash read from the raw queue as 1.107.1's checks do.
- `bin/test-mail.php`: the series message to the student lists every date in the student's timezone and carries one attachment, the message to the mentor names the student and the count, and the file is cleaned up after sending.
- The battery stays silent, the gate ends `85 warnings, no errors.` or fewer, the three checkers clean.

## 9. Privacy

Nothing new is stored about anybody. The series meta is a post ID. The series message lists dates, the topic and the meeting place, as the single message does, to the two people already on the session.

## 10. Guides and release

- The student guide's *Group sessions* gains a paragraph: a mentor may plan a series, the list groups it, **Join all** takes every date with a place and one email holds them all for the calendar, and any single date can still be joined or left.
- The mentor guide's *Group sessions* gains **More dates** in the planning list and a paragraph on the series: the same details for every date, all or nothing, what students see, and that change and cancel stay per session.
- Release 1.108.0: the version in three places, the changelog entry, the template regenerated, the zip built, merged, mirrored and deployed on the product owner's yes, with the student (558) and mentor (559) guide pages republished from a `--base=` build.

## 11. Not in scope

Leave the series and Cancel the remaining sessions (decisions 2 and 5); more than nine dates in one form (decision 1); a recurring rule or a true recurring event (decision 4); RSVP from the calendar, which the single invitation does not have either.
