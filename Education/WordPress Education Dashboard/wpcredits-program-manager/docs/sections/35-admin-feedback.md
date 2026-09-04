## The feedback surveys

Students are asked how the program is going three times - at the start, half way, and at the end -
from their own Report Card, under their report form. Anyone who leaves without finishing is asked a
fourth set instead: four questions about how far they got and what stopped them.

The question set is the one settled in
[#123](https://github.com/WordPress/WPCredits/issues/123) after the analysis of 242
responses from December 2025 to June 2026. Three things about it are worth knowing before anybody
changes a question.

### The three anchors

*Overall experience*, *how confident do you feel contributing* and *how well is your mentor's support
helping* repeat **word for word** in all three stage forms, on the same 1-to-5 scale. That is the
whole reason the surveys are split by stage rather than being one long form: the same student's
answers can be read as a line over time, and mentor support can be correlated against belonging and
intent to keep contributing.

Reword one of them in one form and the comparison quietly stops meaning anything. The test suite
asserts all three are identical across the three forms, so that mistake fails a build rather than
appearing in an analysis six months later.

### The retired questions

Eight questions were dropped by that analysis - for duplicating the question beside them, or for
returning the highest rate of empty answers. They still exist as columns in the table, so nothing
but a test stops one being added back. `bin/test-feedback.php` names all eight.

### One stage at a time

A student sees *Getting started* first; *Half way* appears once it is fully answered, and *Finishing
up* once both are. The reason is the anchors above: three identical questions are only comparable if
the answers are months apart, and a student who opens all three on their last day gives three copies
of one opinion.

Two things keep that from trapping anybody. A conditional follow-up that was never triggered does
not count as unanswered, so a form cannot sit one question short of complete for a question nobody
was asked; and a form somebody has already written in is never taken away, however incomplete the
one before it is.

The optional permissions at the end of Form 3 are not required either - a student who declines both
has still finished.

### Where the answers go

One row per student in the **Feedback table**, matched on email address, with a column per question
prefixed `F1`-`F4` for the stage that asked it. A stage fills in its own columns and leaves the rest
alone, so one row is one student's account of the program from beginning to end.

Two consequences:

- **Mentors do not see any of it.** The answers are not on the mentor's page, and several questions
  are about the mentor. Keep it that way - a student who thinks their mentor is reading writes
  something politer than the truth.
- **The row is matched by email.** A student whose feedback email differs from the one on their
  roster record gets a second row. The table's `Students` link would be the better key and nothing
  populates it yet; that is the open question in #123.

### Changing a question

Questions live in `WPCPM_Student_Feedback::forms()`, keyed by the exact Airtable column name.
Airtable refuses a whole record when one field name does not match, so a typo does not spoil one
answer - it loses the student's entire submission, and they are told only that it could not be sent.

`bin/test-feedback.php` pins all 44 column names and every single-select's choices against the
base's own schema. Run it after any change to the base:

```bash
php bin/test-feedback.php
```

A single-select answer is checked against the choices the column actually has before it is sent, so
a hand-edited form cannot add an option to the base or take a submission down with it.
