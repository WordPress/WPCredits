# Institutions release 3: the Sponsor Dashboard in the Institution Dashboard's shape

Design of 25 October 2026, for plugin main at 1.123.37 and theme 1.32.4. It details section 5 of the approved Institutions features design (`docs/specs/2026-10-24-institutions-features-design.md`), decision C6: the Sponsor Dashboard follows the Institution Dashboard. The product owner's answers of 25 October 2026 are marked as theirs; everything marked "author's call" is the author's, for the product owner to check. Status: written, waiting for the product owner's approval.

## 1. Why

The program now runs two dashboards for outside organizations: the Institution Dashboard for schools and the Sponsor Dashboard for companies. They grew apart. A school's page opens with where to get help and lets its people arrange its sections; a sponsor's page puts help at the very foot and opens with a strip of figures. A program team that supports both, and an organization that sees one, should find the same page shape, the same help in the same place, and the same controls. The owner's rule for shared blocks (a block both dashboards draw follows the Institution Dashboard's rules exactly) applies throughout.

## 2. What the Sponsor Dashboard is today

Read on the Docker copy at plugin 1.123.36 and theme 1.32.4 (screenshots kept beside the session backups, not mirrored: `.superpowers/session-backups/2026-10-25/institutions-release-3/`).

- **Order** (`WPCPM_Sponsors_Dashboard::render()`): the Administrator's Viewing as switcher, the flash, the identity header (`render_identity()`), the At a glance strip (`render_glance()`), four groups drawn through `WPCPM_Dashboard_Card::group()`, then the help (`render_help()`).
- **The four groups**, each a heading and a lead over folded cards:
  - Your sponsorship: the tier card (with "Talk to your program contact"), the yearly report card, the badge;
  - What you offer: offers and codes, usage, posts;
  - Mentors: the mentors the company sponsors and the ones looking for a sponsor;
  - Your company: the company profile and logo, People, the Collaboration Agreement.
- **The At a glance strip** (`glance_tiles()`, the owner's design of 27 September 2026): the tier and its term, the next event, the offers live with this month's claims, the mentors sponsored, the yearly reports; a tile at zero is muted; nothing is drawn when fewer than two tiles would be.
- **The help** (`render_help()` through `WPCPM_Handbook_Assistant::render_resources( 'sponsor', ... )`): program updates and announcements, Resources (Slack, the Sponsor guide, Need help?) and, as a third column, the contact block (`contact_block()`), which names the sponsor's assigned Administrator (`WPCPM_Sponsors_Index::manager_of()`: name, email, booking link) and prints nothing when none is assigned.
- **The header** shows the company's name, logo, website and "Contact:" with the contact's name and email address.

What the Institution Dashboard does differently (`WPCPM_Institutions_Dashboard::render()`): the help comes right under the header, with "Your contact at the program" under the buttons (`render_help()`); its sections are modules (`render_module()`) with a lead and Move up and Move down arrows (`WPCPM_Module_Order`, `ACTION_MOVE`, the order saved per record); the header's contact line shows the name only; a "Read from the program records on" line closes the page.

## 3. What changes

### 3.1 The page's order (the product owner)

From the top:

1. the Viewing as switcher (Administrators only) and the flash, as today;
2. the identity header;
3. the At a glance strip (section 3.2);
4. the help (section 3.3);
5. the four sections, as modules with arrows (section 3.4).

The strip sits above the help (the product owner: "Strip above help, as drawn").

### 3.2 The At a glance strip (the product owner: option A)

What a sponsor's people take in first: "Their tier and days left, then what needs their action" (the product owner).

- **The tier tile first**, worded as today's tier tile (`glance_term()`): the effective tier, and the running term's last day with the days left, or that it ends today, or the day it ended, or no term. It stays drawn only while the tiers are live (`WPCPM_Sponsor_Tiers::live()`), as today.
- **Then one tile for each kind of thing waiting on the sponsor's people**, its number the count of that kind, each a link to the card that holds it (the anchor `dashboard-open.js` opens, as today). The kinds, in this order:
  1. offers an Administrator returned with a note;
  2. offers running low on codes (the offer's own "Running low" test);
  3. offers not switched on yet (the offer state "Not switched on yet");
  4. posts returned with a note;
  5. events the program returned;
  6. the Collaboration Agreement returned;
  7. the company profile not complete (its number: how many of the profile's details are missing).
- **How each kind counts** (author's calls, settled after the builder check, so a tile and its card always agree):
  - **The offer kinds are exclusive**, in the order above: an offer counts once, under the first kind it matches. A new offer an Administrator returned sits in the draft state, so it counts as returned, never also as not switched on yet. A returned change to a live or paused offer, codes taken out included, counts as returned. "Running low" counts only offers that are live today (`is_live()`), not one past its last day that the card lists under ended and expired. "Not switched on yet" counts the drafts left over.
  - **Events** count 0 or 1: the latest event's returned note (`WPCPM_Sponsor_Event::returned_note()`), as the Events card shows it.
  - **The Collaboration Agreement** counts 0 or 1, while the card asks the company for a file (`asks_for_file()`): returned or revoked. The label says which: "Collaboration Agreement returned" or "Collaboration Agreement revoked".
  - **Every waiting tile is drawn only while its card is drawn**, as today's tiles are (`draws_events()` for events, the launch switch for the tier card's), so no tile links to a card that is not on the page.
- **The term's last 30 days, and a term that has ended,** make the tier tile a waiting item: it takes the waiting mark and opens the Sponsorship card, where the card says how to renew and, when it offers it, "Talk to your program contact" (the 30 days are the product owner's yes to the playback; the ended term is the author's call: it is the most urgent of the two). The program contact in the help is there either way.
- **A kind with nothing waiting draws no tile.** "Nothing waits for you", a muted tile with the number 0, is drawn only when nothing at all waits, the tier tile included; a waiting tier tile alone makes a strip of one tile. While the tiers are not live there is no tier tile, so the strip is what waits, or "Nothing waits for you" alone; a strip of one tile is allowed. A sponsor the store holds no row for draws no strip, as today.
- **Today's figure tiles leave the strip:** the next event, the offers live with this month's claims, the mentors sponsored and the yearly reports. Each figure stays beside its card's title where it already is; a figure that shows only in the strip today is added to its card's title line (author's call).
- **Rules:** the tiles keep the Administrator Dashboard's attention-strip markup and classes (`wpcpm-attention__tile`, `wpcpm-attention__link`, `wpcpm-attention__n`), so one set of rules dresses both; each tile's figure is read through its card's own readers, so a tile and its card never disagree; every number is written the way the site writes numbers. The old rule "nothing when fewer than two tiles are drawn" goes.
- **Labels** (author's call; singular and plural through `_n()`): "Offer returned with a note", "Offer running low on codes", "Offer not switched on yet", "Post returned with a note", "Event returned", "Collaboration Agreement returned" or "Collaboration Agreement revoked", "Company profile details missing", and "Nothing waits for you" with the number 0.
- **The waiting mark** is the leading edge in the Administrator Dashboard's attention color, never color alone: the label says what waits.

### 3.3 The help, moved up (the product owner)

- The help block moves from the foot to under the strip, "to easy access support" (the product owner).
- It takes the Institution Dashboard's help shape exactly: program updates and announcements beside Resources (Slack, the Sponsor guide, Need help?), and "Your contact at the program" under the buttons, not as a third column.
- **Your contact at the program** is the sponsor's assigned Administrator, as schools see theirs (the product owner): their name, a link to write to them, and Book a call, from `WPCPM_Sponsors_Index::manager_of()` as today. With none assigned the contact line is not drawn, as a school sees none until its country has a contact; "Talk to your program contact" keeps working either way, falling back as it does today (author's call).
- The words follow the Institution Dashboard's, with "institutions in your country" replaced by what is true for a sponsor: "They look after your company's sponsorship. Write to them about anything the guide does not answer." (author's call).

### 3.4 The four sections as modules

- The four groups become modules in the Institution Dashboard's shape: a heading, the group's lead as the module's lead, and Move up and Move down arrows named after the module ("Move Your sponsorship up").
- The order is the company's, kept for everyone who signs in for it, saved per sponsor key through `WPCPM_Module_Order` with a move handler of the Sponsor Dashboard's own, as the Institution Dashboard does: an option per record, repaired on read (`WPCPM_Module_Order::repair()`), moved in place by the shared script with the form as the fallback. The default order is today's.
- Who may arrange the page: the sponsor's members and Administrators, as on the Institution Dashboard (`$can_move`).
- A module whose cards all print nothing is not drawn and offers no arrows, as today's group is not printed; the first and last drawn modules disable their outer arrows, counting only what is drawn.
- The cards inside each module are unchanged.

### 3.5 The header

- The contact line shows the contact's name only, as the Institution Dashboard's does (author's call, the look-alike rule).
- Nothing else in the header changes.

### 3.6 What stays out

- No "Read from the program records on" line: sponsor records are kept on the site and no longer read from Airtable, so the line has nothing true to say.
- The cards' own contents, the offer form, the tier ladder, the claims, the events and the reports are unchanged.

## 4. Goals, non-goals, anti-goals

- **Goals:** one page shape for both dashboards; help and the program contact within the first screen; a strip that says the tier and what needs the sponsor now; sections the company can arrange.
- **Non-goals:** new sponsor features; changing what any card holds; the Institution Dashboard itself; the tiers' rules; Airtable.
- **Anti-goals:** a sponsor told something waits when it does not (a tile and its card must agree); a strip that names a tier before the tiers are live; any block shared by both dashboards dressed by rules of its own; losing a company's saved order on an update.

## 5. Constraints

- The owner's rules: no em or en dash anywhere; US English; "Administrators" for the program team; full product names; no person's name in code, tests, docs or commits; every number in the site's format.
- Shared blocks follow the Institution Dashboard's rules exactly, in the plugin and in the theme. Where the theme dresses the sponsor page's current shape (`.wpc-dashboard-page` rules for the sponsor groups, the strip, the help columns), those rules follow the new markup; a theme release goes with the plugin one, after it, since the theme's selector check reads the plugin's main.
- The spacing rhythm is measured on the Docker copy at 1280 and 375 pixels against the Institution Dashboard before any deploy.
- Versions: the next free numbers, plugin 1.123.38 and theme 1.32.5, unless another release takes them first.
- The Sponsor guide's sections (`docs/sections/40-` to `43-`) and the Administrator guide's paragraphs about the Sponsor Dashboard describe the new page; the guides rebuild and republish with the release.

## 6. Left to the builder

- How the waiting counts are read: through each card's own readers, with no second source.
- Whether the Sponsor Dashboard's move handler shares code with the Institution Dashboard's beyond `WPCPM_Module_Order`.
- The module keys (`sponsorship`, `offer`, `mentors`, `company`, today's group ids, are the natural ones).
- How the help's markup is shared between the two dashboards, as long as the output and its rules are the Institution Dashboard's.

## 7. Mockups

Kept beside the session backups, not mirrored: `.superpowers/session-backups/2026-10-25/institutions-release-3-mockups/` (`glance-strip-chosen-A.html`, `page-order-chosen.html`). The builder check of 25 October 2026 raised five questions on how the tiles count; section 3.2 settles them as the author's calls.
