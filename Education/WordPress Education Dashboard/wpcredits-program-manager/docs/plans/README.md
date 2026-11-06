# Implementation plans

One file per release line, named `YYYY-MM-DD-plugin-<version>-<slug>.md`, written before the code and followed task by task. A plan records the decisions an implementer cannot make alone: the files, the exact names and signatures, the values a specification pins, and the test that proves each step. The design a plan argues from is in [`../specs`](../specs).

Plans are not shipped. The built zip carries no `docs/` at all, so adding or changing one never needs a version bump.

## Plans kept outside this folder

A plan whose substance is the program's internal process rather than this plugin's code lives in the program's own vault instead, and only its title is listed here. Nothing in them is secret; they are simply about how the program works rather than how the plugin does, and that is not this repository's subject.

| Plan | Subject | Where |
| --- | --- | --- |
| Institution provenance on the site | Recording how each institution entered the pipeline, so the monthly growth post can tell institutions that applied from institutions the program added itself. Five tasks across this plugin and the Credits Monthly Report. | `WordPress Education/Reporting/Institution provenance on the site (plan).md` in the program's vault, beside the design it replaced |

If you are implementing one of these and do not have the vault, ask the program's Administrators for the file rather than working from this table: the table is a pointer, not a summary, and the ordering of the tasks in those plans carries most of their value.
