## Running it day to day

### The sync

Both syncs read Airtable and reconcile accounts: create what is missing, update what has changed,
and apply your *when they are no longer active* setting to the rest. Run one by hand from the
Students or Mentors screen, or leave **Daily sync** on.

Accounts are matched by WordPress.org username where there is one, and by email otherwise. **No
account is ever deleted by a sync** — the most it will do is remove a role.

The sync report on each screen says what happened rather than only that it finished: created,
updated, skipped and why.

### Invitations

An invitation is a password-reset link. Send them in bulk by switching **Invitation emails** on
before a sync, or one at a time from the Students and Mentors screens — which is the safer habit,
because a first sync creates around ninety accounts at once.

### Pages the plugin owns

Activation creates the Report Card pages and gates them. If one goes missing, re-activating the
plugin recreates it; the settings screen warns you when a page it expects is not there.

### Uninstall

Uninstall removes the settings, the sync state, the access-level meta and the custom roles, and moves
affected accounts back to Subscriber. **Accounts are never deleted**, and their program details in
Airtable are untouched.

### When something is wrong

| Symptom | Where to look |
| --- | --- |
| A student or mentor is missing | Their Airtable status, against the status settings. The sync only creates accounts for the statuses you list. |
| Details are stale on a Report Card | Run the sync by hand; the page renders from what the last sync stored. |
| A mentor sees the wrong students | The mentor↔student link in Airtable. The page joins on the records, not on names. |
| Nobody can book a call | The mentor has published no availability. Their Report Card says so. |
| Invitations are not arriving | The **Mail** section on Settings. "Accepted" means the site handed it off; anything else is between the site and its mail service. |
| A gated page is readable by the wrong people | The post's **Program access** control, and the reader's role. Administrators can read every level by design. |
