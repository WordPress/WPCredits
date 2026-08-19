# WordPress Community CRM

**WordPress Community CRM** is the internal tool the WordPress Community team uses
to keep track of the people, organizations and sponsors around meetups and
WordCamps — the relationships that keep WordPress events running, without a new
spreadsheet every year.

It is not a single plugin. It is a small stack assembled on top of
[Jetpack CRM](https://wordpress.org/plugins/zero-bs-crm/): Jetpack CRM supplies the
contact and company records, a custom block theme supplies the front door, and a
companion plugin brings the team's support inbox onto the contact record.

This folder is a source mirror. Each subfolder is the unpacked, installable
source of one plugin or theme, kept in sync with its development repository.

## Release & testing

- **[#162 — Prerelease testing](https://github.com/WordPress/WPCredits/issues/162)** — open call for testing (testing window closes **31 August 2026**). Feedback welcome on that issue.

## What's in here

### Theme

| Folder | Name | Version | What it does |
| --- | --- | --- | --- |
| [`community-crm`](./community-crm) | **WordPress Community CRM** | 1.2.0 | Full Site Editing (block) theme — the front end of the CRM. Its front page doubles as the sign-in screen: one card holding an illustrated hero, a WordPress.org sign-in form and a request-access panel, framed by a thin header bar, under the [official wordpress.org global header](../WordPress.org%20Global%20Header%20and%20Footer), with the wordpress.org global footer closing the page. Built on WordPress Design System tokens (brand blue `#3858e9`, 13px system type, 4px spacing base, 2px control radius) mapped into `theme.json`, so the whole design is editable in the Site Editor. Ships a matching skin for `wp-login.php` so both sign-in routes look like the same product, plus templates for pages, posts, archives, search and 404, and patterns for the hero and the sign-in card. No build step. |

### Plugins

| Folder | Name | Version | What it does |
| --- | --- | --- | --- |
| [`jpcrm-freescout`](./jpcrm-freescout) | **Email Inbox for Jetpack CRM** | 1.0.4 | Brings a [FreeScout](https://freescout.net/) help desk into the WordPress dashboard and wires it into Jetpack CRM: a **Support Tickets** tab on every contact record, an **Inbox** screen under the CRM menu, replying and internal notes attributed to your own FreeScout agent account, ticket events written into the contact's CRM activity timeline, FreeScout registered as a CRM external source, and optional signed webhooks for near-real-time updates. Built on FreeScout's REST API rather than an iframe — which is what makes the CRM integration possible, and avoids the third-party-cookie and `X-Frame-Options` problems that come with embedding the FreeScout UI directly. |

## How the pieces fit together

- **Jetpack CRM** is the engine. It owns the contacts, companies and the activity
  timeline, and it lives in `wp-admin`. It is a third-party plugin and is *not*
  mirrored here — install it from the plugin directory.
- The **theme** is the front door. The site's homepage *is* the sign-in screen,
  so an organizer arriving at the site signs in and lands straight in the CRM
  dashboard. It also skins `wp-login.php` to match, so the two routes are
  visually the same product.
- The **plugin** closes the loop on correspondence. Support conversations from
  FreeScout appear on the CRM contact record rather than in a separate tab of a
  separate tool, and replies can be sent without leaving WordPress.

## Requirements

| | Requires | Tested up to | PHP | Also needs |
| --- | --- | --- | --- | --- |
| `community-crm` (theme) | WordPress 6.5 | 6.8 | 7.4 | WordPress.org Global Header and Footer |
| `jpcrm-freescout` (plugin) | WordPress 6.0 | 6.8 | 7.4 | Jetpack CRM 5.0+, and a FreeScout install with the free **API & Webhooks** module enabled |

## Notes

- The theme's name matches the internal product name. If it is ever published on
  WordPress.org the name will have to change — theme names there cannot contain
  "WordPress".
- The theme renders the standard WordPress login form; it does not itself
  authenticate against WordPress.org. An SSO plugin can print its button into the
  sign-in panel via the `ccrm_login_form_sso` action.
- `wp-login.php` deliberately stays reachable. It is the safety net if the front
  page's sign-in form is ever edited away, and password resets need it either
  way. Redirecting it to the themed homepage is opt-in via the
  `ccrm_redirect_wp_login` filter.
