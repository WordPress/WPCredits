## The feedback surveys

Students answer short surveys about the program on their Student Report Card, under **Feedback forms**. They are not marked, and the institution does not see them. The one exception is **Permissions**: what a student allows there can appear in their institution's semester report.

| Form | Asked of | Meant for |
|---|---|---|
| **Getting started** | A student who has not left | Once they have chosen a project |
| **Half way** | A student who has not left | The middle of their project |
| **Finishing up** | A student who has not left | The end of the program |
| **Leaving the program** | A student who left without finishing | Instead of the other three |

A student who left is one whose status is in **Past students** and does not read as graduated, completed, finished or passed. You can answer for a student on their card, except the questions under **Permissions**.

Step by step: [[Where a student's feedback answers are]].

### The three anchors

Three questions repeat word for word in the three stage forms, on a 1 to 5 scale:

- **Your overall experience so far**
- **How confident do you feel contributing?**
- **How well is your mentor's support helping you make progress?**

Together they show how one student's view changed.

### The retired questions

Eight questions of earlier surveys are no longer asked. The site never writes to their columns, so an answer there did not come from the site's forms.

### One stage at a time

A student sees **Getting started** first. **Half way** appears once it is fully answered, and **Finishing up** once both are.

- A follow-up that was never shown, and the **Permissions** at the end, do not count.
- A form someone has started is never taken away.

### Where the answers go

To Airtable's Feedback table, chosen as **Feedback table** on `WPCredits Program > Settings`, tab **Connection**.

- One row per student, with a column per question, starting `F1` to `F4` for the form. **Getting started**, **Half way** and **Finishing up** also record the student's mentor at that time, when they have one.
- The row is matched on the roster's email address. Another address makes a second row.
- Mentors never see the answers.

### Changing a question

Questions are in the plugin's code: a change needs a developer and a new plugin version.

- Each question writes to an Airtable column of exactly its name. That name starts `F1` to `F4` and is not the wording on the form, so look it up in the plugin's code.
- The column must exist in Airtable first.
- One wrong name makes Airtable refuse the whole submission. The student keeps the answers, but it fails until the name is fixed.
- An anchor must change in all three stage forms alike.
