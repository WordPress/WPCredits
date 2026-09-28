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
| **Students** | The student list, the sync report, and one-at-a-time invitations. |
| **Mentors** | The mentor list, the sync report, and one-at-a-time invitations. |
| **Institutions** | The institutions sync, the applications and signed agreements waiting to be read, every institution record by stage, account creation, the reconciliation of Students with Students Reports, the consent report, the agreements whose state Airtable disagrees with, every semester report, the plugin's copy of the Collaboration Agreement, and the check of how the host serves the private files. |
| **Sponsors** | The sponsors sync, every sponsor with its status, program contact and accounts, Create account and Attach account, the offers and claims, the interests log, the agreements, and the sponsor applications waiting for a decision. |
| **Administrators** | Lists the program capabilities granted to Administrator, and who holds the role: the program managers. |

Since 1.92.0 the Administrator Dashboard on the front end gathers every queue these screens hold; the Administrators screen links to it.

Since 1.93.0 the Sponsors screen is no longer a placeholder: it holds the sponsors sync, every sponsor with its status, program contact and accounts, the Create account and Attach account controls, and the log of interests sponsors expressed on their dashboard.

Since 1.97.0 the Sponsors screen also holds the queue of companies that applied through the form on the site, with the six decisions the Institutions screen has for its own applications, and its menu entry carries a bubble counting the applications, the signed agreements and the sponsor posts waiting for a manager.

### Tools

The **Tools** submenu lists the parts of the program that are run and configured on their own
rather than belonging to one audience - currently **Header notices**, **Need help?**, the **Mentor
Status Checker**, the **Student Duplicate Finder** and the **Track Builder**. Each has its own screen
behind an *Open tool* button. Three of them, the **Mentor Status Checker**, the
**Student Duplicate Finder** and **Need help?**, keep their settings in a **Settings** section at the
top of that screen, rather than on the Settings screen.
