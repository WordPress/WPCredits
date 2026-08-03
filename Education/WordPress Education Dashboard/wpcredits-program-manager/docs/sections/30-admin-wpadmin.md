## The plugin in wp-admin

Everything the program manager touches lives under one top-level menu, **WPCredits Program**. You
need the `wpcpm_manage_program` capability to see it, which the Administrator role is granted on
activation.

{{image:admin-overview|The Overview screen. Each audience is a module with its own role, account count and screen.}}

### Overview

One card per module, in menu order, each showing its role slug, how many accounts hold that role,
and whether the module is built or is currently role-only. Underneath, the same treatment for the
parts of the program that can be run on their own.

If Airtable is not connected yet, this screen says so and links straight to the setting.

### The module screens

| Screen | What it does |
| --- | --- |
| **Students** | The student list, the sync report, and one-at-a-time invitations. |
| **Mentors** | The mentor list, the sync report, and one-at-a-time invitations. |
| **Institutions** | Role only — registers `wpcpm_institution` and reserves the screen. |
| **Sponsors** | Role only — registers `wpcpm_sponsor` and reserves the screen. |
| **Administrators** | Lists the program capabilities granted to Administrator, and who holds the role. |

A role-only screen tells you the role slug, whether it is registered, and how many accounts hold it.
That is deliberate: the role exists and can gate content from the day it is added, long before the
module has screens of its own.

### Modules

The **Modules** submenu lists the parts of the program that are run and configured on their own
rather than belonging to one audience — currently **Header notices**, **Need help?** and the **Mentor
Status Checker**. Each has its own screen behind an *Open tool* button.
