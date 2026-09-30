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
| **Audiences** | The people the program is for and the people who run it: **Students**, **Mentors**, **Institutions**, **Sponsors** and **Administrators**. Each audience has a user role and a screen under its name; the administrators hold WordPress's own Administrator role, which is granted the program's capabilities on activation. | The menu, each audience's own screen, and the Settings tabs. |
| **Tools** | The parts of the program that are run and configured on their own rather than belonging to one audience: **Header notices**, **Need help?**, the **Mentor Status Checker**, the **Student Duplicate Finder** and the **Track Builder**, each called by its name alone. | The **Tools** menu item and screen, and the Overview's **Tools** card, which lists each tool with its status. |
| **Landing page** | Where an account goes when it logs in: the **Mentor**, **Student**, **Institution** and **Sponsor landing page**, each with the page's address under its switch. | The Students and mentors, Institutions and Sponsors tabs of Settings. |
| **Remove** and **Leave** | The two answers of each rule for somebody who leaves the program, always in this order: remove the role or the access, or leave it in place. Nothing is ever deleted either way. | The Settings sections **When someone leaves**, **When an institution leaves the pipeline** and **When a sponsor is no longer Approved**. |

### Overview

{{image:admin-overview|The Overview screen: what waits for a decision, the syncs, the tools and the way to Settings.}}

**WPCredits Program** opens on the Overview: four cards with what waits for a decision, when each
sync last ran and runs next, whether each tool can run, and the way to Settings. The Overview only
reads and links; it decides nothing.

If Airtable is not connected yet, this screen says so and links straight to the setting.

**Waiting for a decision** lists each queue with something in it, in the order of the Administrator
Dashboard's strip of counts, by the strip's name for the queue with its count after it in
parentheses. Each is a link to where its queue is listed: the institution applications,
agreements, semester reports, mentor requests and locked accounts open the **Institutions** screen,
the sponsor agreements, applications and offers running low open the **Sponsors** screen, the
sponsor posts open the Administrator Dashboard's **Sponsor posts to review** card while that page
exists, and the duplicated students open the **Student Duplicate Finder**. The dashboard's strip
shows every count, a zero muted; this card leaves out a queue with nothing in it, and when nothing
is waiting it says "Nothing is waiting for a manager right now." At the foot of the card,
**Decide on the Administrator Dashboard** opens that page, where the decisions are made; while the
page is missing, the card says so in the button's place and asks you to re-activate the plugin to
recreate it.

**Syncs** has a row for each audience's sync, **Students**, **Mentors**, **Institutions** and
**Sponsors**, each name a link to the screen the sync is run from: the **Sync** tab of the Students
and Mentors screens, and the Institutions and Sponsors screens. **Last run** gives the date and time
of the last run, in the site's own formats, or *Never*. **Next run** gives the date and time of the
next run; it says *Not scheduled* when none is, and *Running now* while a run is under way. The
table shows no errors: a sync's last error is on the Administrator Dashboard's **Syncs and health**
card.

**Tools** has a row for each tool, its name a link to the tool's screen, and under **Status** the
line the tool's card shows on the **Tools** screen; for a tool that cannot run, that line is the
warning that says why, in the tool's own words.

**Settings** says "Airtable is connected." or "Airtable is not connected yet." Its
**Open Settings** button opens the Settings screen.

### The audience screens

| Screen | What it does |
| --- | --- |
| **Students** | Two tabs: **Accounts**, the invitations and every Student account in one list, and **Sync**, the students sync and its last report. |
| **Mentors** | Two tabs: **Accounts**, the invitations and every Mentor account in one list, and **Sync**, the mentors sync and its last report. |
| **Institutions** | The institutions sync, the applications and signed agreements waiting to be read, every institution record by stage, account creation, the reconciliation of Students with Students Reports, the consent report, the agreements whose state Airtable disagrees with, every semester report, the plugin's copy of the Collaboration Agreement, and the check of how the host serves the private files. |
| **Sponsors** | The sponsors sync, every sponsor with its status, program contact and accounts, Create account and Attach account, the offers and claims, the interests log, the agreements, and the sponsor applications waiting for a decision. |
| **Administrators** | Two tabs, under the button to the Administrator Dashboard while that page exists: **Accounts**, every administrator account in one list, and **Capabilities**, the program capabilities granted to Administrator. |

Since 1.92.0 the Administrator Dashboard on the front end gathers every queue these screens hold; the Administrators screen links to it.

Since 1.93.0 the Sponsors screen is no longer a placeholder: it holds the sponsors sync, every sponsor with its status, program contact and accounts, the Create account and Attach account controls, and the log of interests sponsors expressed on their dashboard.

Since 1.97.0 the Sponsors screen also holds the queue of companies that applied through the form on the site, with the six decisions the Institutions screen has for its own applications, and its menu entry carries a bubble counting the applications, the signed agreements and the sponsor posts waiting for a manager.

Since 1.118.0 the Students screen is two tabs, and a press on either tab comes back to it. **Accounts**, the tab **WPCredits Program > Students** opens on, holds the **Invitations** card and the **Student accounts** list. **Sync** holds the last sync's error when it ended in one, the **Airtable sync** card with **Sync students now**, or the run's progress and **Cancel sync** while one is going, and the **Last sync report**.

The **Student accounts** list works like WordPress's own **Users** screen: every Student account, a page at a time, so each one can be reached however many the site has. **Search students** finds an account by name, username, email address or institution. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. The list opens sorted by name, A to Z, so a first press on the **Student** heading turns it Z to A; a press on **Username** or **Institution** sorts the list by it, A to Z, and pressing a heading again reverses the order. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Student** column cannot be hidden, because each row's actions sit under it. The institution picker above the table, each institution with its number of accounts, narrows the list to one institution, and the **Invitations** card follows it, so its button invites only the students at that institution who have never been invited, and comes back to that institution's list. The picker, a search by institution and the **Institution** sort work on the whole list, never only on the page in view.

Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens that student's Student Report Card, and **Send invite** or **Resend invite**, which emails that one student at once. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**. **Send invite** queues a first invitation for each ticked account that has never had one, as the card's button does, and the card shows the progress. **Resend invite** queues a fresh invitation for each ticked account invited before, which goes out with the next batch: the card's progress bar and its stop button follow a run of first invitations, and a re-invitation starts no run, so on its own it shows on neither. A row's invitation and the bulk actions both come back to the list as it was: the same view, search, institution, sort and page. However it is asked for, nobody is sent a second invitation within fifteen minutes of the last one: **Resend invite** on ticked accounts leaves out anybody invited in the last fifteen minutes, and its notice says how many it left out.

Since 1.119.0 the Mentors screen is two tabs in the same way, and a press on either tab comes back to it. **Accounts**, the tab **WPCredits Program > Mentors** opens on, holds the **Invitations** card and the **Mentor accounts** list. **Sync** holds the last sync's error when it ended in one, the warning while institution and team names have not been read yet, the **Airtable sync** card with **Sync mentors now**, or the run's progress and **Cancel sync** while one is going, and the **Last sync report**.

The **Mentor accounts** list works as the **Student accounts** list does: every Mentor account, a page at a time. **Search mentors** finds an account by name, username or email address. The **All**, **Invited** and **Never invited** views split the list by whether an account has been sent an invitation, each with its count. The list opens sorted by name, A to Z, so a first press on the **Mentor** heading turns it Z to A, and **Username** sorts it by username, A to Z. **Students** sorts it by how many current students a mentor has, the fewest first, and **Status** puts **Active** before **Not in Airtable**; pressing a heading again reverses the order. Every sort works on the whole list, never only on the page in view. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Mentor** column cannot be hidden, because each row's actions sit under it. The list has no institution picker, so the **Invitations** card counts, and its button invites, every mentor who has never been invited.

Under each name are **Edit**, which opens the account in wp-admin, **View page**, which opens that mentor's Mentor Report Card, and **Send invite** or **Resend invite**, which emails that one mentor at once. To invite several, tick them, choose **Send invite** or **Resend invite** under **Bulk actions** and press **Apply**; both work as they do on the Students screen, through the same queue. A row's invitation and the bulk actions both come back to the list as it was: the same view, search, sort and page. Here too nobody is sent a second invitation within fifteen minutes of the last one, and **Resend invite** on ticked accounts says how many it left out.

Since 1.119.0 the Administrators screen is two tabs too, **Accounts** and **Capabilities**, under the **Open the Administrator Dashboard** button, which stays above them whichever tab is shown. The button is there while that page exists; while it is missing, the **Administrator accounts** list says so. **Accounts**, the tab **WPCredits Program > Administrators** opens on, holds the **Administrator accounts** list: every account with WordPress's Administrator role, a page at a time. **Search administrators** finds an account by name, username or email address. The list opens sorted by name, A to Z, so a first press on the **Name** heading turns it Z to A; a press on **Username** sorts it by username, A to Z, and pressing a heading again reverses the order. **Screen Options** sets how many rows a page holds, 20 by default, and which columns are shown; the **Name** column cannot be hidden, because each row's **Edit**, which opens the account in wp-admin, sits under it. **Can manage program** says whether each account holds the program's capability.

The list has one view, **All**, and no checkboxes, bulk actions or invitations: program managers use WordPress's own Administrator role, the plugin never invites them, and their accounts are added and managed on the **Users** screen. **Capabilities** lists the program capabilities the Administrator role is granted on activation, as the screen did before.

### Tools

The **Tools** submenu lists the parts of the program that are run and configured on their own
rather than belonging to one audience - currently **Header notices**, **Need help?**, the **Mentor
Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
behind an *Open tool* button. Three of them, the **Mentor Status Checker**, the
**Student Duplicate Finder** and **Need help?**, keep their settings in a **Settings** section at the
top of that screen, rather than on the Settings screen.
