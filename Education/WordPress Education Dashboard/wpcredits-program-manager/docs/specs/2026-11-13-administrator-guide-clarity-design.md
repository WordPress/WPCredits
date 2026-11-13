# The Administrator guide, made usable (design)

13 November 2026. Scope: the Administrator guide only. The Student, Mentor, Institution and Sponsor guides wait until their dashboards are redrawn.

## Why

The program's Administrators cannot find answers in the guide. One of them asked in the Administrators' channel whether approving an institution's application comes before or after the Collaboration Agreement, and for "a step by step guide on what we have to do in the dashboard to keep track of the steps with institutions", and said the guide is very difficult to understand. Every such question lands on the product owner, who called it a blocker.

The guide today is about 82,000 words on one page. It is written as a reference for the person who runs the site: wp-admin screens, every setting, the sync, where data is kept. Its paragraphs run to several hundred words, it carries release history ("since 1.111.0"), and it gives no job as steps. About 12,000 of its words are the Student and Mentor guides repeated in full.

## Who reads it

Two readers, with different jobs:

1. **A program Administrator.** Decides applications, follows schools up, looks after students and mentors. Works on the Administrator Dashboard. Opens wp-admin rarely. Needs: what do I do now, which button, what happens when I press it, who gets an email.
2. **The Administrator who runs the site.** Settings, the sync, tools, emails, data. Needs the reference, and needs it to be true.

The guide serves the first reader first.

## Goals

- Every job an Administrator does is in the guide as numbered steps with the exact names on the screen, what happens next and who is told.
- An Administrator finds a job in seconds: a search box, a list of jobs, and a link from the place on the dashboard where the job is done.
- Every statement is true of the plugin as released. Each one is checked against the code, and a test keeps the names quoted in the guide tied to the names in the code.
- A short clip shows a job where a screen is easier to see than to read.
- The reference stays, shorter and in plain words, folded under the jobs.

## Not goals

- The other four guides.
- New program features. Where a job cannot be done on the site yet, the guide says where it is done.
- Changing what any screen does. The one addition to screens is a help link to the matching job.

## Anti-goals

- A sentence nobody checked against the code.
- Release history in the guide. The readme's changelog keeps it.
- A real person's or a real school's name in a screenshot, a clip or an example.
- A guide that needs its script to be read: without script it is still a complete page.

## The guide's shape

One page, in the Administrator Dashboard's frame, as today. Its parts, in order:

1. **Start here.** What the site does for an Administrator, the dashboard's views in a few lines, and how to get help. Under 300 words.
2. **Step by step.** The jobs, grouped: institutions, students, mentors, sponsors, your own account. Each job is one part with the same build:
   - a title that says the job as the reader would ("A school applied");
   - *When*: one line saying when this job comes up;
   - numbered steps, one action each, the control's name in bold exactly as on the screen;
   - *What happens next*: what the site does, who is emailed, what the other person sees;
   - *Good to know*: the two or three things people get wrong;
   - a clip or a picture where it helps;
   - a link to the reference for the detail.
3. **Where does it stand?** One table each for a school, a student and a mentor: what you see on the screen, what it means, what to do next.
4. **Reference.** The screens, the settings, the tools, access, running the site. Rewritten into short entries; each setting is one entry (its name, where it is, its choices, its default, what it changes). Folded, so the page opens on the jobs.
5. **The other guides.** Links to the Student guide and the Mentor guide, which an Administrator can open, in place of the copies.

## Finding things

- **Search.** A box above the list of sections. Typing filters the jobs and the reference to the parts that hold the words and marks the words in the text. It works on the page already loaded; nothing is sent anywhere.
- **The list of sections** beside the guide, as today, now led by the jobs.
- **Folded reference** uses the browser's own `details`, so the browser's find-in-page and a link to a heading inside open the right part.
- **Help links on the dashboard.** Each place where an Administrator decides something links to its job in the guide: one small link, in the corner of the card.

## Clips

- Short, silent or narrated, with captions, played only when pressed. Each shows one job from first click to result.
- Filmed on the trial copy of the site with every name replaced by an invented one.
- Stored in the site's media library, like the guide's pictures; the build names each by its attachment.

## Accuracy

- Before writing: a facts sheet per area (institutions, students, mentors), each fact with the file and line it comes from.
- Every part of the current reference is checked against the code by a reviewer who reports what is wrong, what cannot be found and what is out of date. The rewrite fixes each finding.
- After writing: a second reviewer checks each new job and each rewritten part against the code, sentence by sentence, and the jobs are walked through on the trial copy.
- A test reads the guide's sources and fails when a name in bold is not a name in the code, apart from a short list of names that belong to WordPress or to another service.

## What the builder may decide

The markup of a job, the search's exact behavior, the clip format and size, how the reference is split into entries, and the order of jobs inside a group.
