## The plugin in wp-admin

Everything the program manager touches lives under one top-level menu, **WPCredits Program**. You
need the `wpcpm_manage_program` capability to see it, which the Administrator role is granted on
activation. Every screen under it is titled with its own name: **Overview**, a screen for each
audience, **Tools** and a screen for each tool, and **Settings**.

### The words the screens use

The screens, the settings and this guide call each thing by one name.

| Name | What it is | Where you meet it |
| --- | --- | --- |
| **WPCredits Program** | The plugin, as the menu names it. Each screen under the menu is titled with its own name alone: **Overview**, **Students**, **Tools**, **Settings** and so on. | The menu, and a screen's place in it, written with ">" between the steps, such as **WPCredits Program > Settings**. |
| **Audiences** | The people the program is for and the people who run it: **Students**, **Mentors**, **Institutions**, **Sponsors** and **Program managers**. Each audience has a user role and a screen; program managers hold WordPress's own Administrator role, so their screen is **Administrators**. | The Overview's first cards, the menu, and the Settings tabs. |
| **Tools** | The parts of the program that are run and configured on their own rather than belonging to one audience: **Header notices**, **Need help?**, the **Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**, each called by its name alone. | The **Tools** menu item and screen, and the Overview's last cards. |
| **Landing page** | Where an account goes when it logs in: the **Mentor**, **Student**, **Institution** and **Sponsor landing page**, each with the page's address under its switch. | The Students and mentors, Institutions and Sponsors tabs of Settings. |
| **Remove** and **Leave** | The two answers of each rule for somebody who leaves the program, always in this order: remove the role or the access, or leave it in place. Nothing is ever deleted either way. | The Settings sections **When someone leaves**, **When an institution leaves the pipeline** and **When a sponsor is no longer Approved**. |

### Overview

{{image:admin-overview|The Overview screen: each audience with its role, its account count and its screen.}}

One card per audience, in menu order, each showing its role slug, how many accounts hold that role,
and whether it is built or currently role-only. Underneath, under **Tools**, one card per tool.

If Airtable is not connected yet, this screen says so and links straight to the setting.

### The audience screens

| Screen | What it does |
| --- | --- |
| **Students** | Two tabs: **Accounts**, the invitations and every Student account in one list, and **Sync**, the students sync and its last report. |
| **Mentors** | The mentor list, the sync report, and one-at-a-time invitations. |
| **Institutions** | The institutions sync, the applications and signed agreements waiting to be read, every institution record by stage, account creation, the reconciliation of Students with Students Reports, the consent report, the agreements whose state Airtable disagrees with, every semester report, the plugin's copy of the Collaboration Agreement, and the check of how the host serves the private files. |
| **Sponsors** | The sponsors sync, every sponsor with its status, program contact and accounts, Create account and Attach account, the offers and claims, the interests log, the agreements, and the sponsor applications waiting for a decision. |
| **Administrators** | Lists the program capabilities granted to Administrator, and who holds the role: the program managers. |

Since 1.92.0 the Administrator Dashboard on the front end gathers every queue these screens hold; the Administrators screen links to it.

Since 1.93.0 the Sponsors screen is no longer a placeholder: it holds the sponsors sync, every sponsor with its status, program contact and accounts, the Create account and Attach account controls, and the log of interests sponsors expressed on their dashboard.

Since 1.97.0 the Sponsors screen also holds the queue of companies that applied through the form on the site, with the six decisions the Institutions screen has for its own applications, and its menu entry carries a bubble counting the applications, the signed agreements and the sponsor posts waiting for a manager.

Since 1.118.0 the Students screen is two tabs, and a press on either tab comes back to it. **Accounts**, the tab **WPCredits Program > Students** opens on, holds the **Invitations** card and the **Student accounts** list. **Sync** holds the last sync's error when it ended in one, the **Airtable sync** card with **Sync students now**, or the run's progress and **Cancel sync** while one is going, and the **Last sync report**.

The **Student accounts** list works like WordPress's own **Users** screen: every Student account, a page at a time, so each one can be reached however many the site has. **Search students** finds an account by name, username, email address or institution. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. Pressing the **Student**, **Username** or **Institution** heading sorts the list by it, A to Z and then back. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Student** column cannot be hidden, because each row's actions sit under it. The institution picker above the table, each institution with its number of accounts, narrows the list to one institution, and the **Invitations** card follows it, so its button invites only the students at that institution who have never been invited, and comes back to that institution's list. The picker, a search by institution and the **Institution** sort work on the whole list, never only on the page in view.

Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens that student's Student Report Card, and **Send invite** or **Resend invite**, which emails that one student at once. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**. **Send invite** queues a first invitation for each ticked account that has never had one, as the card's button does, and the card shows the progress. **Resend invite** queues a fresh invitation for each ticked account invited before, which goes out with the next batch: the card's progress bar and its stop button follow a run of first invitations, and a re-invitation starts no run, so on its own it shows on neither. A row's invitation and the bulk actions both come back to the list as it was: the same view, search, institution, sort and page. However it is asked for, nobody is sent a second invitation within fifteen minutes of the last one: **Resend invite** on ticked accounts leaves out anybody invited in the last fifteen minutes, and its notice says how many it left out.

### Tools

The **Tools** submenu lists the parts of the program that are run and configured on their own
rather than belonging to one audience - currently **Header notices**, **Need help?**, the **Mentor
Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
behind an *Open tool* button. Three of them, the **Mentor Status Checker**, the
**Student Duplicate Finder** and **Need help?**, keep their settings in a **Settings** section at the
top of that screen, rather than on the Settings screen.
