# Student automation, first line (design, 9 November 2026)

Approved by the product owner on 9 November 2026 ("yes"), with the dates of section 8; the product owner's answers of 9 November 2026 and Claude's calls are marked where they apply. Plugin: WPCredits Program Manager. Versions: the next free ones when
building starts (1.123.60 on 9 November 2026); this document does not pin them.

## 1. Why

A program lead's proposal ("Shifting from AI Mentor Test to Automation", October 2026) moved the program from
testing an AI mentor to automating the parts of mentoring that are lookup, reminding and checking, with ordinary
code. Its two goals: mentorship that scales, and mentorship of an even quality. The large university cohorts that
arrive this autumn make it urgent: a program manager cannot follow sixty students by hand, and a student who goes
quiet for three weeks is usually lost.

On 30 October 2026 the program lead chose three things to start with: flags for disengagement, verification tools
(a first-week checklist) and an FAQ (a "Start here" page). On 5 November the product owner estimated them, built
into the site, at about two weeks in all, flags first; the program lead agreed. On 9 November the product owner
added that the proposal's quick wins should ship with them, and that the final version is put together after the
mentor survey's results are in, before anything launches on the site.

Success: a program manager opens the Administrator Dashboard on a Monday and sees which of their students went
quiet, and why the site thinks so; the student and the mentor were already reminded, with a link to book a call;
a new student knows what to do in week one without asking anyone.

## 2. What this line builds

Five features, in this order. Each one is behind its own switch, off by default, until the launch (section 8). The flags' switch has three states: Off; Preview, in which Administrators see the card and the reminders that would have gone out are listed on it, and nothing is mailed; and On (Claude's call, 9 November).

### 2.1 Disengagement flags, with reminders

**Who is followed.** Students the site counts as in progress (the same rule as the Programs card's "in progress"
figure). Graduated, pending graduation, paused, dropped out and every other closed status are never flagged and
never reminded. Students of a test institution are flagged with the Test mark and left out of every count, as
1.123.55 rules.

**Last activity.** The latest of: the last sign-in and the last visit (`WPCPM_Sign_Ins`), a call or group session
booked, joined or held (`WPCPM_Mentor_Calls`), and a save of the student's report form. The site records report
saves today only in Airtable; this line adds a site-side stamp, written in `WPCPM_Student_Report_Form::handle_save()`.
One class answers "when was this student last active, and through what", so every later feature (and the AI
Mentor, section 5) reads the same answer. When the line is first switched on, the report-save stamp is filled
from the last-modified time of each student's Students Reports record in Airtable, so a student whose only
activity was saving their report does not look quiet (product owner, 9 November).

**Flag.** A student with no activity for 14 days is flagged. The 14 days is a setting. A new student's clock starts
when their account was made, or at their track's start date when that is later (a student back on an old account),
so nobody is flagged in their first two weeks for having done nothing yet (Claude's call).

**Who sees a flag.** A card on the Administrator Dashboard lists the flagged students, grouped by their program
manager. A student's program manager is the contact of their institution's country, as the site already routes it
(`WPCPM_Countries::routing()`; each country contact is an Administrator with an account on the site). A student
with no institution, or whose country has no contact, is listed under "No program manager". The signed-in
Administrator's own students come first. Each row says how long the student has been quiet, the last activity and
its kind, the mentor, and whether the reminders went out and when.

**Weekly email.** Every Monday each program manager is emailed the list of their flagged students, with a link to
the card. Nothing is sent when the list is empty.

**Launch.** When the switch goes On, every student already quiet is flagged on the card at once, but no reminder
goes out for a quiet spell that began before the switch went On; reminders go to students who go quiet after it
(product owner, 9 November). So launch day sends no wave of emails.

**Reminders.** When a student is flagged, the student is emailed once, and so is their mentor if they have one. A
mentor with several newly flagged students gets one email a day listing them, not one per student (Claude's call).
The student's email links to booking a call with their mentor on the Student Report Card; with no mentor, to the
program manager's booking link (the country's Calendly); with neither, to the Student Report Card. The mentor's
email names the student and links to the student on the Mentor Report Card. A mentor is optional everywhere: a
student with no mentor gets the reminder alone.

**"We've already talked."** The student, the mentor, or an Administrator on the card can mark that they have talked;
the site records who and when (Claude's call). It is the only snooze. It counts as activity, so the
flag clears and the 14 days start again. The email's link takes the person to the button on their own dashboard;
the button acts only for a signed-in person. A reminder is sent once per quiet spell: a student who stays quiet
stays on the card as "reminded on <date>, no reply", which is the program manager's signal to step in.

### 2.2 First-week checklist

A card on the Student Report Card, movable like its other modules, for new students only: students whose track
started in the last four weeks when the line is switched on, and every student who joins after that (product
owner, 9 November). The site ticks the items it can check by itself:

- WordPress.org profile on record (the Students row's WordPress Profile);
- signed in to the site;
- report form started;
- first call booked;
- contribution team chosen (the student's Main Contribution Team set), with a link to the Team Matcher page (2.4);
- joined the Making WordPress Slack and the #wpcredits channel: the student ticks these, since the site cannot see
  Slack;
- enrolled in the Learn course: the student ticks it, with a link to the course, until the Learn Link module is in;
  then the site ticks it (product owner, 9 November).

The card shows until every item is ticked, then folds to one line that the student can open again. Administrators
see it through "Viewing as student". It gates nothing: the site has no matching step to hold back, and holding a
student back is not this line's job.

### 2.3 Start here page

A public page, readable without signing in, that tells a new student what to do first and where everything is,
answering the top student complaint that the resources and handbooks are overwhelming. Linked from the Student
Report Card's resources and from the student's welcome email (a new placeholder in the `invite-student` template).
The program lead writes the copy; the first version is put together from the Student guide's joining steps and is
replaced by her copy before the launch. It is a plain page of blocks on the site, so the AI Mentor's answering
engine can read it later.

### 2.4 Team Matcher page (quick win)

The Contribution Team Matcher (the Find Your Team plugin, 1.0.13) is active on the live site, and the Program
Manager already reads its team list (`WPCPM_Contribution_Teams`), but no page shows its quiz. This line adds a
public page with the Matcher's block, linked from the checklist, the Start here page and the student registration
form's confirmation. The Matcher's answer is not stored: the student's Main Contribution Team stays where it is set
today.

### 2.5 "Link your work" (quick win)

One field, "Links to your work", in the hours part of each track's report form, one link per line, so declared
hours point at public contributions (Trac, GitHub, translate.wordpress.org, profiles). It is a track field made in
the Track Builder, on every track with an hours part, writing to one long-text column, "Links to your work", in
Students Reports. Claude creates that column through the Airtable connector on the product owner's yes, after
showing the exact change (product owner, 9 November); nobody can remove a column once it is made. The field is
added to the tracks with the final version, at the launch, since a published track is live at once (Claude's call). Mentors and Administrators read it where they read the report today.
Hours stay one total: there is no log per entry.

## 3. What waits for the survey

The other proposals are written up once the mentor survey's results are in (it closes on Thursday 12 November
2026), because the survey asks mentors, task by task, whether to drop it, hand it to the site or keep it, and it
asks the design questions these depend on. What the code has for each, read on 9 November 2026:

- **Pre-filled weekly report** (hours this week, last contribution, blocker, next goal). The site has mentor call
  notes but no weekly report and no weekly history; hours are one running total.
- **Contribution-team intake directory** (per team: takes students, meeting time, a first task). Nothing exists
  beyond team names and links.
- **Office hours on the site.** Group sessions exist, with capacity, series, join and reminders; joining is limited
  to the mentor's own students and a session has no per-student question. The survey asks whether mentors would
  rather be matched or sit in a team's pool, which decides who may join.
- **Mentor inactivity** (two-week cycles, a reminder, a program manager's check-in, Inactive and reassignment, a
  self-service "unavailable"). The Engagement tab measures mentors; there is no cycle counter, pause flag, write of
  Inactive to Airtable or reassignment tool.
- **Grading checks.** Statuses for pending graduation and graduate exist; there are no grade rules. Grading is done
  by hand in Airtable today.
- **Mentor badge.** Undefined: what it is, who grants it and where it shows.

## 4. The survey and the final version

This line is built and tried now, and deployed with its switches off. When the survey's results are in, the product
owner and the program lead settle the final version: what the reminders say and to whom, the 14 days, the
checklist's items, and the Start here copy. The final version is a release of its own; the launch is turning the
switches on. Anything the survey turns down is removed in that release, not left switched off.

## 5. The AI Mentor, later

The AI Mentor (the WordPress-native build plan of 8 November 2026) is to be incorporated after December. This line
is built so that it does not have to be redone then:

- one activity answer and one reminder path: the AI Mentor's idle nudges (its sixth release) read the same last
  activity and send through the same reminder and the same "we've already talked", instead of a second system with its own seven
  days; the
  threshold is one setting both use;
- the checklist and the AI Mentor's chat are both Student Report Card modules, side by side;
- the Start here page and the guides are what its answering engine reads;
- nothing here touches the Need help? assistant, which the AI Mentor's first release replaces in its own session.

## 6. Constraints

- Every test mail on the live site goes to maciej@a8c.com (plus-addresses of it allowed); the Docker copy sends no
  mail and writes nothing to Airtable (mail captured, Airtable faked), as every trial so far.
- Student context stays on the site: no outside service, nothing sent to a model.
- What this line stores (report-save stamps, flags, reminder dates, "we've talked", checklist ticks) lives on the
  site, not in Airtable, so it survives the move of student data off Airtable, due by mid-December 2026.
- "Administrators" is the one name for them in every string; full product names (Student Report Card, Mentor Report
  Card, Administrator Dashboard); no em dashes; US English; the WordPress.org design system.
- New cards follow the Institution Dashboard's card rules and spacing, at full width.
- Each release documents its screens and settings in the guides in the same release, and bumps the version.
- Mail goes through the plugin's mail catalog, so every template is in Tools > Emails, logged and testable.

## 7. Non-goals and anti-goals

Not in this line: anything from section 3, any AI, gating a student, changing how mentors are assigned, a log of
hours per entry.

It fails, even if it works, if: a trial mails a real person; a student is reminded after graduating or pausing;
a student is reminded twice for one quiet spell; a flag or a reminder reads like a penalty; one student sees
another's flag; a test student changes a figure.

## 8. Rollout

Dates confirmed by the product owner on 9 November 2026: the five features built, tried on the Docker copy and on the live
site with their switches off by Friday 20 November 2026; the final version after the survey's results, by Friday
27 November; the launch in the first week of December. The roadmap is updated with these dates and the plan's releases.

## 9. Left to the builder

The card and email wording within the rules above; how the 14 days and the weekly email's day are stored; the order
of releases within the line; the checklist card's look; how the Start here page's content is kept (a guide section
or the page itself), as long as it stays a public page the AI Mentor can read.
