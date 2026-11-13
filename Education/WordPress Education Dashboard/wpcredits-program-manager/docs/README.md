# Program documentation

Five guides, one per audience:

| Guide | Contains | Published as |
| --- | --- | --- |
| [Student guide](students.md) | The Student Report Card, booking a call and what to expect from the program. | `/student-guide/`, Student level |
| [Mentor guide](mentors.md) | The Mentor Report Card, availability, mentoring tips, check-in templates and a checklist, **and the whole student guide**. | `/mentor-guide/`, Mentor level |
| [Administrator guide](administrators.md) | The jobs an Administrator does as numbered steps, then a reference for wp-admin, settings, tools, access levels and the sync. It links to the Student and Mentor guides and does not repeat them. | `/administrator-guide/`, Administrators only |
| [Sponsor guide](sponsors.md) | The Sponsor Dashboard, the sponsorship and its events, offers and codes, posts, mentors, the company and its agreement, and what a company can see. | `/sponsor-guide/`, Sponsor level and Administrators, republished by each release |
| [Institution guide](institutions.md) | The Institution Dashboard: students, enrollment, the semester report, the institution's details and the Collaboration Agreement. | `/institution-guide/`, Institution level and Administrators, republished by each release |

## Which guide carries which

The plugin's access levels do not nest. A mentor holds `wpcpm_view_mentor_content` and nothing
else, so a mentor opening a Student-level page gets the restricted notice. A Mentor guide that
only linked to the Student guide would send them somewhere they cannot go. So the Mentor guide
carries the whole Student guide.

An Administrator can open every level: a user with the manage capability reads every page
(`can_view()` in `includes/class-wpcpm-content-access.php`). So the Administrator guide no longer
repeats the Student and Mentor guides. It links to them. The Sponsor and Institution guides stand
alone: they carry the signing-in section they share with the others and nothing of the Student
or Mentor guides.

The guides' contents live in `bin/docs-guides.php`, one table the builder and the tests read. A guide's
`reference` list names the parts whose `##` headings the block output marks with the class `wpcpm-guide-ref`.

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

In a section, `[[Heading text]]` points at the heading with exactly that text in the same guide: a link in
the block output, `[Heading text](#anchor)` in the Markdown. Each guide resolves its own pointers, so a shared
section can point at a heading only if every guide that carries it has that heading once. A pointer to a
heading that is missing, renamed or written twice stops the build and names the guide, section and pointer.

## Images

`images/` holds the screenshots the guides use. They are **mockups, not captures of the live
site**: each one is the plugin's own markup and the theme's real stylesheets, filled with
invented people. A real Student Report Card carries a student's name, email, photo and call
notes, and these documents are read by every mentor and student on the program.

The sources are not in this repository - they are rebuilt from the plugin's CSS whenever the UI
moves. Attachment IDs for the published copies are in `bin/build-docs.php`.

## Clips

A line in a section that is exactly `{{clip:<slug>}}` is a short video. `clips.json` maps each slug to
`{ "id", "src", "poster", "captions", "width", "height", "caption" }`: the attachment id, the URLs of the
.mp4, the poster .jpg and the .vtt captions, the size and one sentence. The block output is a core video
block with controls, nothing preloaded and the captions as a track, never autoplay; the Markdown output is
`*Clip: <caption>*`. A slug missing from the table, or an entry missing a field, stops the build.
