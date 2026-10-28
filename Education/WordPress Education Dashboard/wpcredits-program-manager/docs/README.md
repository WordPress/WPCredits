# Program documentation

Five guides, one per audience:

| Guide | Contains | Published as |
| --- | --- | --- |
| [Student guide](students.md) | The Student Report Card and booking a call. | `/student-guide/`, Student level |
| [Mentor guide](mentors.md) | The Mentor Report Card, availability, **and the whole student guide**. | `/mentor-guide/`, Mentor level |
| [Administrator guide](administrators.md) | wp-admin, settings, access levels, the sync, **and the mentor and student guides**. | `/administrator-guide/`, Administrators only |
| [Sponsor guide](sponsors.md) | The Sponsor Dashboard, the sponsorship and its events, offers and codes, posts, mentors, the company and its agreement, and what a company can see. | `/sponsor-guide/`, Sponsor level and Administrators, republished by each release |
| [Institution guide](institutions.md) | The Institution Dashboard: students, enrollment, the semester report, the institution's details and the Collaboration Agreement. | `/institution-guide/`, Institution level and Administrators, republished by each release |

## Why each guide repeats the ones below it

The plugin's access levels do not nest. A mentor holds `wpcpm_view_mentor_content` and nothing
else, so a mentor opening a Student-level page gets the restricted notice - a mentor guide that
*linked* to the student guide would be sending them somewhere they cannot go. Each guide is
therefore complete on its own. The sponsor and institution guides stand alone: they carry the
signing-in section they share with the others and nothing of the student or mentor guides.

## Do not edit the guides

`students.md`, `mentors.md`, `administrators.md`, `sponsors.md`, `institutions.md` and everything in
`build/` are generated. Edit the sections in `sections/` and rebuild:

```
php bin/build-docs.php
```

That rewrites the Markdown for reading here. To rebuild the block markup for the published
pages, point the image base at the uploads directory:

```
php bin/build-docs.php --base=https://example.com/wp-content/uploads/2026/08
```

Then update the pages from `build/*.html`:

```
wp post update <id> docs/build/students.html
```

A section used by more than one guide is written once and changes everywhere at the next build,
which is the whole reason for the split.

## Images

`images/` holds the screenshots the guides use. They are **mockups, not captures of the live
site**: each one is the plugin's own markup and the theme's real stylesheets, filled with
invented people. A real Student Report Card carries a student's name, email, photo and call
notes, and these documents are read by every mentor and student on the program.

The sources are not in this repository - they are rebuilt from the plugin's CSS whenever the UI
moves. Attachment IDs for the published copies are in `bin/build-docs.php`.
