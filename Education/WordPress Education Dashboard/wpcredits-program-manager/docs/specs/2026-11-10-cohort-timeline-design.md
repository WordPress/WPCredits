# Cohort timeline (design, 10 November 2026)

Plugin: WPCredits Program Manager. Next free version when this was written: 1.123.68.

## 1. Why

Program managers plan mentors months ahead of an intake, but nothing on the site shows when the
next students arrive or whether the mentors on the program can take them. The answer lives in the
Students table as start and end dates, scattered across rows and institutions, and the mentor count
lives on another screen. The cohort timeline puts both on one page: how many students are on the
program each month for the next twelve, how many of them are new, how many places the active
mentors have, and where the program runs short.

## 2. The product owner's decisions (10 November 2026)

1. **Placement.** A summary card on the Administrator Dashboard that opens a full-width timeline
   view drawn in the same dashboard style.
2. **Who counts.** Enrolled students with a future start date, registrations still in review, and
   the students already in progress (so the overlap with the current cohort shows). Interested
   leads do not count.
3. **Time scale.** Months, twelve ahead, with the existing half-year cohort bands
   (`WPCPM_Cohort`, January to June and July to December) drawn above them.
4. **Capacity.** A setting, *Students per mentor*, default 3. Places in a month are the active
   mentors times that number.

The rest is the builder's account of how, with the builder's calls marked as such.

## 3. What a student is on the timeline

Every row of the roster index (every institution's roster and the rows that name no institution,
read once each) and every site registration not yet in the index, classified once:

| Kind | Rows | Drawn as |
|---|---|---|
| `continuing` | a tracked active status (`WPCPM_Mentors_Sync::tracked_statuses()['active']`), start on or before today | gray, de-emphasized |
| `starting` | a tracked active status, start after today | brand blue |
| `review` | Students rows with no status (a registration written to Airtable, waiting for a program manager) whose window is not over; site registrations `waiting` or `failed`, and `written` ones whose Students record the index does not hold yet | teal, with a diagonal hatch |

Left out: `SPAM`, `Duplicated`, `Interested`, every past status, every other status, rows with no
usable start date, rows of a test institution (`WPCPM_Test_Institutions::test_ids()`), and any row
whose window ended before the current month.

**A student's window** is start to end, both `Y-m-d` read off the string as `WPCPM_Cohort::key()`
reads them. **Builder's call:** a row with no usable end date is given one four months after its
start (`DEFAULT_MONTHS`), and the view says how many rows were estimated this way, because a row
that never ends would hold a mentor's place for ever. A row whose end is before its start is
treated the same way.

## 4. The numbers, per month

For each of the twelve months from the current one:

- **On the program** (`load`): students whose window overlaps the month, by kind.
- **Starting** (`starting`): students whose start falls in the month, confirmed and in review.
- **Finishing** (`ending`): confirmed students whose end falls in the month, which frees places.
- **Without a mentor** (`unassigned`): confirmed students on the program that month with no
  mentor yet (`WPCPM_Roster_Index::is_waiting()`); every registration in review has none.
- **Places**: active mentors (`WPCPM_Mentor_Engagement`'s active count) times students per mentor.
  Flat across the months: the site cannot know who will join or leave.
- **Balance**: places minus confirmed load, and places minus the whole load.
- **State**: `ok` when the whole load fits, `tight` when the confirmed load fits and the review
  layer does not, `short` when even the confirmed load does not.
- **Mentors needed**: `ceil( load / ratio )`, and to recruit: that minus active mentors, at least 0.

The summary is the next six months: students starting (confirmed, in review), the peak month and
its load, the first month that is short or tight, and the mentors to recruit to cover the peak.

## 5. Screens

**Card** `Upcoming cohorts`, the Watch group's first card: count = students starting in the next
six months; the phrase says the first shortage ("2 more mentors needed by March 2027") or that the
mentors cover the next six months. Body: four tiles and a six-month mini chart, then the action
*Open the cohort timeline*.

**View** at the Administrator Dashboard's address with `?wpcpm_view=cohort-timeline`, gated as the
page is. A way back, the title, one sentence, the read time; four tiles (starting in six months,
active mentors, places, mentors to recruit); the chart (stacked columns per month in the three
kinds, a single places line, the half-year bands above, a label over each short month, a legend,
a hover and focus tooltip per month); the month table that holds every number the chart draws;
the intakes list (each month with starts, the institutions sending them and the tracks); a note on
the ratio and estimated end dates, with a link to the setting.

The chart is plain HTML and CSS (no script, no library). Colors were run through the dataviz
validator: brand blue `#3858e9` and teal `#0d9488` pass every check in light mode; the gray is the
de-emphasis slot. Identity is never color alone: the legend, the hatch on review, the table.

## 6. Setting

`students_per_mentor`, int, default 3, 1 to 20, scope `people`, a section *Cohort planning* on the
Students and mentors tab.

## 7. Not in this release

A CLI command and an ability for the same numbers; per-mentor capacity; forecasting mentor joins
and leaves; contribution-team matching.
