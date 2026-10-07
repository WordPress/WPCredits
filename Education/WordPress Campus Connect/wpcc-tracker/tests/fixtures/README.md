# Fixtures are not published here

The test suite runs against two frozen exports of the Campus Connect Details report. Both are
withheld from this public copy, so `php tests/run-tests.php` will not run from it.

They are exports of the **whole** report, which is most of the point: the suite exists to prove
the normaliser buckets the planning pipeline correctly, and the pipeline is the part the public
API does not carry. Of the 2026-10-07 export's 165 rows, 80 are applications that are declined,
cancelled, on hold or still being vetted. `WordCamp_Loader::get_public_post_statuses()` is
`['wcpt-scheduled', 'wcpt-closed']`, so Central does not list those events, and publishing the
fixtures would name the institution and city behind each one.

The same reasoning applies to `data/seed.json`, which does ship. It is built from Closed and
Scheduled rows only, so its planning figure reads 0 until the plugin first syncs. See
`bin/build-seed.php`.

To run the suite, export the report from Central yourself with these 13 columns, save it as
tab-separated text in this folder, and name it `campus-connect-details-<YYYY-MM-DD>.tsv`:

```
Start Date (YYYY-mm-dd)   End Date (YYYY-mm-dd)   Status   Name   Institution Name
City   Country   Number of Anticipated Attendees   Actual Attendees   Series Event
Created   URL   ID
```

Do not tick `Organizer Name` or any Wrangler, Mentor or E-mail column. The report form remembers
its last selection and re-ticks some of them on its own, so check the boxes immediately before
exporting rather than once at the start.
