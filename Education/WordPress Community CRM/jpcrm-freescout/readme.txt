=== Email Inbox for Jetpack CRM ===
Contributors: gomp
Tags: crm, jetpack crm, freescout, helpdesk, support
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Brings a FreeScout help desk into the WordPress dashboard and wires it into Jetpack CRM.

== Description ==

Puts your FreeScout support inbox inside WordPress and, more usefully, connects it to Jetpack CRM so support history shows up where you already work.

The inbox is built on FreeScout's REST API rather than an iframe, which is what makes the CRM integration possible — and avoids the third-party-cookie and `X-Frame-Options` problems that come with embedding the FreeScout UI directly.

**What it does**

* A **Support Tickets** tab on every CRM contact record, listing that person's conversations.
* An **Inbox** screen under the CRM menu: filter by status, search by subject, read full thread history.
* **Reply and add internal notes** from inside WordPress, correctly attributed to your own FreeScout agent account.
* **Open a new ticket** from a contact record.
* Ticket events written into the contact's **CRM activity timeline** — opened, customer replied, agent replied, note added, status changed.
* FreeScout registered as a CRM **external source**, so contacts created from the help desk are attributed and stay linked by customer ID.
* Optional **webhooks** for near-real-time updates instead of polling.

**Requirements**

* Jetpack CRM.
* A FreeScout install with the free, bundled **API & Webhooks** module enabled.

== How It Works ==

= Not an iframe =

The obvious way to put a help desk in the dashboard is to embed it in a frame. This plugin deliberately doesn't, for two reasons.

The practical one: FreeScout sends `X-Frame-Options: SAMEORIGIN` by default, so framing it requires setting `APP_X_FRAME_OPTIONS=false` in FreeScout's `.env`. And when WordPress and FreeScout sit on different domains, FreeScout's session cookie becomes a third-party cookie inside the frame — Safari blocks it outright, so the frame shows a login screen no matter how many times you sign in. Both fixes are server configuration a plugin cannot perform on your behalf.

The more important one: a frame integrates nothing. It is a browser inside your dashboard. Everything below is only possible because the plugin reads the API and renders the results itself.

= The connection =

You supply a FreeScout base URL and an API key, generated in FreeScout under Manage → Settings → API & Webhooks. Requests are authenticated with the `X-FreeScout-API-Key` header. The key is stored in your WordPress options table and is never shown again after saving — leave the field blank when re-saving to keep the existing key.

Read requests are cached for 60 seconds, so moving around the inbox doesn't issue a fresh API call for every click. Any write, and any inbound webhook, clears that cache immediately.

= Reading tickets =

Two screens read from the API:

* **CRM → Inbox** lists conversations for your default mailbox, filterable by status and searchable by subject. Opening one fetches the conversation with its threads embedded in a single request and renders the history oldest-first, like an email client. Internal notes are visually distinct from customer-visible messages. Status-change bookkeeping entries are filtered out so the history stays readable.
* **The Support Tickets tab** on a CRM contact record lists that person's conversations, matched on their email address.

Message bodies arrive as HTML and are passed through WordPress's `wp_kses_post()` before display, so help desk content cannot inject scripts into your admin.

= Replying, and why agent mapping exists =

FreeScout requires an agent ID on every reply or note created through its API. It identifies who the message is from.

This creates a problem worth understanding. The API key belongs to one FreeScout account, so the naive implementation attributes every reply sent from WordPress to that one account — regardless of who actually typed it. Your customers would see the wrong name, and you would most likely find out from a confused reply.

So the plugin maps each WordPress user to a real FreeScout agent, resolved in this order:

1. A manual agent ID set in the settings screen.
2. A previously resolved ID cached against the WordPress user.
3. A live lookup by email address, matching the agent's primary address first and then their alternate addresses.

If none of those produce a match, **the reply box does not render.** It is replaced by a message naming the email address that failed to match. This is intentional: refusing to send is recoverable, whereas a misattributed reply already sat in a customer's inbox under someone else's name.

Replying is limited to WordPress administrators. That is adjustable via the `jpcrm_fs_reply_capability` filter.

One related detail, in case you extend this: FreeScout's API accepts an `imported` flag that suppresses all outgoing email. It is meant for migrating historical conversations. The plugin strips it from every request unconditionally, because setting it on a genuine reply would mean the customer never receives it while everything appears to have worked.

= What gets written back to the CRM =

Ticket activity is recorded on the contact's normal CRM activity timeline, using registered log types so entries display with a proper label and icon: ticket opened, customer replied, agent replied, internal note added, and status changed.

FreeScout is also registered as a CRM external source. Contacts the plugin creates are attributed to it and stay linked by FreeScout customer ID, so the association survives an email address changing on either side.

The plugin does not add contacts to your CRM unless you ask it to — "Create missing contacts" is off by default. When it is on and a contact already exists, existing CRM fields are never blanked out by help desk data that happens to be empty.

= Webhooks (optional) =

Without webhooks the plugin polls, so ticket activity appears in the CRM the next time a screen is loaded. With them, it arrives as it happens.

Add the webhook URL shown in the settings screen to FreeScout, with a shared secret, and paste the same secret into the settings. Every delivery is verified as a base64-encoded HMAC-SHA1 of the raw request body before the payload is read; anything that fails verification is rejected. With no secret stored the endpoint is disabled entirely; once one is set, the "Remove the secret and turn webhooks off" checkbox disables it again.

Like the API key, the secret is never shown again after saving — leave the field blank when re-saving to keep it.

Logging is idempotent — a repeated delivery for the same thread will not produce a duplicate timeline entry. And when a webhook secret is configured, webhooks become the single source of truth for thread activity: the reply screen stops writing its own log entries, so a reply you send is recorded once rather than twice.

= Relationship to Help Scout =

FreeScout is a separate, open-source, self-hosted application modelled on Help Scout, and its REST API deliberately follows Help Scout's conventions — the `_embedded` response envelope, the thread model, and the webhook signing scheme are all recognisably the same shape.

**This plugin supports FreeScout only.** It is not compatible with Help Scout's hosted service, which uses different authentication (OAuth2 rather than a static API key) and different endpoints. The similarity means Help Scout support would be a smaller job than starting over, but it is not something this plugin currently does.

== Installation ==

1. Install and activate alongside Jetpack CRM.
2. In FreeScout, enable the **API & Webhooks** module, then go to Manage → Settings → API & Webhooks and generate an API key.
3. In WordPress, go to CRM → Settings → **FreeScout** and enter your help desk URL and API key.
4. Save, then reload the page and choose a default mailbox.
5. Check the **Agent mapping** table. Each administrator is matched to a FreeScout agent by email address; anyone without a match cannot reply until you set their agent ID manually.
6. Optionally, add the shown webhook URL in FreeScout with a shared secret, and paste the same secret into the settings.

== Frequently Asked Questions ==

= Does this work with Help Scout? =

No. It supports FreeScout, the self-hosted open-source help desk. Help Scout is a different, commercial product; despite the similar name and near-identical API design, it uses OAuth2 and different endpoints. See "Relationship to Help Scout" above.

= Why do I need agent mapping? =

FreeScout requires an agent ID on every reply sent through its API. Without a real mapping, every reply from WordPress would appear to come from whoever owns the API key. Rather than quietly misattribute replies, the plugin disables the reply box and tells you which account is unmapped.

= Who can reply from WordPress? =

Administrators. This is filterable via `jpcrm_fs_reply_capability` if you need to widen or narrow it.

= Does this modify my CRM contacts? =

Only if you opt in. "Create missing contacts" is off by default, so the plugin reads your CRM and writes activity-log entries but won't add contacts unless you ask it to. Existing contacts are never blanked out by help desk data.

= Do I need webhooks? =

No. Without them the plugin polls the API when you load a screen, with a short cache. With them, ticket activity lands in the CRM timeline as it happens. When a webhook secret is set, webhooks become the single source of truth for thread activity so nothing gets logged twice.

== Changelog ==

= 1.0.4 =
* Security: the webhook secret is no longer shown in the settings screen. It was
  rendered into a plain text field, putting the HMAC signing key on screen and in
  view-source; anyone who read it could forge webhook deliveries, which create
  contacts and write timeline entries. It now behaves like the API key — masked to
  its last four characters, blank to keep it — with a checkbox to remove it, since
  an empty field no longer means "turn webhooks off".

= 1.0.3 =
* Documentation: added a "How It Works" section covering why the integration uses the REST API rather than an iframe, how the connection and caching behave, how agent mapping resolves and why a reply is refused rather than misattributed, what gets written back to the CRM, and how signed webhooks work.
* Documentation: clarified that this plugin supports FreeScout and not Help Scout, which is a separate commercial product with a similar API design.

= 1.0.2 =
* Fixed: the FreeScout settings tab never appeared under CRM → Settings. Jetpack CRM's `postSettingsIncludes()` (init priority 10) calls `zeroBSCRM_freeExtensionsInit()`, which resets `$zeroBSCRM_extensionsInstalledList` to an empty array, and only afterwards reads it into `$zbs->extensions` — so registering by pushing onto that global from `plugins_loaded` was always discarded. Registration now hooks the `zbs_extensions_array` filter, which is applied where `$zbs->extensions` is built.

= 1.0.1 =
* Fixed: the plugin reported "needs Jetpack CRM to be installed and activated" on sites where Jetpack CRM was active. Jetpack CRM only assigns its `$zbs` global and defines `ZEROBSCRM_PATH` on `init` priority 0, so testing for either at `plugins_loaded` could never succeed. Detection now checks for the `ZeroBSCRM` class, which is loaded while the CRM's plugin file executes. Registration stays on `plugins_loaded` because the CRM applies `zbs_approved_sources` at `init` priority 10.

= 1.0.0 =
* Initial release.
* FreeScout REST API client with short-lived caching and error surfacing.
* Support Tickets tab on the CRM contact record.
* Inbox screen with status filters, subject search, and full thread history.
* Reply and internal notes, attributed via WordPress-user-to-FreeScout-agent mapping (email auto-match with manual override).
* New ticket creation from a contact record.
* CRM activity-log types for ticket opened, customer reply, agent reply, internal note, and status change.
* FreeScout registered as a CRM external source.
* Signed webhook receiver (HMAC-SHA1) with idempotent activity logging.
