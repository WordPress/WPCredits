# Program documentation

Three guides, one per audience:

| Guide | Contains | Published as |
| --- | --- | --- |
| [Student guide](students.md) | The Student Report Card and booking a call. | `/student-guide/`, Student level |
| [Mentor guide](mentors.md) | The Mentor Report Card, availability, **and the whole student guide**. | `/mentor-guide/`, Mentor level |
| [Program manager guide](administrators.md) | wp-admin, settings, access levels, the sync, **and both guides above**. | `/program-manager-guide/`, Administrators only |

## Why each guide repeats the ones below it

The plugin's access levels do not nest. A mentor holds `wpcpm_view_mentor_content` and nothing
else, so a mentor opening a Student-level page gets the restricted notice — a mentor guide that
*linked* to the student guide would be sending them somewhere they cannot go. Each guide is
therefore complete on its own.

## Do not edit the guides

`students.md`, `mentors.md`, `administrators.md` and everything in `build/` are generated. Edit
the sections in `sections/` and rebuild:

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
invented people. Real Report Cards carry students' names, emails, photos and call notes, and
these documents are read by every mentor and student on the program.

The sources are not in this repository — they are rebuilt from the plugin's CSS whenever the UI
moves. Attachment IDs for the published copies are in `bin/build-docs.php`.
