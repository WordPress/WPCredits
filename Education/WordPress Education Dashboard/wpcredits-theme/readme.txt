=== WPCredits ===

Contributors: gomp
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.32.3
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: education, full-site-editing, block-patterns, custom-colors, custom-logo, custom-menu, translation-ready, full-width-template, block-styles

A block theme and the front end for the WPCredits Program Manager plugin.

== Description ==

WPCredits gives the WordPress Credits Program a landing page, a branded login, and
a full-width "Mentor Report Card" page that dresses what the plugin draws there as a
compact, triaged, printable list.

= The chrome =

The header and footer reproduce the WordPress Education Initiatives theme: a 76px
sticky white bar on a 1240px measure with 40px gutters, the site title alone, with
the word "Education" picked out in blue and whatever follows it muted, a navigation
at the right edge whose dropdowns are 240px panels
with 8px corners and a soft shadow, and a four-column footer over a credit bar that
signs off "Code is poetry."

That measure is theme.json's `wideSize` and root padding too, so the brand lines up
with the content beneath it. Inside the dashboard the patterns are wp-admin native
instead - 13px body text, sentence case, 2px radii on controls, flat notices, no
shadows.

Four details are the theme's rather than the reference's, and are deliberate:

* The navigation marks the current menu item in brand blue. The reference's nav is
  hard-coded anchors with no such state; a real menu on a multi-page site needs one.
* The reference's blue "Join the Initiative" button is the account chip here - a
  sign-in button for visitors, and a link to each of the Student Report Card and the
  Mentor Report Card that the reader can open, plus a log-out link and profile photo
  once someone is signed in. It keeps the button's shape and position.
* The sign-in button stays in the bar below 960px rather than folding into the menu
  panel, because it is the way in to everything else on the site.
* Below 1200px a signed-in reader's header is one row: the brand, the account
  picture and a Menu button whose panel lists the navigation, Need help?, the Student
  Report Card and the Mentor Report Card the reader can open, and Log out, in the
  order of the wide row. Without JavaScript the header keeps its wrapped row of links.

= What the theme does and does not do =

The theme adds no program data and no plugin behavior. Every student, field and
note on the mentor page is rendered by WPCredits Program Manager; the theme styles
that markup and, with JavaScript available, groups and sorts what is already on the
page. Specifically it:

* Splits the plugin's one-line student preview into columns on wide screens, and
  puts that line back on narrow ones.
* Sorts students into "Need a call", "Ending soon" and "On track", with counts that
  double as filters. A student falls into the first group they match, so a call that
  is overdue always outranks an internship that is ending.
* Adds a search box over the students already listed.
* Lifts the plugin's "Expand all" / "Collapse all" buttons into a toolbar.
* Adds a "Add note" control to the rows of students who need a call, so the thing a mentor came
  to do is one press from the list.

With JavaScript blocked, the page is the plugin's list, styled: each student is a
native `<details>` element that opens on its own, and the plugin's own script still
handles printing.

= Triage windows =

Two numbers decide the grouping, both filterable:

* `wpcpm_stale_note_days` (in the plugin since 1.64.0; was `wpcredits_stale_note_days` here) - how long a student can go without a note before
  they need a call. Default 30, matching the plugin's own wording on the page.
* `wpcpm_ending_soon_days` (in the plugin since 1.64.0; was `wpcredits_ending_soon_days` here) - how close an internship end has to be to count as
  ending soon. Default 60.

Dates are compared against the site's today, not the browser's, so a mentor
traveling sees the same grouping as the program manager looking at the same list.

= Templates =

* Front page - the landing page, built from four patterns.
* `page-mentor-dashboard` - full width, no sidebar. Applies automatically to the
  page the plugin creates, which has the slug `mentor-dashboard`.
* Full width page - a custom template for any other page that needs the whole
  width.
* Page, single, index, search and 404.

= Patterns =

Four sections under "WPCredits program" in the inserter: the hero, the four
audiences, "How an internship runs", and the mentor call to action. The hero ships
with its own photo, so the landing page is showable the moment the theme is
activated.

The front-page template references the patterns, so editing a pattern changes the
front page - but only while the page is still referencing them. To edit the landing
page inline instead - to swap the hero photo, say, or change the mentor count - open
the front page in the Site Editor, select the pattern and detach it. **Detaching
copies the markup into the page**, and from then on that copy is what visitors see:
later changes to the pattern, and to anything set as a block attribute rather than in
CSS, stop reaching it. Styling still applies, because that comes from the stylesheet.

= The Education Hub homepage =

A page template, "Education Hub home", draws an alternative homepage built from the
program team's hub: a hero with the program's three outcome figures, a sign-in section
with a card each for students, mentors, institutions and sponsors, three doors for
students, institutions and companies, every education program as a card with filter
chips, a flow from each field of study through the teams it joins to what a student
will have to show, the pathway from a first workshop to a first job beside
an institution's four partner levels, and a closing band. It prints no post content,
so the page it is set on is only its address. Without JavaScript every part of it is
shown and no control is; with it the chips, the switch and the steps show one part at
a time. The figures come from WPCredits Program Manager 1.122.24 or later, and with an
older plugin, or none, the hero shows its copy alone.

The site's homepage is not changed by the template. Set the homepage under Settings >
Reading to a page that uses it, and the theme lets the template draw the front page;
choosing the old homepage again puts it back. Then set the hub's page back to Private, so
it is not left public at its own address.

= The login screen =

`wp-login.php` is not a template, so the branding is applied through the login
hooks: the header's own brand, the site's name, over the form, and a note about
where a first password comes from underneath it. Nothing about authentication
changes.

== Installation ==

1. Upload the theme and activate it.
2. Install and activate WPCredits Program Manager. 1.13.0 or later is what this
   version is built against; 1.7.2 is the floor, and everything newer than a given
   plugin degrades rather than breaking - every call into the plugin is guarded on the
   class or method existing. Without the plugin at all the theme is a small, working
   marketing theme; the dashboards and the viewer chip simply have nothing to show.

   Two things specifically need 1.13.0: the call calendar's styling, and
   `WPCPM_Mentors_Dashboard::current_mentor()`. On an older plugin the theme falls back
   to resolving the mentor itself, which is wrong for an administrator who also mentors
   - see Known limitations.
3. Build the header menu in the Site Editor. Top-level items with children get the
   reference's dropdown panels; items without stay plain links.
4. Set a static front page under Settings → Reading if you want the landing page.

The footer's three link columns are List blocks, not menus, so a site with one
navigation does not need three more. Point them at real pages in the Site Editor -
they ship with the reference's labels and `#` placeholders.

== Known limitations ==

* A student whose Airtable record has no ID is left ungrouped: the plugin omits the
  anchor for those rows, so there is nothing to match them to. They stay in the list
  in the plugin's own order.
* Visiting a gated page directly is handled by the plugin, not the theme: a
  logged-out visitor is redirected to the login form, and a logged-in one without
  the level gets `wp_die()`. That last screen is WordPress's own and is not themed
  here - restyling it would mean intercepting the plugin's denial. The plugin's
  restricted notice, which is what appears where a gated page is listed rather than
  opened, is styled.
* Rows in "Ending soon" and "On track" that have no report-form link carry no
  right-hand button. The disclosure triangle already opens the row, and a second
  control that does the same thing is one more thing for a screen reader to read
  out. The design shows a "Details" button there.
* The hero photo is bundled at `assets/images/hero-mentoring.jpg` and is what a fresh
  install shows, so `screenshot.png` is the theme as it arrives rather than the theme
  as somebody finished it. Replace the Image block in the Site Editor to use your own;
  1240x745 is the shape the column is drawn at, and the tint behind it means a
  different picture does not shift the page while it loads.
* On WPCredits Program Manager older than 1.13.0 the theme has to work out which
  mentor's list is on screen by itself, and gets it wrong for an administrator who is
  also an Active mentor in Airtable: it tests for the Mentor role, which the sync never
  gives an administrator. The plugin shows that person their own students while the
  triage payload describes a different mentor, and since the two join on Airtable record
  ID, nothing matches - rows lose their end date and note count. There is no fixing it
  from the theme; the plugin exposes `current_mentor()` from 1.13.0 precisely so the
  theme can stop guessing.

== Copyright ==

WPCredits bundles two typefaces for the Education Hub's pages, each under the SIL Open Font License, Version 1.1 (https://openfontlicense.org), with its license beside its files:

* EB Garamond, Copyright 2017 The EB Garamond Project Authors (https://github.com/octaviopardo/EBGaramond12), in `assets/fonts/eb-garamond/`.
* Inter, Copyright 2020 The Inter Project Authors (https://github.com/rsms/inter), in `assets/fonts/inter/`.

== Changelog ==

= 1.32.3 =
* On a tablet held upright (782 to 900 pixels wide), the four audience columns of the Education Hub (Students, Mentors, Institutions, Administrators) keep their space and the lines between them; they lost both there as if they had stacked, so the columns touched.

= 1.32.2 =
* The one-time updates of saved templates that came with 1.32.0 and 1.32.1 now hold a lock while they run, so two first visits at the same moment do not both update the same template. A lock that a broken visit left behind is taken over after ten minutes, a visit that finds the update already done once it holds the lock does nothing more, and a visit lets go only of its own lock.
* Where the site keeps only a few revisions and the copy kept of a template is pruned after the update, the update's record keeps the template as it was instead, and the Site Editor's notice says the copy is in the record.
* When a template holds more than one Partner levels block, the "Levels at a glance" table in the Site Editor reads the same one as the page: the template's own before one inside a synced pattern or template part.
* A 1.31 Partners cards block or levels table hidden in the Site Editor stays hidden after the update.

= 1.32.1 =
* The Partners page's five levels and its "Levels at a glance" table are edited in the Site Editor. The Partner levels block holds a Partner level for each level: its name, amount, tag and lead are edited on the card, its list and the table's short amount in the level's settings. The table is a block of its own, drawn from those levels, with one row for each item a level lists and Yes from that level up, so the cards and the table always agree; its rows now read as the items do. The sponsor application's "What each level includes" reads the same levels, so the form tells a company what the page told it. A level, or the whole section, hidden in the Site Editor is left out of the table and the form too.
* A Partners template already saved in the Site Editor is updated once, in place: the levels and the table become the new blocks, filled with what the page showed, and every other edit stays. The template as it was is kept as an exact revision, read back after it is written, and the Site Editor's notice says what changed. The Education Hub's Program index is marked as edited, so an index whose cards an Administrator removes stays empty instead of filling with the theme's programs again.
* Smaller fixes: the courses band keeps its space above it outside the Education Hub, the field of study list's headings are translated, and the filter chips count only the program cards shown at the window's size.

= 1.32.0 =
* The Education Hub template is edited in the Site Editor, every word of it. The Program index holds a Program card for each program: its name, what it is and its time are edited on the card, and its status, audiences, address on the page, benefits and links in the card's settings; cards can be added, removed and reordered, and the filter chips count the cards there are. Under it, the Courses band holds a Course card for each WordPress Credits course, with its name, link, hours and line. "Find where you fit" is a list of Field of study rows, each with its field, the teams it joins and what the student will have to show, where it was a block of code. The page looks as it did.
* A template already saved in the Site Editor is updated once, in place: the Program index and "Find where you fit" become the new blocks, filled with what the page showed, and every other edit stays. The template as it was is kept in its revisions, and the Site Editor shows Administrators a notice saying what changed until they dismiss it. A save from an editor opened before the update that brings the old blocks back is updated again at once.
* On a phone, the rows of an institution's students on the Institution Dashboard, the student card and the Mentor Report Card show each label above its value, without the "Value:" that stood before every value (1.31.12).

= 1.31.12 =
* On a phone, the rows of an institution's students on the Institution Dashboard, the student card and the Mentor Report Card show each label above its value, without the "Value:" that stood before every value.

= 1.31.11 =
* The header menu's *Become a partner* leads to the Partners page, whose own *Become a partner* section leads to the application, where it led to the sponsorship application (a program Administrator's ask, the site's owner's yes, 22 October 2026). While the Partners page is not published it leads to the application, and with neither to the WordPress Credits handbook. On the Partners page the item is marked as the page being viewed: the signed-in phone menu shows it in the brand blue, at its own weight.
* In the signed-in phone menu, *Become a partner* is a row like the others, with the rows' hairline under it, where it kept the wide row's outline on three sides with its words against the left line (since 1.31.9). On a wide screen it stays an outline button.
* The Partners page's five levels, each with its amount, its lead line and what it includes, are written once, in `wpcredits_partner_levels()`, and the theme hands them to the plugin's sponsor application through its `wpcpm_partner_levels` filter (plugin 1.123.24), which shows them in a closed *What each level includes* section under the level question. The page itself draws exactly as before. With a plugin older than 1.123.24 the filter is never asked and nothing changes.

= 1.31.10 =
* Sponsor forms: three rules that dressed nothing since plugin 1.123.22 are gone. The sponsor forms' control rule, on the Sponsor Dashboard and the Administrator Dashboard, no longer names a select, since the profile's Type of product was the only select a sponsor form drew; and the rule that put 24px above and 8px below a note following a field goes, since the profile's note on where offers are edited is now part of its first note and no sponsor form has a note after a field. Nothing on either page looks different. With a plugin older than 1.123.22, the profile's Type of product box would lose the theme's border and padding.

= 1.31.9 =
* The theme line takes main's 1.24.28 to 1.24.31, whose entries are further down: the Translation track on the Education Hub, its bigger hero picture, the hub as offered today, and its pathway and Companies cards set aside.
* The Partners page: the plugin's public page of the partnership levels, at /partners/ (plugin 1.123.18), is drawn by a template of its own in the Education Hub's look, with a program lead's words: what the program is and what partner support funds, with two figures of its size and the two ways to start; what support pays for, in three cards; "2027 is a pilot year"; the five levels, Friend, Supporter, Backer, Champion and Partner, each with its amount and what it adds; a table comparing them, which scrolls sideways in its own box on a narrow screen and by keyboard; how donated products are offered and how student work is used; the founding partners; the page's own content, the plugin's alphabetical listing of the partners; and the close, the application form and the education team's address, with the note on tax deductibility. The page stays a draft, and visitors are refused it, until the levels go public.
* The menu: while the partnership levels are public, the header's menu ends with "Become a partner", an outline button in the brand blue that leads to the sponsorship application. Before that there is no such item.
* Education Hub homepage: the Institutions door's button now reads "Partner as an institution", as the closing band's does, so the page's only "Become a partner" is the menu's, which leads to the sponsorship application.
* For developers: `templates/page-partners.html`, five patterns (`partners-hero`, `partners-funds`, `partners-levels`, `partners-commons`, `partners-apply`), `inc/partners.php` (the stylesheets on the Partners page alone; the menu item hooked into the navigation as its last child while `WPCPM_Sponsor_Tiers::live()` is true) and `assets/css/partners.css`, which builds on the hub's sheet; the menu item's rule in `style.css`. `bin/test-partners.php` checks the template, the words, the table against the levels, the links, the stylesheets and the menu item.

= 1.31.8 =
* The theme line takes main's 1.24.27, whose entry is further down: on a phone, the Administrator Dashboard's tables with column headers keep their frame around the visible window when scrolled sideways, since the frame is on the box that scrolls and not on the table.
* Sponsor Dashboard on a phone: the Usage card's table is in a box that scrolls sideways from 782px down (plugin 1.123.17), and its frame is treated the same way. The 1px line is on the box and the table inside has none, so the frame stands still while the columns move. Above 782px nothing changes. The Download as CSV button keeps its 16px above it: that gap is now said of the box the button follows, where it was said of the table. This release pairs with plugin 1.123.17; with an older plugin the box's rules dress nothing and the button loses the 16px.
* For developers: `bin/test-dashboard-table.php` pins the Usage card's two rules beside main's two for the Administrator Dashboard, all four in one phone query, and the button's gap; `bin/check-selectors.php` compares the plugin's two box rules (`.wpcpm-administrator__scroll` and `.wpcpm-usage__scroll`) and refuses a display, an overflow or a position on a table's own box or on a scroll box.

= 1.31.7 =
* The theme line takes main's 1.24.26, whose entry is further down: on the Education Hub homepage, "Find where you fit in WordPress Credits" is a flow from a field of study through the teams it joins to what a student will have to show, one row per field with the teams as tags, stacked on a phone, where it was a three-column table. Nothing else a reader sees changes, and no sponsors rule is touched: the sponsor strip on the Education Hub homepage, the sponsors page and the dashboards look as they did.
* For developers: `bin/test-hub.php` runs main's new checks (the flow's rows, what a screen reader hears, the strings and the rules at each width) beside this line's sponsor strip checks, and all of them pass; `bin/check-selectors.php` also holds the Sponsor Dashboard's Usage table and the Administrator Dashboard's queues to one phone rule, read off the plugin it is given.

= 1.31.6 =
* The theme line takes main's 1.24.22, 1.24.23, 1.24.24 and 1.24.25, whose entries are further down: on the Education Hub homepage, the mountain drawn in words behind the hero's picture; on the site header, the menu group ending at the content's right edge, the brand as the site's name alone without the WordPress mark (on the login screen too), and the site's name at twice the size, 30px from the Site Title style in theme.json. Nothing else a reader sees changes, and no sponsors rule is touched: the sponsor strip on the Education Hub homepage, the sponsors page and the dashboards look as they did.
* For developers: `bin/test-hub.php` and `bin/test-header.php` run main's new checks (the mountain, the header part's brand, the Site Title size) beside this line's sponsor strip checks, and all of them pass.

= 1.31.5 =
* The theme line takes main's 1.24.21, whose entry is further down: on the site header, below 1200px wide, the closed menu stays closed for a submenu that opens on hover and has no toggle, as it already did for one with a toggle. Nothing else a reader sees changes, and no sponsors rule is touched.

= 1.31.4 =
* The theme line takes main's 1.24.18, 1.24.19 and 1.24.20, whose entries are further down: the Education Hub homepage in the WordPress.org look, with EB Garamond and Inter shipped with the theme; the hero's eyebrow under the header and the wider stroke behind "Keep climbing."; and the site header's Menu button below 1200px for a signed-in reader, with the brand and Log in on one row on a signed-out phone.
* Education Hub homepage: the sponsor logo strip between the pathway and the closing band is drawn in the hub's new look. Its eyebrow is spaced as the hub's, 22px above the sponsors; each sponsor is a white card on a 1px hairline with 2px corners and no shadow, the cards in a grid 16px apart, two columns on a phone; a card whose sponsor has a website is the link, its hairline brand blue under the pointer; a sponsor without a logo is named in the serif, as a program is; and the link to the full list is underlined, as the doors' links are. The strip keeps its 48px above and below, so the page keeps its 96px between sections. The strip on the current homepage, the sponsors page and the dashboards look as before.
* For developers: `bin/test-hub.php` checks the strip's rules on the hub against the hub's own values, that each one names the strip on the hub alone, and that every sponsors rule away from the hub is as 1.31.3 shipped it; `bin/check-selectors.php` keeps main's check of the plugin classes the theme's scripts read beside this line's placement checks.

= 1.31.3 =
* The theme line takes main's 1.24.17, whose entry is further down: on the Education Hub homepage, the pathway's prompt above the steps and the cue at the foot of each step, the Builder step's benefits, and the band of the four WordPress Credits courses under the program index, with Step 4's link to the courses.
* Education Hub homepage: the sponsor logo strip is where 1.31.2 put it, between the pathway and the closing band, padded 48px above and below with no hairline above it; the pathway's new prompt and cues sit inside the pathway's own section, so the strip keeps its 96px to the pathway's last line and to the closing band.

= 1.31.2 =
* The theme line takes main's 1.24.15 and 1.24.16, whose entries are further down: the Education Hub homepage, its hero on a brand-tint panel, and the site header that wraps as designed above 640px.
* Education Hub homepage: the sponsor logo strip, the plugin's Sponsors block that the current homepage carries between its steps and its call to action, sits on the hub too, between the pathway and the closing band. It is padded 48px above and below, as every section of the hub is, so the page keeps its 96px between sections, and has no hairline above it, as no section of the hub has one; the homepage keeps its hairline. As on the homepage, the block prints nothing for visitors until the program turns the listing on, so the hub looks as before until then. The live site's Site Editor copy of the hub's template, once there is one, needs the block added by hand, as the homepage's did.
* The sponsors page's secondary button keeps the outline button's colors and edge, now in rules of its own, so the outline button's rule is one selector again, as the hub's checks read it; nothing on a page changes.
* For developers: on this line `bin/test-hub.php` checks the hub's seven children in their order, the strip being the homepage's own block once on each template, its 48px with no hairline, and the homepage's template as this line ships it, with the strip.

= 1.31.1 =
* The theme line takes main's 1.24.12, 1.24.13 and 1.24.14, whose entries are further down. The "Viewing as" field that plugin 1.123.6 draws, one box you click to open the names, type into to narrow them and pick from, is dressed as the list was: white background, gray border, 2px corners and 14px type. Its open list has the theme's soft shadow, 32px rows, a tint on the highlighted name and a bold name for the one being viewed. The WordPress chevron sits 8px in from the edge of the field, and of the plain list a page shows without JavaScript, one arrow and not two. A student's name too long for its line wraps, at a space, on the Mentor Report Card and on the Institution Dashboard's roster, where it ran past the card's edge on a phone.
* This release pairs with plugin 1.123.6, which takes the field from public plugin 1.122.15. With an older plugin the field's rules dress nothing, and the list's chevron and the names' wrap still apply.
* For developers: `bin/check-selectors.php` lists a class the plugin no longer prints but the theme still dresses for an older plugin as kept, with the last plugin version that printed it, instead of flagging it. The one entry is the no-match line of public plugin 1.122.14's search box.

= 1.31.0 =
* The Upcoming events section on the sponsors page, and the program's own event forms on the Administrator Dashboard with the Sponsor Dashboard's fields (plugin 1.120.0).

= 1.30.0 =
* The Administrator Dashboard's groups, its closed rows with their state and action, and the At a glance strip that says when nothing is waiting (plugin 1.119.0).

= 1.29.0 =
* The Sponsor Dashboard's At a glance tiles, its closed rows with their state and action, and the resources in three columns below the cards (plugin 1.118.0).

= 1.28.1 =
* The events from sponsors on the Institution Dashboard take the modules' inset, and on the Sponsor Dashboard a sub-heading inside a card stands clear of the text above it and the Change the event form opens with the room the offers' own form has (plugin 1.117.1).

= 1.28.0 =
* The dashboards' events list and the Sponsor Dashboard's event planner (plugin 1.117.0), dressed like the group sessions and the interests form.

= 1.27.1 =
* The Sponsor Dashboard's header carries the badge from the Your badge card at its right, dressed to sit beside the company's details (plugin 1.115.1).

= 1.27.0 =
* The Sponsor Dashboard's Reports card (plugin 1.115.0) lists a sponsor's annual reports with Open and PDF buttons, dressed like the dashboard's other lists.

= 1.26.0 =
* The Student Report Card's course section can carry a row of the companies partnering with a track (plugin 1.114.0); the theme dresses it as a row of logo tiles, there or in the section of its own the plugin draws on a track with neither a course link nor an hours box.

= 1.25.0 =
* The landing page gains the plugin's Sponsors strip between the steps and the call to action, and the theme dresses it: one row of logos, a line under it. The block prints nothing for visitors until the program turns the listing on, so the page looks as before until then.
* The sponsors page's listing, the Sponsor Dashboard's sponsorship card (plugin 1.112.0) and its badge card (plugin 1.113.0) are dressed by the rules the rest of the site follows.

= 1.24.31 =
* Education Hub homepage: the pathway section is set aside for now, with the hero's "Explore the pathway" button that led to it, while its details and badges are settled, as a program lead asked. The hero keeps one button, "Find where to start".
* The Companies door and the closing band's Companies card are set aside too, as the program lead suggested and the site owner agreed: both led to the WordPress jobs site, which the page leaves out for now. The doors are now the students' and the institutions', and the closing band holds the sponsors' and schools' card.
* Nothing is deleted: the pathway, its button and the two Companies cards come back with the rest of the planned content when it is turned back on (for developers: `add_filter( 'wpcredits_hub_show_planned', '__return_true' )`).
* For developers: `patterns/hub-pathway.php` prints nothing unless `wpcredits_hub_shows_planned()`; the hero's second button and the Companies column of `patterns/hub-cta.php` print under the same test; the Companies door in `patterns/hub-doors.php` is marked `parked` and left out the same way. `bin/test-hub.php` checks the page without them and, with the filter on, with them; `bin/test-hub-js.php` opens both pages.

= 1.24.30 =
* Education Hub homepage: the page now shows only what the programs offer today, as two program leads asked before it is published. Every benefit marked "(planned)" is no longer shown, nor are three lines of academic recognition that are not offered yet (under Builder, Community Leader and Credentialed), nor the WordPress Builder Certification card, which is still in development. Where a step or a partner level has nothing offered today under "Unlocks" or "Gets", that heading is left out and the step or level shows what earns it and where it leads. The program list counts five programs, and its filters count them the same way.
* The program cards no longer say which step of the pathway a program belongs to ("Step 1 · Attendee" and the like): a reader meets the steps only further down the page, as a program lead pointed out in the page's content review.
* Nothing is deleted: the planned benefits, the three lines and the card are kept in the theme and come back, each planned one marked "(planned)" as before, when the planned content is turned back on (for developers: `add_filter( 'wpcredits_hub_show_planned', '__return_true' )`).
* For developers: `inc/hub-content.php` gains `wpcredits_hub_shows_planned()` (the filter `wpcredits_hub_show_planned`, false by default), `wpcredits_hub_shown()`, `wpcredits_hub_programs_shown()` and `wpcredits_hub_line_html()`; each step's `recognition` is now an item (`wpcredits_hub_item()`), the three named lines planned; the programs lose their `step` key and the cards their `wpc-hub-program__step` line and rule. `bin/test-hub.php` checks the page as offered today and, with the filter on, the whole of it as before. The six step labels leave the translation template.

= 1.24.29 =
* Education Hub homepage, the hero: the picture beside the title is bigger. On a wide screen it fills the space from the line between the two columns to the right edge, and from the top of the text down to the bottom of the two buttons, about 1.15 times its old size, every piece in its old place. The text's column is a little narrower (47% of the row, it was 52%) and the picture's wider (49%, it was 44%), so the paragraph under the title may take one more line. The picture now stands on the text's foot: the mountain's foot is level with the bottom of the buttons, and the flag stays below the top of the text at every width. Below 900px the picture is still not shown.
* For developers: `patterns/hub-hero.php` sets the columns to 47% and 49% and the picture's column to bottom alignment; `assets/css/hub.css` gives the picture 98px of room above it (the flag's top) and 4px below it (the mountain's foot) in a rule three classes deep, `.wp-block-column.wpc-hub-hero__art > .wpc-hub-art`, because the flow layout's first-child and last-child rules would take a lone child's margins away, and two steps up, `zoom: 1.077` from a 560px column and `zoom: 1.154` from 600px, each no wider than the narrowest column it is drawn in, beside the five steps down. `bin/test-hub.php` pins the split, the alignment, the room (read from the drawing's own paths) and the steps. No string changes.

= 1.24.28 =
* Education Hub homepage, "Which WordPress Credits course?": the Translation track is the band's fifth course, after the Designer track. Its card reads "Translation track" (linked to its Learn WordPress page), "For translators" above the name, and "Translation skills and real work with the Polyglots team: translate.wordpress.org and GlotPress, locale glossaries, software strings, and Learn WordPress lessons." The program card's link above the band now reads "Compare the five courses". It was asked for in the hub's content review.
* The five cards are laid out so none sits alone on a row. From 900px wide, the two courses by length, Intro to Contribution and Foundations, share the first row, and the three tracks, the Developer track, the Designer track and the Translation track, share the second. From 640px to 899px there are two columns, and the last card, the Translation track, spans both. On a phone the cards are one column, as before. The space between cards is 16px, between rows and between columns, at every width, and the band's distance from the cards above (56px) and from what follows is unchanged.
* For developers: `inc/hub-content.php` gains the fifth `wpcredits_hub_course()` and the new link text; `assets/css/hub.css` gives the grid six columns from 900px (it had four) and adds three rules next to it, `.wpc-hub-courses .wpc-hub-course:nth-child( -n+2 )` and `:nth-child( n+3 )` from 900px and `:last-child:nth-child( odd )` from 640px to 899px. `bin/test-hub.php` pins the card, the link, the rules, and lays the sheet out from its own rules at nine widths to show that no row is left short. Three translatable strings are new ("Translation track", "For translators" and the description) and "Compare the four courses" is replaced by "Compare the five courses", so the translation template is regenerated.

= 1.24.27 =
* Administrator Dashboard on a phone: each table with column headers keeps its frame around the visible window when you scroll it sideways. Since plugin 1.122.35 the box around the table is what scrolls, but the table's 1px line was still drawn on the table, so its left and right edges slid out of view along with the columns. From 782px down the line is now on the box and the table inside has none, so Administrators see the same frame they saw before, standing still, with the columns moving inside it. On a wider screen nothing changes, and the other dashboards' tables are drawn as before.
* The box's keyboard focus ring (2px, in the brand blue, 2px outside the line) is the same as before and stays whole with the line on the box. No spacing changes.
* For developers: two rules in `assets/css/dashboard.css`, in the plugin's own `@media ( max-width: 782px )` query, next to the shared dashboard-table rule: `.wpc-dashboard-page .wpcpm-administrator__scroll` draws the line and `.wpc-dashboard-page .wpcpm-administrator__scroll .wpcpm-admin-table` has none. `bin/test-dashboard-table.php` pins both, the query, the rules' weight and the focus ring. No translatable string changed, so the translation template is as it was.

= 1.24.26 =
* Education Hub homepage: "Find where you fit in WordPress Credits" shows the way from a field of study to a WordPress team and on to real work, where it was a three-column table. Each field of study is one row, read left to right: the field, an arrow, each team it joins as a blue tag, an arrow, and what you'll have to show behind a green check mark. One line above the rows names the three steps: "Your field of study", "The team you join" and "What you'll have to show". Every field and every piece of work is worded as before; the teams are separate tags now, each named as the team is (Core, Plugins, Testing, Accessibility, and so on).
* On a phone, and on any window under 768px wide, each row stacks: the field, a down arrow, the teams, a down arrow and what you'll have to show, at the section's left edge. Nothing on the page scrolls sideways, at any width from 320px.
* A screen reader hears each row as one item of a list named by the section's heading: the field, then "The team you join:" and the teams, then "What you'll have to show:" and the work. The arrows, the check mark and the line of step names are decoration and are not read. In high-contrast (forced colors) mode the tags keep their border and the arrows their color.
* Translators: each team is its own string, with the context "a WordPress team a student joins", and the two labels a screen reader hears carry notes. The table's two other column headers and its comma-separated lists of teams are no longer in the translation template.
* For developers: the section is a Custom HTML block in the programs pattern (`patterns/hub-programs.php`), its rules under "Find where you fit" in `assets/css/hub.css`. The table's rules and `wpcredits_hub_fields_region()`, which made the table's scroll box reachable by keyboard, are gone with the table. `bin/test-hub.php` checks the rows in order, what a screen reader hears, the hidden labels, the silent decoration, the strings and the rules at each width.

= 1.24.25 =
* Site header: the site's name is twice the size it showed, 30px where it was 15px, on a 1.2 line. On a phone it is 30px from 375px wide and a little smaller under that, down to 24px, so the bar keeps its one row. The 15px came from the Site Title style in theme.json, which outranked the 20px and the phone's 17px the stylesheet asked for; the size is now said in theme.json, and the phone's at a rule that outranks it.
* For developers: `bin/test-header.php` checks theme.json's Site Title size, that no one-class rule of the stylesheet sizes the name, and the phone's two-class size.

= 1.24.24 =
* Site header: the brand is the site's name alone. The WordPress mark beside it is gone, since the WordPress.org bar above the header already carries it; the name still leads to the home page. The login screen's brand, the same lockup, loses the mark too.
* The readme's description says what the header now holds: the site title alone, and the navigation at the right edge.
* For developers: the theme no longer draws a mark when no site logo is set (`wpcredits_site_logo_fallback()` and its filter are gone) nor defines the mark (`wpcredits_get_logo()`); `bin/test-header.php` checks that the header part's brand holds the site title alone, that nothing draws or filters the mark, and that the login screen prints none.

= 1.24.23 =
* Site header: on the wide bar the navigation, the help button and the account picture, or a visitor's Log in, end at the right edge of the content below them, as the header was designed. The header template asked for them centered, and that won over the stylesheet, so they sat in the middle of the space beside the brand. On a phone the second row still starts under the brand.
* For developers: `bin/test-header.php` checks the header part's justification for that group and the phone's two-class rule that keeps its start.

= 1.24.22 =
* Education Hub: behind the hero's picture stands a mountain drawn in words, rows of the pathway's step names cut to two peaks that rise above the editor pieces. The higher peak stands over the chart, with a flag on its summit and its snow cap in the brand blue, and a trail with a dashed route zigzags between the pieces from the mountain's foot to the flag. Like the rest of the picture it is decoration, hidden from screen readers and not shown below 900px wide.
* For developers: `bin/test-hub.php` checks the mountain's rows (where each begins in the hub's list of steps, the order of the names, that each crosses the drawing), its two masks, the trail's ends at the foot and the summit, the flag's pole, its ids and its rules; the picture's focus check no longer counts the `href` of an SVG `use` element, and the words check leaves out the `//` between the mountain's names.

= 1.24.21 =
* Site header: below 1200px wide, the closed menu stays closed for a submenu that opens on hover and has no toggle, as it already did for one with a toggle. Nothing else a reader sees changes.
* For developers: `bin/test-header.php` counts each class the menu's entry count names on its own, reads the priority and the accepted arguments of the three header filters (the stand-in's `add_action` and `add_filter` keep them), checks a site name holding markup, weighs each submenu selector of the menu layout against the sheet's rules outside it and core's reveal on focus, names the navigation's links and container in the order check, judges the signed-out phone row inside its own block of the sheet, and pins the closed navigation container's declarations and the Menu label's translators note; `bin/test-header-js.php` has scenarios for a student's own page, for the observer's present-to-absent rule and for a full press (pointerdown, focus, click); `bin/check-selectors.php` shares one JavaScript string pattern between its two scans; `bin/stubs/node-suite.php` looks node up once; the three-item navigation is one function in `bin/stubs/header-markup.php`.

= 1.24.20 =
* Site header: below 1200px wide, phones and most iPads among them, held either way, a signed-in reader's header is one row: the brand, the account picture and a Menu button. The button opens a panel under the bar that lists, in the order of the wide row, the navigation, Need help? where the handbook assistant is available, the Student Report Card and the Mentor Report Card the reader can open, and Log out, which a phone did not show before; the page being viewed is marked, and each entry is a row at least 44px tall. The button, Escape, a tap or click outside, a link or Need help? closes the panel; Escape, and the assistant closing, put the focus back on the button. Opening it does not move the page. Without JavaScript the header keeps its wrapped row of links, and from 1200px the row is as before.
* Site header, signed out: on a phone, where the navigation's one link leads to the home page, the brand and Log in share one row and that link is left out, since the logo and the site title lead home; any other single link stays shown and the bar keeps its two rows. The Log in button on a phone is the smaller one its rule always meant, 14px with 9px and 14px of padding; with the one row the bar is about 62px tall from 334px wide, and 82px on the narrowest phones, where the site title takes a third line.
* Site header: where no logo is uploaded, the WordPress mark's link is named with the site's name for screen readers ("Home" on a site without a name), as an uploaded logo's link already is.
* For developers: `bin/test-header-js.php` runs the header's scripts on the stand-in page, and `bin/check-selectors.php` also checks that every plugin class the theme's scripts read is one the plugin sets.

= 1.24.19 =
* Education Hub homepage, the hero: the eyebrow sits 44px under the header, where it sat 114px under it: the hero's columns keep no space above them, and the eyebrow is a block of its own rather than the old pill's inline box. The blue stroke behind "Keep climbing." is wider and taller, so every letter, the K's top and the g's tail among them, sits on the blue, and it sits a little lower, clear of the line above.

= 1.24.18 =
* Education Hub homepage: the WordPress.org look. Headings and the outcome figures' numbers are set in EB Garamond, WordPress.org's serif, and everything else in Inter. Both ship with the theme (`assets/fonts`, each with its SIL Open Font License), so the page asks no one else for its fonts, and only the hub's pages load them. Cards are white on a 1px hairline with 2px corners; the filters, the pathway's switch, the status tags, the steps and the details are square-cornered too.
* Education Hub homepage, the hero: no tinted panel. The title reads "Start learning with WordPress." then "Every step counts." then "Keep climbing.", the last in white on a hand-drawn stroke of the brand blue. Beside the copy from 900px sits a picture made of WordPress editor pieces in the page's own words: a block toolbar, the Contributor step as a selected block, a rising chart, the Contribution badge, a type card and the inserter's plus. It is hidden from screen readers, holds nothing to focus, and is zoomed down with its column so it never cuts or covers its words; below 900px it is not shown. The title's second sentence becomes two strings to translate, "Every step counts." and "Keep climbing."
* Education Hub homepage, the outcome figures: they move out of the hero into a row of their own under it, between two hairlines, each number in the serif at 56px with its label and base under it and the source line across the row; on a phone, one figure to a row. With no stored figure the row is not shown.
* Education Hub homepage, the doors: the cards for students, institutions and companies each open with a light gray band holding a small editor piece in the card's own words ("Your first contribution", "Ready-to-run program", "Verified WordPress experience"), decoration only. The doors' and the program cards' links are underlined.
* Education Hub homepage, the programs, the courses and the pathway: program, course and step names in the serif; "Step 1" to "Step 7" in the serif in brand on white steps, the chosen step filled in brand; on a phone each step keeps the bar along its foot that widens with the climb; the chosen program filter is filled in ink. The line "Items marked planned are not offered yet." is removed; each planned item still says "(planned)" beside it.

= 1.24.17 =
* Education Hub homepage, the pathway: a step plainly opens what it unlocks. The line that says so moves out of the ladder's introduction into a tinted prompt right above the steps, "Select a step to see what it unlocks." (and "Select a level to see what it brings." for institutions); every step and level ends with "See what it unlocks" (or "See what it brings") and a down arrow, "Shown below" on the one whose detail is open; a step lifts under the keyboard's focus as under the pointer; and at 900px and wider the open detail points up at its step. Screen readers hear each step as before.
* Education Hub homepage, the Builder step: it unlocks showcase eligibility and a profile badge, both still marked planned; the free .blog or .art domain with a year of hosting and the full theme and plugin library come off: the domain and hosting are a WordPress.com perk and the page names no host, and the step now lists only what a Builder earns.
* Education Hub homepage, the program index: under the six cards, "Which WordPress Credits course?", a band of the program's four courses, Intro to Contribution, Foundations, the Developer track and the Designer track: a card each with its hours (or who it is for), its name linked to its Learn WordPress page, and one line on what it asks, four across from 900px, two from 640px, one on a phone, 56px under the cards as the field-of-study table is under it. Its lede says access to a course comes through the student's institution. Step 4's link, "See the courses", leads to it, and in forced colors the pressed step's number, line and cue take the system's highlight text. The WordPress Credits card links to the band, "Compare the four courses", and to the program page and the impact dashboard.

= 1.24.16 =
* Education Hub homepage: the hero sits on a soft brand-tint panel, and its three figures on a white card.
* Site header: signed in with the Student Report Card and Mentor Report Card links, or with the help button beside a short menu, the bar no longer scrolls the page sideways between 641px and 806px; it wraps onto two or three rows as it was designed to.

= 1.24.15 =
* Education Hub homepage: a new page template, "Education Hub home", with five patterns: a hero with the program's three outcome figures and their source, three doors for students, institutions and companies, every education program in one list with a field-of-study table, the pathway with the students' seven steps and the institutions' four partner levels, and a closing band. A page set to it shows the hub, and a page that is merely called education-hub does not; the homepage stays as it is until it is set to such a page under Settings > Reading, when the theme lets the template draw the front page. The hub's sections, cards and buttons keep their gaps over the editor's layout styles: 96px between sections, 16px between cards and 12px between a pair of buttons.
* Two of the three new blocks, Program index and Pathway: every program as a card with chips that filter them by audience, and a switch between the two ladders with a staircase of steps and each step's detail. Without JavaScript every card, both ladders and all eleven details are shown and no control is; with it each press says what it shows to a screen reader, and focus stays where it was. Each filter's announcement names its audience, "Showing 3 of 6 programs for students", "for faculty" or "in Career and credentials", so no two chips say the same sentence. At 900px and wider the steps rise as a staircase; below that, on a tablet or a phone, each step is a plain full-width row with a bar beneath it that widens with the climb, and pressing a step brings its detail into view when it is out of sight. An address changed to a card or a step that is hidden shows it and scrolls to it, and a link opened in a new tab or window (Ctrl, Cmd, Shift or Alt held) leaves the filter and the step as they are. Every benefit no public page confirms is marked "(planned)".
* Education Hub sign-in section: right under the hero, a card each for students, mentors, institutions and sponsors (the Student Report Card, the Mentor Report Card, the Institution Dashboard and the Sponsor Dashboard), drawn by the third new block, Sign in, placed in the template. Signed out, each card signs in and lands on its page; signed in, each opens its page. Each link is named by one whole sentence, such as "Sign in to your Student Report Card", so a list of links tells the four apart. A page that is not published leaves its card out, and with none the section is not shown.
* The program figure block shows the three outcome figures and their source line, read from WPCredits Program Manager 1.122.24 or later; with an older plugin, or none, it shows neither, and the hero's copy takes the whole width. An outcome figure is never typed in, so a fallback written into the block is ignored for it. A new label attribute replaces an outcome figure's own label; a text color or font size set on the block does not reach the figure's number, label or base. In the Site Editor each program figure block now shows what the server draws for its own stat, as the Program index and Pathway blocks do, instead of a placeholder. The four counts, mentors among them, are drawn as before.
* On a phone the field-of-study table scrolls inside its box, which is reached by keyboard, also after the table's words are edited in the Site Editor; nothing on the page scrolls sideways.
* For developers: `bin/check-selectors.php` also checks that every `.wpc-hub-*` and `.wpc-figure*` selector is printed by the theme. The installable zip built with `git archive` leaves out `bin/` (`.gitattributes`), which keeps the checks and the new suites, `bin/test-hub.php` and `bin/test-hub-js.php`; `bin/make-pot.sh` leaves `bin/` out of the translation template with WP-CLI too, as it already did with gettext alone.

= 1.24.14 =
* Dashboards: the "Viewing as" field that plugin 1.122.15 draws, one box you click to open the names, type into to narrow them and pick from, is dressed as the list was: white background, gray border, 2px corners, 14px type, 230px minimum width, and the WordPress chevron 8px in from its edge with room kept for it, one arrow and not two. It is 34px tall, as **Show** beside it is. **Show**, the labels, the note and the 8px gaps between them are as before. What is typed reads in the page's ink, and the prompt before anything is typed in the theme's muted gray.
* Dashboards: the open list is a white panel with the controls' gray border and 2px corners, the theme's soft shadow and 14px type, 2px under the field and as wide as it. Each name is a 32px row. The highlighted name, the one Enter picks, is on the pale indigo tint with a 2px blue edge on its left; the name under the mouse is light gray; the name being viewed is bold, so it is not told by color alone; and the line that says nothing matches is in the muted gray. In forced-colors mode the highlighted row takes the system's own highlight colors.
* This release pairs with plugin 1.122.15, which adds the field. With plugin 1.122.14 or older nothing on a page changes: the rules for the 1.24.12 search box and the 1.24.13 list stay as they were, and a page without JavaScript still shows the plain list.
* For developers: `bin/check-selectors.php` can name a class that the plugin no longer prints but the theme still dresses for an older plugin. It lists such a class as kept, with the last plugin version that printed it, instead of flagging it. The one entry is the 1.24.12 no-match line, last printed by plugin 1.122.14.

= 1.24.13 =
* Dashboards: the arrow on each "Viewing as" list sits 8px in from the list's edge, where the browser drew its own about 5px from it, in a way no padding could move. The theme draws the WordPress chevron in its place and keeps room for it, so a long name stops short of the arrow. The list is now 34px tall, as the search box above it and **Show** beside it are. **Show**, the labels and the gaps between them are as before.

= 1.24.12 =
* Dashboards: the search box above each "Viewing as" list matches the list under it, with the list's white background, gray border, 2px corners, 14px type and 230px minimum width, in place of the browser's own box. Its label reads as the list's label does. The box is on the Student Report Card, the Mentor Report Card, the Institution Dashboard and the Sponsor Dashboard, for Administrators only, and it pairs with plugin 1.122.14, which adds it; with an older plugin nothing on a page changes.
* Dashboards: with plugin 1.122.14 the box sits directly above the list, at the list's left edge and width, with each label beside its field (above it on a phone); the plugin sets that layout, and the theme dresses the box and its label as it dresses the list.
* Dashboards: the line that says nothing matches what was typed reads as the note beside the switcher does, 14px in the theme's gray.
* Mentor Report Card: from 1180px wide down, a student's name too long for its line wraps, at a space, and inside a word only when one word alone is wider than the line; before, it ran on over the institution beside it from 901px to 1180px, and past the card's edge on a phone. From 901px to 1180px a row whose name takes two lines keeps its height, which the avatar sets. Institution Dashboard: the roster's names, which already had room for one line above 900px, wrap the same way from 900px down, where they ran past the card's edge on a phone. On a phone a row can grow by the one line its name takes, about 20px. Wider screens are as before.

= 1.24.11 =
* Header: for a signed-in reader whose account chip has no link to the Student Report Card or the Mentor Report Card (a sponsor member's is only Log out), the menu, Need help? and Log out no longer pile into a column of two or three rows beside the site's name. With the site's mark and name together about 340px wide and a one-item menu, that was up to about 757px, where the header was about 129px tall at 641px and 83px tall at 700px. In that range they now sit on one line in a row under the name (about 105px tall with JavaScript on, spaced as any two-row header is), and from about 758px they share the name's row, 77px tall, as before.
* Header: an administrator's, a student's and a mentor's header is as in 1.24.10 at every width, whatever the menu. A visitor's is as before too while the menu and the Log in button fit beside the name, which a one-item menu does from 641px; with a longer menu, the menu and the Log in button sit on one line under the name where they do not fit.
* Landing page: the audiences pattern calls its fourth audience Administrators, and its line reads "Run the program: review applications, open accounts and promote vetted mentors."
* For developers: the stylesheet no longer carries a rule for the last-updated line inside the mentor card's identity block, which the plugin never prints there.

= 1.24.10 =
* Header: the Mentor Report Card's pinned group heading and the open menu sit right below the header however tall it grows, and anchored cards, items and guide headings land 28px below it, a student's row 24px more. On a phone the open menu spans the screen, not a strip under its button. With JavaScript off, the heading and the menu above 640px sit 76px down and anchors land 104px down, 208px on a phone, as in 1.24.9 (a student's row 24px more at every width, now 232px on a phone).
* Header: where the account chip does not fit whole beside the site's name, the header takes two rows, spaced as on a phone (with JavaScript on), and every link keeps its whole label. With the site's mark and name together about 340px wide, that is up to about 900px for a chip with links to both pages, as an administrator's has, and up to about 720px for a student's or a mentor's. A visitor's header is as before.
* Header: on phones up to about 343px wide, for a site name about 253px wide at the phone's 17px, the name breaks onto a second line instead of running into the right margin or, below about 323px, pushing the page sideways.
* Header: the account chip names each page it links to by its product name, Student Report Card or Mentor Report Card, for everyone, as the plugin's toolbar does (plugin 1.122.7 and later). A student read My Program, a mentor My Students and an administrator Student Dashboard and Mentor Dashboard. The login screen's link and the mentor call to action's button read Open your Mentor Report Card.
* Landing page: the audiences pattern's line for institutions says "Recognize", in US English.

= 1.24.9 =
* Header: signed in on a tablet-width window (601px to 782px), where WordPress makes the admin bar 46px tall instead of 32px, the sticky header now sits right under the bar. Its top 14px used to be behind the bar, because the theme left room for a 32px bar.
* Navigation and Mentor Report Card: on that window the open menu (from 641px) and the group heading pinned below the header move down with the header by the same 14px, so each keeps the place it had against the header. All three now read the bar's height from the variable WordPress declares for it, with 32px as the fallback. Nothing moves above 782px, or at 600px and below, where the bar scrolls away.

= 1.24.8 =
* Dashboards: a card or an item opened by the anchor in its address now lands below the sticky header instead of under it. The wp-admin screens' links to an item on the Administrator Dashboard (plugin 1.121.0 and later) and a decision's way back to its card use such anchors.
* Guides: an anchored heading keeps clear of the header on a phone too, where the bar is two or more rows tall.

= 1.24.7 =
* Tools from our sponsors: the button beside the More information link, and Report a problem beside each code under Your codes (plugin 1.116.4).

= 1.24.6 =
* Mentor Report Card: the planning form's Sessions box, which the plugin keeps disabled until a repeat rule is chosen (plugin 1.109.1), is dressed like a manager's read-only report field, so it reads as waiting rather than broken.

= 1.24.5 =
* Mentor Report Card: the planning form's Repeat select (plugin 1.109.0) is dressed like the form's other controls, by the same rule.

= 1.24.4 =
* Mentor Report Card: the "More dates" legend of a series (plugin 1.108.0) is dressed like the planning form's labels, by the same rule, instead of inheriting the body's size and color.

= 1.24.3 =
* Every dashboard table is dressed once, by one rule, in the Institution Dashboard's values: the Student Report Card's detail tables, the Institution Dashboard's roster and student tables, the Sponsor Dashboard's Usage card and the Administrator Dashboard's queues. A row header was three different weights across the three pages, with three paddings, two rule colors and a 13px uppercase column header only one of them drew.
* Sponsor Dashboard: the claimed code's button reset is said in the theme's own tokens, beside the rest of its dressing, now that the plugin prints the code as a button.
* That one rule carries the dashboard body class, like the rest of the sheet, so it dresses a table on a dashboard rather than every table the theme draws; the two column-header selectors for tables that have no column headers are gone. `bin/check-selectors.php` refuses a shared table rule written without the body class.

= 1.24.2 =
* Student Report Card: the Designer Track's chip (plugin 1.98.2) says its rose as a token rather than leaving it to the plugin's own sheet.
* Student Report Card: the screenshot questions of the Designer Track's practical lessons - the thumbnail in the card line, the note and the swap sentence in the hint's voice, and the file picker and Remove on one row at the form's control size, the file button dressed as the Sponsor Dashboard's is.
* Student Report Card: the report form's choice lists take the same control treatment as its text boxes, the way the feedback form's have since 1.21.0.

= 1.24.1 =
* Administrator Dashboard: a table row's own header (the offer's name in Offers running low) reads as a cell, not as a column label.

= 1.24.0 =
* Administrator Dashboard: the sponsor cards of the Sponsors module's phase S6 (plugin 1.98.0): the folded application on the Sponsor applications card, the Offers running low table, the New interests items and the Sponsors card's tiles, all on the page's existing rules.
* Administrator Dashboard: inside the folded application, the logo figures take their wp-admin look (the white half on the ink ground) and the block's own headings the item's size.

= 1.23.23 =
* Sponsor Dashboard: the open offer's state block is `wpcpm-offer__moves` (plugin 1.97.5); the chip keeps `wpcpm-offer__state`.

= 1.23.22 =
* Sponsor Dashboard: an open offer's details list, its Edit this offer disclosure and its state block (plugin 1.97.4).

= 1.23.21 =
* Sponsor Dashboard: inside an open offer, one row of buttons and the codes forms under their own line (plugin 1.97.2).

= 1.23.20 =
* Sponsor Dashboard: the Offers and codes card in two columns (plugin 1.97.1), the new-offer form on the left and the offers folded on the right as panels like the mentor page's availability panel.

= 1.23.19 =
* Sponsor Dashboard: the Usage card's chart (plugin 1.96.8) dressed in the theme's tokens, and the note, chart, table and CSV button 16px apart. Institution Dashboard and Sponsor Dashboard: the first block of every open card starts 30px under the card's line.

= 1.23.18 =
* Sponsor Dashboard: a note between two fields of a form lets go of the field above (24px) and holds the rows it introduces (8px).

= 1.23.17 =
* Sponsor Dashboard: 16px between the logo note and the tiles.

= 1.23.16 =
* Sponsor Dashboard: the profile card's two columns, details left and logo right with the resources split's divider and air; one column under 900px (plugin 1.96.7).

= 1.23.15 =
* Sponsor Dashboard: the logo's eyebrow inside the profile card, 24px over it (plugin 1.96.6).

= 1.23.14 =
* Institution Dashboard: the cards inside a module carry no bottom margin, so the gap before the Collaboration Agreement is the same 10px as between the roster's groups.

= 1.23.13 =
* Institution Dashboard: 10px between the cards of a module (the roster's own gap), 22px between modules (the page's own bottom room), and the read line at the foot under its hairline (plugin 1.96.5).

= 1.23.12 =
* Institution Dashboard: the line sits above each module's title and nowhere inside a module; the Collaboration Agreement card is a plain disclosure like the representatives'.

= 1.23.11 =
* Institution Dashboard: the arrows sit on each module's title line (20px over every title, as on the Student Report Card).

= 1.23.10 =
* Institution Dashboard: the three movable modules wear the Sponsor Dashboard's group shapes (eyebrow title, lead, one line between modules), the arrows are the dashboards' rule now, and the "Invite a colleague" heading gets its gap (plugin 1.96.4).

= 1.23.9 =
* Institution Dashboard and Sponsor Dashboard: 22px of room under the new hairline, the space the lifted switcher used to bring.

= 1.23.8 =
* Institution Dashboard and Sponsor Dashboard: a hairline under the title, above the dashboard, as the two Report Cards draw.

= 1.23.7 =
* Mentor Report Card: a hairline above the mentor's photo, as the Student Report Card draws one above the profile.

= 1.23.6 =
* Student Report Card: the mentor's details table keeps its own height; only its top edge meets the student table's.

= 1.23.5 =
* Dashboards: the administrators' "Viewing as" switcher is lifted out of the dashboard card and set above it, at the card's width.

= 1.23.4 =
* Student Report Card: the profile and mentor columns share their rows, so the two details tables start level whatever the mentor's card holds; under 900px the two sections stack again instead of standing side by side, squeezed.

= 1.23.3 =
* Mentor Report Card: the hairline above the "Currently mentoring" row stays, and the search centers on the ordering sentence under the heading (plugin 1.96.3).

= 1.23.2 =
* Mentor Report Card: the student search in the "Currently mentoring" heading row, on the right, at the heading's inset; the row wraps when narrow (plugin 1.96.2).

= 1.23.1 =
* Administrator Dashboard: the Sponsor Collaboration Agreements card's decision rows and its "Out of force" subheading (plugin 1.96.1).

= 1.23.0 =
* Sponsor Dashboard: the logo card (plugin 1.96.0). Two labelled preview tiles, the white logo on a dark ground so it is visible at all, and the two file fields in the controls the sponsor forms already use.
* Sponsor Dashboard: the agreement card. The state as the pill an offer's state wears, the program's note quoted with the brand rule down its edge, and the document's history as rows under hairlines.

= 1.22.15 =
* Mentor Report Card: whichever section follows the header band drops its own hairline, now that the program updates and resources lead the page (plugin 1.95.14).

= 1.22.14 =
* Student Report Card: the module arrows sit inside the content edge, a pair on the heading line ending where the text ends, so the page's right margin stays one line.

= 1.22.13 =
* Student Report Card: the room the first heading leaves for the module arrows under 900px now applies (the mover form is the module's first child for a manager).

= 1.22.12 =
* Student Report Card: under 900px the module arrows sit in a row on the heading line, inside the content edge, and the first heading leaves them room.

= 1.22.11 =
* Student Report Card: the four movable modules under the profile and mentor columns are flat wrappers here, the mover sits in the gutter at the module's top right with the theme's control border and focus ring, and the hairline between blocks stays on a phone (plugin 1.95.11).

= 1.22.10 =
* Sponsor Dashboard: the group lead under each heading, at the cards' inset, 16px over the first card (plugin 1.95.10).

= 1.22.9 =
* Sponsor Dashboard: each group carries the line, its heading sits inside it in the eyebrow style of the Resources and Updates headings, and the first card has no line of its own (plugin 1.95.9).

= 1.22.8 =
* Sponsor Dashboard: no hairline between two cards of the same group; the one line a group shows is under its heading.

= 1.22.7 =
* Sponsor Dashboard: headings over the three groups of cards (plugin 1.95.8), at the cards' inset, 40px above and 12px over the group's first card.

= 1.22.6 =
* Administrator Dashboard: the sponsor posts card's decision row (plugin 1.95.5) - Preview, Publish and the folded Return with a note on one line, the note form under them when opened.

= 1.22.5 =
* Sponsor Dashboard, Posts card: the list is the Updates list (plugin 1.95.4), so the rules that dressed the card's own rows, pills and decision forms are gone; the actions row and the returned note stay.

= 1.22.4 =
* The dark footer sits at the foot of the window on every page: the page column is at least the window tall and the main grows into the room. The wordpress.org header and footer wrappers step aside with display: contents, so the 20px stripe above the footer goes.

= 1.22.3 =
* Sponsor Dashboard, Posts card: 12px between the lead and the actions row.

= 1.22.2 =
* Sponsor Dashboard, Posts card: the actions as one row, the post head as one row, the decision as one row of three buttons with the return form folded under a disclosure and the hint below (plugin 1.95.3).

= 1.22.1 =
* Sponsor Dashboard, Posts card: the link to wp-admin's Posts screen beside the Write a post button (plugin 1.95.2).

= 1.22.0 =
* Sponsor Dashboard: the Posts card (plugin 1.95.0): posts as rows under hairlines with their state pills, Write a post as the shared button, a manager's Publish and Return under a pending post with the theme's controls.
* Student Report Card and Mentor Report Card: a sponsor's published guides listed under its offer in Tools from our sponsors.
* Posts: a sponsor post opens with a byline box naming the company, in the article card's values.

= 1.21.0 =
* One button on every dashboard: 600 14px/20px, 6px 14px padding, 8px radius (the Sponsor Dashboard's buttons were 13px); the Student Report Card's two large buttons keep their size.
* Mentor Report Card: the header band meets the first section with one line; the past-students box lines up with the student cards (20px inset) and leaves 20px below; its notice is a plain note; the ordering note has no rule above it.
* Administrator Dashboard: table rows centered on their buttons; 12px between a request's note field and its buttons; 18px between request cards; the decision buttons take the shared button size instead of their own, larger one; the line between requests takes the theme's soft line color.
* Institution Dashboard: student rows laid out like the Mentor Report Card's, one line with the portrait, the facts in the middle and the badge at the right; that one line now holds down to 900px, then takes the Mentor Report Card's two-line treatment below it instead of a cramped line with a dropped badge. The People card's invite form, Resend, Cancel, Remove and the flash messages are dressed now too, rather than left in the browser's own defaults.
* Student Report Card: the run headings are one class; the feedback form's selects take the theme's control treatment.
* The plugin's Mentor Report Card triage stylesheet is a declared dependency of the theme's dashboard sheet, so it loads on every dashboard page and the theme's rules always print after it.

= 1.20.0 =
* The type and color review of 5 September 2026, phases two and three. The type scale is complete across the last small rules: nothing on the page is smaller than 12px; chips, counts and eyebrows are 12px or 13px; every control, label, table cell, note and meta line is 14px; intros and empty states are 16px. The calendar's day numbers, the search field, the notes box, the availability editor, the calls list, the triage labels and the mentee table's stacked row labels all take these sizes now.
* The inks move onto the WordPress.org charcoal scale - ink-70 #40464d, ink-60 #656a71, and ink-50 #979aa1, reserved for icons - and the tints move onto the blueberry scale: brand-tint #eff2ff, brand-border #c7d1ff, and brand-dark #213fd4. The control border stays #8d8d8d. The border tint decorates panels and accents only: outlined buttons and the active filter take the brand color as their border, because the tint is too light to bound a control.
* Every checkbox and radio label is a 24px target with a 16px glyph inside it, the team tiles and the rating stars excepted, both of which size their own. The report's consent row, whose box sits beside its label rather than inside it, stands 24px too.
* The Student Report Card's read-only rows and the "Required" mark are styled here too, and the card's last hairline rule is gone. With plugin 1.94.6, which styles the same rows and mark in its own stylesheets.

= 1.19.5 =
* Student Report Card, My profile and My mentor: the same type fixes as the rest of phase one. The mentor's job line and location, the profile and contact rows and their links are 14px; the Teams eyebrow is 12px in the label gray instead of 10px of light gray capitals, and the team names take the row text size.

= 1.19.4 =
* The Sponsor Dashboard's mentor tiles follow the plugin's new block name (`wpcpm-mentor-tile`, plugin 1.94.5), so the Student Report Card's "My mentor" card is dressed by its own rules alone again.

= 1.19.3 =
* The Sponsor Dashboard's form controls and the Administrator Dashboard's note fields take the control border (3:1 against the page) instead of the card hairline, the boundary WCAG 1.4.11 asks for. With plugin 1.94.4, which does the same for the Institution Dashboard's forms.

= 1.19.2 =
* The type review of 5 September 2026, phase one. Two undefined tokens fixed: every input on the Student Report Card and the call forms had a 1.27:1 border because `--wpc-line-control` never existed (the token is `--wpc-control-border`), and the feedback rating stars had no focus ring because `--wpc-blue` never existed. Reading text is never smaller than 14px and never in the lightest gray any more: hints, notes, the switcher note, the updated line and the calendar's day numbers move to 14px in `ink-60` or `ink-70`, the page intro to 16px. Section names stop being the smallest text on the page: card and section titles 14px, group legends and lesson sub-headings 13px, count pills 12px. The Sponsor Dashboard's number, date and file fields take the same border, radius and font as the text fields, the file button is dressed as the secondary button, and the Kind and Also open to legends take the label treatment. A new heading style for the sentence over a run of marks on the Student Report Card (plugin 1.94.3). `bin/check-selectors.php` now also fails on a `var( --wpc-* )` the theme never declares.

= 1.19.1 =
* The Sponsor Dashboard's two mentor lists are mentor cards in a grid, the shape the Credits Program Mentors plugin gives the program's mentors, in the theme's tokens with the same lift on hover. The page's own blocks on a dashboard page (a welcome text above the Administrator Dashboard) take the dashboards' 32px inset, which the theme had zeroed along with the plugin root's. The note fields on the Administrator Dashboard and the sponsor forms are border-box, so a 100% width no longer runs past the column. Every inset narrows at one breakpoint, 900px; the shared sections and the count line narrowed at 782px before. The Student Report Card's team tiles (plugin 1.94.1) take the card line, the control radius and, once chosen, the brand tint with the icon in brand blue; every checkbox in the report is brand blue when ticked.

= 1.19.0 =
* The "Tools from our sponsors" section plugin 1.94.0 adds to the Student Report Card, the Mentor Report Card and the Administrator Dashboard, and the Offers and Usage cards on the Sponsor Dashboard, take the theme's tokens: the offer cards, the logo box, the monospaced code a click selects, the state pills, the usage table, the muted count line a manager sees on somebody else's card.

= 1.18.2 =
* The Sponsor Dashboard's spacing is the Institution Dashboard's: the switcher keeps the same gap beneath it, the message under it takes the banner's inset, the page carries the same inset at its foot, and the theme no longer sizes the program-contact block, the sub-headings or the list rows, which the plugin now draws with the institution's rules.

= 1.18.1 =
* The Sponsor Dashboard's identity block: the theme no longer sizes the name, the website and the facts beside the logo. Plugin 1.93.1 gives them the Institution Dashboard's rules, and the theme sizing neither is what makes the two headers read as the same header.
* The hairline under the identity header, which the Institution Dashboard has carried since the page moved onto the dashboard template, now runs under the Sponsor Dashboard's header too: on both pages the first section sits under a header rather than at the top of the card, so it wants the rule the student page's first section does without.

= 1.18.0 =
* The Sponsor Dashboard, the page plugin 1.93.0 gives sponsors, has its template, its page predicate, its body class and its skin: the identity block with the logo on a white ground, the program-contact block, the profile and interests forms, the mentor lists and the message tones. As with the other dashboards, the predicate and the body class were added together.

= 1.17.0 =
* The Administrator Dashboard, the page plugin 1.92.0 gives program managers, has its template, its page predicate, its body class and its skin: the attention strip, the programs tiles, the tables, and the theme's secondary button on the decisions the wp-admin queue draws with core's button classes. As with the Institution Dashboard, the predicate and the body class were added together, because a page in one and not the other renders as an article with no skin.

= 1.16.8 =
* Nine rules in the dashboard stylesheet and one in the print stylesheet dressed classes the plugin no longer prints, left behind when the triage and the student edit form moved into the plugin. They are gone, and `php bin/check-selectors.php` now greps both trees for every class the theme dresses, so the next one is flagged the day it goes.
* Every em and en dash in the theme is a plain hyphen, and the same script fails on a new one.

= 1.16.7 =
* An open group on the Institution Dashboard shows a chevron pointing down, as an open student card does. The plugin rotates its own chevron when a group opens, and that rotation was landing on this theme's icon, which drew a corner instead; the student cards had their own rule and the groups did not. With every panel on that page now a disclosure, the open state is the one a school sees first.

= 1.16.6 =
* "Current" and "Waiting for a mentor" join the same left edge as everything else on the Institution Dashboard. An open group draws its name as a heading rather than as a clickable row, and the heading carries its 32px inset in padding where the row carries it in margin. 1.16.5 zeroed the row and left the heading, so the two open groups stood alone while the collapsed rows beneath them had moved.

= 1.16.5 =
* Every block on the Institution Dashboard starts at one left edge. The shared group summary carries a 32px inset, which is right on the Mentor Report Card where the groups sit straight in the card, and one inset too many on the institution page where they sit inside a section that already has one. The result was that the heading, the filter bar, the cohort strip, the student cards, the people rows and the agreement panel all lined up at 32px and the rows a reader clicks stood alone at 64.

= 1.16.4 =
* The search box on the Mentor Report Card no longer prints its magnifying glass on top of the placeholder's first letter. The plugin lays that control out as an overlay, with the glyph and the clear button positioned absolutely into a padded gutter; this theme lays it out as a flex row and had reset the padding without standing the positioning down. The clear button had the same fault on the other side, unseen until somebody typed.

= 1.16.3 =
* The city and country line under an institution's name is back in line with the rest of the header. The identity header reuses the shared `wpcpm-dashboard__intro` class for that line, and 1.16.1's inset rule matched it by class, so the line was inset twice and sat 32px right of the name above it. The rule now matches only a direct child of the page root.

= 1.16.2 =
* The foot of the Institution Dashboard clears the card. The page ends on the bordered agreement panel, whose border was landing 1px from the card's own while the title at the top sits 22px inside it. The page now carries the same 22px at the bottom.

= 1.16.1 =
* The Institution Dashboard's own blocks are inset from the card again. The dashboard shell carries no global padding, so when 1.16.0 moved the page onto that shell the identity header, the roster, the People card and the agreement panels came to rest against the card's border while the heading and the resources section stayed inset. They now carry the shell's 32px, narrowing to 20px below 900px like every other block in it.

= 1.16.0 =
* The Institution Dashboard now uses the dashboard template the Student Dashboard and Mentor Report Card use. It had no template of its own, so it fell back to the article layout: a 34px title with 24px under it inside a 40px card, where its two siblings use a 22px title in the dashboard shell. The heading and the space around it now match the other two pages.

= 1.15.2 =
* A collapsed group of students reads as something you can press: a bordered row that lifts on hover and joins the panel it opens. The skin had been undoing most of that, leaving a box with a background, a radius and no top edge.
* Two collapsed groups in a row no longer touch.
* The people on an institution card line up with everything else on the page: a core list rule was indenting them.

= 1.15.1 =
* Two spacing fixes on the Institution Dashboard, both the ones the student page already needed: the program manager notice has room under it rather than the institution name sitting against its edge, and the first section carries the same hairline rule as every section below it.

= 1.15.0 =
* **The Institution Dashboard gets the dashboard skin.** The theme treated only the Mentor and Student Report Cards as dashboard pages, so the card, the measure and the type that make those two look the way they do never loaded on the institution page, whatever its own stylesheet said. It is a dashboard page now, and gets the same shell as the other two.

= 1.14.4 =
* Hides the collapsed menu's close button along with the panel it belongs to, which was leaving an X on top of the lone menu item.

= 1.14.3 =
* Puts the lone menu item back in the header rather than across the top of the page. Core positions the collapsed menu over the whole viewport, so showing it without returning it to the normal flow painted the link under the wordpress.org header.

= 1.14.2 =
* **A menu holding one item no longer hides behind a hamburger.** On a phone the header collapsed its navigation into a toggle whatever was in it, so tapping the hamburger revealed a single link. Reported by the mentor coordinator on rechecking the phone layout. A lone item now sits inline beside the logo; add a second and the toggle comes back on its own.

= 1.14.1 =
* Translation template regenerated: 96 strings, against 1.14.0. It had not been rebuilt since 5 August.

= 1.14.0 =
* **The header fits a phone.** The brand, the menu and the viewer's own links were one row that could not shrink, so on a narrow screen everything to the right of the site name was pushed off the edge: a mentor reported the Log in button and "My Students" cut off at the side and the menu looking as though it were missing items. They were on screen, just past it. The brand now takes one row and the rest takes another, wrapping again if it has to, and the dropdown hangs off the header itself rather than a fixed distance down the page.

= 1.13.0 =
* Hands the Mentor Report Card's triage, counts and search to the plugin, which renders the list they regroup. A theme should not be the reason a feature exists. Requires WPCredits Program Manager 1.64.0; this theme now only dresses the result.

= 1.12.0 =
* Removes the theme's own footer. The wordpress.org global footer is the only footer now, so the site ends the way every other WordPress.org property does.
* Drops the footer template part, its pattern and its styles with it.

= 1.11.1 =
* Match wordpress.org's own markup: the global header and footer template parts now carry `has-display-contents`, so their wrapper drops out of the layout instead of taking a flow gap.

= 1.11.0 =
* Adds the official wordpress.org global header and footer, above and below the site own chrome -
  the arrangement wordpress.org properties use, with the global bar outermost and the site own
  navigation inside it. They come from the WordPress.org Global Header and Footer plugin, which
  vendors the blocks from WordPress/wporg-mu-plugins.

= 1.10.2 =
* The Documentation sidebar card ends under its search instead of matching the article height.
  Stretched, it left thousands of pixels of empty white below the search with nothing to put there.

= 1.10.1 =
* The Documentation sidebar search sits directly under the contents list rather than at the foot of
  the card. The card is as tall as the article beside it, so the foot of it is thousands of pixels
  below the list - off screen on load, and reachable only by scrolling to the end of the guide.

= 1.10.0 =
* **The landing page mentor figure is live.** It was typed in as 92; the site had 88, and nothing
  would ever have said so. A new **Program figure** block counts the accounts holding a program role
  - the same number the Mentors screen reports - and the sync keeps that in step with the mentors
  Airtable lists as active, so the page never asks Airtable anything. Cached for an hour, since it
  only moves when a sync runs.
* The block takes a fallback, used when the plugin is inactive or the count is zero: a landing page
  opening with 0 mentors is worse than one showing the last figure somebody checked.

= 1.9.6 =
* A search box at the foot of the Documentation sidebar, and the sidebar card is now the same height
  as the article beside it - so the page reads as two panels rather than a short box next to a long
  one, and the search sits at the bottom of the column rather than adrift under the contents list.

= 1.9.5 =
* The gutter between the two cards on the Documentation template is the space the band leaves above
  them - one measurement in one place rather than two that happened to look similar, and both halve
  on a phone where the band does.

= 1.9.4 =
* The Documentation template now wears `wpc-main--content`, the same class a regular page uses, so
  its background band, the space above and below it, and the way it meets the header and footer are
  the page treatment itself rather than an approximation of it. It had none of that: the cards sat
  on the body background with the wrong gaps around them.

= 1.9.3 =
* The Documentation template is two cards side by side: the page in the left one, dressed exactly as
  every other page on the site, and the contents list in a card of its own on the right. The sidebar
  is a percentage of the window rather than a fixed width, so it grows with it.
* The contents list loses its rule and its indent - the card around it is the edge now.

= 1.9.2 =
* The Documentation template lines its article up with the site logo. The header is a centred
  1,240px container, so its left edge moves with the window - the page now calculates the same
  gutter instead of guessing a fixed inset, and falls back to 40px on windows narrower than that.
  The right side stays free: nothing centred, nothing capped.

= 1.9.1 =
* The Documentation template is full width, with the contents list down the right.
* The contents list never scrolls on its own and is never cut off - the program manager guide has
  fifty-five headings, and every one of them is reachable in the page own scroll. It does not stick:
  a sticky list taller than the window is a list whose last entries cannot be reached at all.
* Narrow layouts follow core own 781px stacking width rather than a made-up one. Core ships
  `flex-wrap: nowrap !important` above that width, so a theme cannot stack a columns block earlier
  however the rule is written.

= 1.9.0 =
* **A Documentation page template**, for the three program guides. Slightly wider than a normal page
  to make room for a sidebar, with a contents list in it and the article beside it capped at a
  readable measure - the extra width buys the sidebar, not longer lines.
* The contents list is a theme block, **Table of contents**. WordPress has one, but it is provided by
  the Gutenberg plugin rather than by core, and it returns nothing at all outside `the_content` - so
  it cannot be used in a template's sidebar, which is where a contents list belongs.
* The list sticks beside the article and scrolls on its own when it outgrows the window; below 900px
  it becomes a block at the top of the page instead.

= 1.8.25 =
* Dresses the feedback scales as stars for plugin 1.59.0 - amber when chosen or hovered, ink when
  reading somebody else's answers, and the focus ring on the star since the radio it belongs to is
  not drawn. Drops the boxed treatment the scales had, including a white-on-chosen rule that would
  have painted the chosen star white.

= 1.8.24 =
* Removes the design preview added in 1.8.23. It was a trial layer behind a query flag and is gone
  in full - the stylesheet and the three functions that loaded it. Nothing else changes: it never
  altered the page for anyone who had not asked for it.

= 1.8.23 =
* Adds an opt-in preview of proposed Student Report Card design changes at `?design=preview`, for
  program managers only. It is a trial layer: one stylesheet scoped to a body class, no markup
  changes, and nothing renders differently for anyone who has not asked for it. Delete
  `assets/css/dashboard-preview.css` and the three functions at the foot of `inc/dashboard.php` to
  remove it entirely.

= 1.8.22 =
* Draws the same rule under each feedback survey as the one above the set of them.

= 1.8.21 =
* Moves the rule that separates the feedback surveys from the report form onto the surveys'
  heading, so everything about them - including their name - is on their side of the line.

= 1.8.20 =
* A survey question now takes the card's body size and ink rather than the 12px muted treatment the
  report form uses for its field labels. A question is read; a field label is scanned.

= 1.8.19 =
* Dresses the feedback surveys the plugin adds in 1.58.0: the 1-to-5 scales, the consent line, and
  the permissions box that has to read as separate from the questions above it. The forms
  themselves reuse the report form's treatment, since to a student they are the same kind of thing.
* The chosen step on a scale is filled rather than ringed - on a row of five, a border alone is
  hard to pick out, which is the one thing a scale has to make obvious. Where `:has()` is
  unsupported the radio stays visible instead, so the answer is never invisible.

= 1.8.18 =
* **Fixes the version stamped on every stylesheet and script the theme loads.** It was written out
  by hand and had sat at 1.7.1 through ten releases, so browsers and edge caches kept serving the
  CSS and JavaScript they already had - a fix would ship and the page would carry on looking the
  way it did months ago. It is read from the stylesheet header now and cannot drift again.

= 1.8.17 =
* Places the report form across the full width of a student's card, under the details table and the
  notes beside it. As the third child of a two-column grid it was auto-placed into the notes column,
  where a form of twenty-odd fields had half a card to fit in.
* Drops the rules for the Airtable button that used to sit in the card; the plugin stopped emitting
  it in 1.56.0.

= 1.8.16 =
* Drops the styling for the link that stood in for the report disclosure. It is a real disclosure
  since plugin 1.56.1, so the summary takes the treatment every other one on the card has.

= 1.8.15 =
* Dresses the session planner as the availability panel's twin - same box, same inset - so the
  right-hand column reads as two controls rather than as a panel with a loose paragraph under it.
* The closed report form on a mentee's card takes the disclosure's own colour and type, so a link
  standing in for a `<summary>` is not a different kind of thing to look at.

= 1.8.14 =
* **The report-form link stays in the card.** The script used to hoist it into the student's row,
  which was right while it opened the Airtable form - one link, reachable without opening the card.
  Since plugin 1.55.0 it opens that student's report *on this page*, at the foot of their own card,
  so a control in the row that scrolls you into the card below it is a worse version of the
  disclosure triangle already there. The row keeps its "Add note" action for a student who needs a
  call.
* Dresses the mentor section's new left-hand box: it carries the inset, so the diary and the group
  sessions under it keep the same margin.

= 1.8.13 =
* **"No availability set" takes the same red as "Need a call".** Both say the same kind of thing -
  something is not set and somebody is waiting on it - and this one had a pale red of its own.
* Gives group sessions the space the grid used to provide when they spanned the row, now that they
  follow the diary in the same column.

= 1.8.12 =
* The availability panel takes a call card's inset - same 12px/14px - so the schedule and the
  booked calls beside it are two of the same box. Its body keeps only the rule and the space over
  it, since the panel now carries the padding.

= 1.8.11 =
* **"Save my report" takes the same treatment as the card's other actions** - the size and radius
  the course, report-form and Need help? buttons use. It is the one thing a student presses on that
  form, and it was the size of a table control.
* Follows the plugin's availability disclosure, which is now the same control group sessions uses:
  the toggle takes the sessions treatment, and the rules that dressed the old bar - its title span
  and its borrowed chevron - are gone with the markup they styled.

= 1.8.10 =
* Dresses the report form's two new section marks in the card's tokens: the headless divider takes
  the same soft rule as a headed one, and the note under a run of fields takes the muted ink every
  other hint on the card uses.

= 1.8.9 =
* Dresses the report form's lesson headings in the card's own tokens - the soft rule and the muted
  ink - one step below the group legend, so a group, a lesson and a field are three distinct
  levels rather than three things shouting at the same volume.

= 1.8.8 =
* The Save button beside the hours box takes the same type and padding as the controls, so the box
  and the button read as one row rather than as a field with something taller attached to it.

= 1.8.7 =
* **The hours box takes the report form's controls** - same border, radius, type and padding - so
  the field outside the form matches the ones inside it.
* A team's name in the contribution list is an answer rather than a field label, so it keeps the
  card's body type instead of the bold, muted, 12px treatment every question above it takes.

= 1.8.6 =
* Follows the hours box, which draws its own label rather than one of the form's field rows, so the
  label keeps the muted treatment every other label on the card has.

= 1.8.5 =
* **Dresses My course's two columns** - the button that opens the course beside the hours box the
  plugin moved there - and gives the hours label the same muted treatment every other field label
  on the card has.

= 1.8.4 =
* **Dresses the report form's grouped fieldsets.** The plugin's own border and radius are stood down
  for the design's card line, and each group's legend takes the same uppercase treatment as every
  other heading inside a card - so a group reads as a section rather than as a browser default.

= 1.8.3 =
* **Dresses the student's new report form.** Its inputs and textareas take the same controls as the
  availability editor and the session forms, checkboxes are left as checkboxes rather than being
  given a text box's border, and a form a program manager is reading rather than filling in reads as
  information instead of as something broken.

= 1.8.2 =
* **Dresses the plugin's new group sessions.** Each session is a bordered block with the design's
  own card line and radius rather than the plugin's theme-agnostic default, its counts and hints use
  the real grays instead of `opacity`, and the create and note forms take the same controls as the
  availability editor - so the two forms on a mentor's card do not look like they came from
  different plugins.

= 1.8.1 =
* **Dropped "Printing opens every student first." from the ordering line** under the student list.
  The behaviour is unchanged - printing still opens every student, so a printed list is never
  half-empty - but it is the plugin's doing and a mentor reading a list does not need telling.
  The line now reads "Ordered by internship end date within each group, soonest first."

= 1.8.0 =
* **The hero ships with its photo.** Where the pattern had a dashed box saying what picture
  belonged there, it now has the picture: a student taking notes through a video call with their
  mentor, bundled at `assets/images/hero-mentoring.jpg`. A landing page whose main image is an
  empty outline reads as broken, and every install had to do the same first job before the page
  could be shown to anyone. Swap the Image block for your own whenever you like.
* The photo is 1240x745 and 161KB - a JPEG rather than the 478KB PNG it came from, which is the
  right format for a photograph - and its column holds that ratio with a tint behind it, so the
  page does not jump as the image arrives.
* `screenshot.png` is re-captured from the pattern itself, so what Appearance shows is now what
  a fresh install actually looks like.

= 1.7.21 =
* **A current `screenshot.png`.** The old one was captured before the site had a photo, so
  Appearance showed the hero as an empty dashed box - and it still carried the previous site
  name, the old eyebrow, the old audience copy and the small statistic. The new capture is the
  landing page as it stands: the hero with its photo and the statistic beside it, the four
  audiences, and "How an internship runs". Taken at 1440px and 2x, then scaled to the 1200x900
  the theme directory asks for, so the type is sharp rather than resampled from a 1200px capture.

= 1.7.20 =
* **Fixes the Log in button being drawn across "Remember Me".** 1.7.19 addressed the wrong thing:
  the row's spacing was never reaching the page. Core styles it from `.login form .forgetmenot`
  and `#login form p`, both of which outrank anything a class can say, so the row stayed floated
  - out of flow, with the submit paragraph riding up into it - and its margins stayed at zero.
  The rule is written against `#login` now, which is the specificity core set, and the row is
  back in flow with 24px between it and the button. Checked against wp-login.php on the staging
  site rather than reasoned about: 20px above, 24px below, no overlap.

= 1.7.19 =
* **The login card's brand no longer overhangs the form under it.** At the site header's sizes
  - a 38px mark beside a 20px name - "WordPress Education Dashboard" measured about 347px
  against the 294px between the card's insets, and `white-space: nowrap` sent the difference out
  over the padding. The mark is 32px and the name 16px on this card, and a name too long for one
  line now wraps inside the padding instead of hanging off the card.
* **Balanced the space around "Remember me".** It had over 40px above it and 20px below, because
  the gap above belongs to whatever `login_form` printed before it - a captcha plugin's markup,
  carrying core's paragraph margin - rather than to the row itself. That element ends 16px above
  the checkbox now, and the step down to the Log in button is 24px.

= 1.7.18 =
* **The hero's statistic now borrows the hero's own type.** The number is the headline's size
  and color - 44px brand blue, 34px below 900px, where the headline steps down too - and the
  line under it is the lede's size and color at a heavier weight. The two lines were also
  crowded together; there is 10px between them now, so the card reads as a figure with a
  caption rather than as one block of text.

= 1.7.17 =
* **The hero's statistic is a card again, not a caption.** Bigger - the number at 34px, the line
  under it at 13px and semibold instead of 12px and gray - and overhung further off the photo's
  left edge, out into the white between the two columns, where it reads as its own object rather
  than as something stuck to the corner of the image. The number is set in tabular figures, so
  the card does not reflow when the count changes.
* **The hero's eyebrow now reads "WordPress Credits Program"** rather than "WPCredits program",
  which is the program's actual name and the one the handbook uses.
* Follows the plugin's Resources section, whose two halves swapped: the divider and the 32px
  either side of it are keyed to which column is second now rather than to which one it is, so
  the section is not restyled again next time they move.

= 1.7.16 =
* **Past students get the same row as current ones.** Only the "Currently mentoring" list was
  enhanced, so a finished student kept the plugin's own card: the Student report form button was
  buried in the body, and the one-line preview the plugin prints was hidden by the stylesheet,
  which left the row as a bare name and a face. Past rows now carry the institution, the end date
  and the note column, with the report form button in the bar.
* **The search finds past students, and opens their section to show them.** Searching for someone
  a mentor finished with last term answered "No students match that search," because the search
  only ever ran over the current list - and any match would have been inside a closed disclosure
  anyway. Past students are counted in the match total now, and the section opens on a hit and
  closes again when the search is cleared. The triage filters are unchanged: "Need a call" and
  "Ending soon" name states only a current student can be in, so the section drops out of a
  filtered view rather than sitting under it unfiltered.
* **No more "Share this:" under a post.** Jetpack printed sharing buttons and a "Customize
  buttons" link after every post. This site is signed-in only, so each button shared a page the
  recipient cannot open.

= 1.7.15 =
* **The expanded student panel is symmetric now.** Its left padding was 52px against the right's
  12px - a 72px text indent inherited from an earlier layout. Two things were wrong because of
  it: the gap on the left was too wide, and the split between the details table and the notes
  sat 20px right of the card's centre, so it did not line up with the rule between Resources and
  Updates. Both are fixed by one value: the table's left edge, the student's name in the row
  above and the notes panel's right edge all sit on the card's 32px inset, and the split is on
  the card's centre.

= 1.7.14 =
* **The band below the card now matches the one above it: 16px, and 10px below 900px.** It was
  48px, which read as an empty stretch between the card and the footer. Applies to the Report
  Cards and to posts and pages alike.

= 1.7.13 =
* **Halved the band between the header and the card, to 16px** - 10px below 900px. It applies to
  the Report Cards and to posts and pages alike, so no view insets from the header differently.
  The 48px below the card is unchanged: that space separates the card from the footer, not from
  the chrome immediately above it.

= 1.7.12 =
* **Posts and pages are the site's full 1240px content width now**, the same as the Report Cards
  and the front page, rather than the 720px default measure.
* **Cut the band above and below them back to the dashboard's own 32px and 48px.** At 48/64 it
  had become a gap of its own once the white stripe above it was gone.

= 1.7.11 =
* **Closed the white stripe between the header and the page.** Core gives every top-level block
  a 20px `margin-block-start`, and on a `main` that paints a background that margin sits outside
  it - so a white band ran under the header's rule and along the top of the footer. Removed on
  the dashboard and content mains and on the footer that follows them. The previous release
  added padding inside the background, which was right for the card's breathing room but could
  never close this gap.

= 1.7.10 =
* **Single posts and pages are cards now**, on the soft surface, matching the Report Cards and
  the archive's entries. They were prose on bare white, which on a site where every other view
  is carded read as a missing template rather than as a choice. Edge to edge below 782px.
* **Fixed the white bands above and below the dashboard card.** The 32px and 48px were margins
  on the card, and a `main` with no padding or border of its own does not contain them - they
  collapsed out, so those bands fell outside the background and painted in the page colour.
  They are the parent's padding now.

= 1.7.9 =
* **"Mentor sign in" in the header is now "Log in".** Students, institutions and administrators
  all use the same form, and the old label read as though it were only for mentors.
* Dresses the Resources section's two columns: an equal 32px either side of a vertical rule,
  the same divider idiom the Report Card's own two columns use, stacking at 900px - the width
  the page's other columns stop being columns.
* Styles the Updates list: title above date, soft rules between items, brand-coloured
  "All updates" link.

= 1.7.8 =
* Sizes the Slack logo to the action buttons' own height - their 1px border, 15px padding and
  24px line, twice over, so 56px - set in this file because the button height is set here.

= 1.7.7 =
* The Slack logo is no longer styled as a button - no border, fill or padding, and these rules
  now exist to keep anything else from putting a box back around it. It lifts slightly on hover
  instead of taking a background, since Slack's colours may not be changed.

= 1.7.6 =
* Dresses the Slack button for Slack's full logo rather than a square icon: the same 54px height
  as the labelled buttons beside it, with narrower side padding, because the logo carries its own
  breathing room and the buttons' usual 32px on top of it reads as a gap.

= 1.7.5 =
* Dresses the Resources section's icon-only Slack button: square, and at exactly the height of
  the labelled buttons beside it - the same 15px of padding around a 24px box. It keeps a light
  background on hover as well, because Slack's mark may not be recoloured and needs one.
* On a phone the labelled buttons span the column; the icon does not stretch with them, since
  a square stretched across a column is not a square.

= 1.7.4 =
* The header button is called "Need help?".
* Its counterpart at the foot of the Mentor Report Card is folded into the same rule as the
  course and report-form buttons, so all three are one size, one radius and one weight
  rather than three buttons that resemble each other.
* Adds a template for the Handbook Assistant page. It was falling through to the ordinary
  page template at 720px while everything else on the site is 1240px, so it read as a
  different site. It now uses the same wide card shell as the two Report Cards.

= 1.7.3 =
* Adds an "Ask the handbook" button to the header, opening the plugin's handbook assistant
  without leaving the page. Dressed as the header's other links rather than as a call to
  action, since it sits in that row and should read as part of it.
* The button and the panel meet at one data attribute and one method call, so the theme
  decides where the way in belongs and the plugin owns everything behind it. It renders
  nothing when the plugin is inactive, the assistant is switched off, the handbook has never
  been synced, or the reader is not allowed to use it - one question asked of the plugin
  rather than four rules reimplemented here.

= 1.7.2 =
* The mentor dashboard template renders the page title again, as "Mentor Report Card" above
  the card, matching the student page. The script no longer folds it into the mentor's
  identity line - the page's title and whose page it is are two different things.
* Drops a dead `.wpc-dash-enhanced .wpc-dash__title` rule. It zeroed the title's inset for
  the arrangement where the script moved the title inside the dashboard root; that class
  goes on an element the title is a sibling of, so it could never have matched.
* The "Viewing as student" control has space beneath it on the Student Report Card. The
  shared rule leaves none, because on the mentor page the header below brings its own
  padding - on the student page the next section's top rule was drawn hard against it.

= 1.7.1 =
* The course and report-form buttons are much larger. Everything above them on the Student
  Report Card is reference; those two links are the only things on the page a student does,
  and at the shared 13px button size they read as two more table controls. They span the
  column on a phone rather than wrapping a label mid-word, and print at text size.
* Both call sections split at the card's centre line. The 24px gutter was a `column-gap`, so
  the rule between the halves - which sits on the leading edge of the second column - landed
  12px right of centre, while the section above it split at exactly 50%. Two rules meant to
  read as one line were visibly apart. The gutter is now paid out of both halves.
* On the mentor page the availability panel starts level with the first call card. The diary
  column opens with a section label and the availability column does not, so the two panels
  began a label's height apart.

= 1.7.0 =
* **Passes WordPress Coding Standards with zero violations.** `phpcs.xml.dist` pins the
  standard so `phpcs` with no arguments is the whole check. The one exclusion is the block
  asset filename, which WordPress dictates.
* Fixed by the pass: a missing translator comment, two reserved-keyword parameter names,
  and a `phpcs:ignore` that had drifted onto the line above the read it described and so
  stopped applying. Renaming one of those parameters left a dangling reference in the
  function body that would have rendered an empty class attribute - caught by adding
  `VariableAnalysis` to the ruleset.
* Notices from the plugin now arrive inside the content rather than above the header
  (WPCredits Program Manager 1.21.0), so the band styling applies where the page begins.

= 1.6.13 =
* Dresses the plugin's new header notices (WPCredits Program Manager 1.19.0) as a band
  above the sticky header: amber tint, and the text held to the chrome's own measure so it
  lines up with the brand directly above it rather than starting at the window edge. In
  `style.css`, not the dashboard skin - a notice appears on every page, and that skin only
  loads on the two dashboards.
* Two notices, for somebody in two audiences, separate with a hairline rather than
  repeating the band's edge. Print drops them.

= 1.6.12 =
* Follows the plugin's 1.18.1 move of My mentor call out of the page grid and across the
  card: its internal booked-beside-picker split gets this design's gutter and divider,
  matching the mentor page's diary-beside-availability treatment.
* The program section stops spanning two rows and the vertical rule belongs to the mentor
  section alone - a full-width section carrying a left border would have drawn a stray rule
  down the middle of the card.
* Print flattens the new split along with everything else.

= 1.6.11 =
* Styles the plugin's new sending state: the pressed control keeps its brand fill while it
  reads "Booking…", because it is the only thing on screen saying the page is working and
  must not fade at the same moment. The controls that were not pressed step back.

= 1.6.10 =
* Insets the availability panel's save confirmation, which the plugin now renders outside
  the disclosure so it can be read with the panel shut.

= 1.6.9 =
* Styles the plugin's new **Copy hours** control: a tinted strip under the weekly grid with
  a small secondary button, so it reads as a tool for filling the form in rather than as
  part of the schedule - and so it is not mistaken for the form's own Save.
* Its status line sits on its own row, so a message appearing does not shuffle the controls
  sideways.

= 1.6.8 =
* **A mentor's empty fields are no longer flagged amber** in the My mentor card. The amber
  means "this is yours to fill in" - true of the student's own rows, three of which carry
  an Edit control right there, and false of their mentor's. A student cannot add their
  mentor's website, so flagging its absence asked them for something they cannot give. The
  row still reads "Not set"; it just stops being a task. The row's icon stays at full
  strength there too, so no trace of a flag is left on a row that is deliberately not
  flagged.
* Keyed on the `--mentor` section class the plugin already emits, so this needed no markup
  change on the plugin side.

= 1.6.7 =
* Follows the plugin's 1.16.1 move of the student's identity into the My program section:
  the card, its 88px portrait and its name now take the same treatment as the mentor card
  beside them. The old full-width-header rules are gone, including the one that gave it the
  card's 32px inset at 782px - inside a section that inset comes from the section, and
  keeping both indented it twice.

= 1.6.6 =
* Styles the plugin's new **My course and report form** section: the two buttons side by
  side, with the report form outlined in brand rather than filled. The course is what a
  student is working through; the report form is the errand, so it reads as the second
  button without stopping being one.
* Retired the rules for the old single-button `.wpcpm-student__action`, which the plugin
  no longer emits.

= 1.6.5 =
* The team icon now labels its row, so it takes the same gray as the other row icons. The
  question mark that stands in for an unchosen team is not dimmed on an empty row, unlike
  the rest: there it is the information rather than a repeat of the label.
* Dropped the rules for the value-cell team wrapper, which the plugin no longer emits.

= 1.6.4 =
* An availability panel with nothing set says so in `#daa39b`. Restated here because this
  sheet's muted-text group already claims `.wpcpm-availability__state` and would otherwise
  hand it the same gray as everything else in that panel.

= 1.6.3 =
* Team icons take the same gray as the contact-row icons, and keep it inside the link -
  the team name carries the link color, the icon stays quiet beside it. Print drops them
  with the other row icons.

= 1.6.2 =
* Dresses the contact-row icons the plugin adds in 1.14.2: quieter than the label they
  sit beside, since they identify a row at a glance rather than carrying information of
  their own, and dimmed further on a row with no value so "Not set" reads as one state.
* The inline **Edit** form gets a rule above it when open, so its inputs read as an
  editor for the row they are in rather than as another row of the table.
* Print drops the icons along with the edit control, and flattens the new label wrapper
  back to inline so the mentee table still prints as one running line per student.

= 1.6.1 =
* Follows the plugin's 1.14.1 layout fixes: the two-column grid moved off the dashboard
  root onto the plugin's own wrapper, so the student's identity header and the "view as"
  control are no longer competing with placed sections for a row - which is what put the
  student's name and photo at the foot of the page.
* The program section now spans both rows of the left column, beside the mentor and their
  call section on the right.
* The mentor's contact table sits below the whole card rather than inside its text
  column, so it lines up with the portrait.
* Styles the inline **Edit** control that replaces the separate details form: a link
  beside the value, not a button that would outweigh it.

= 1.6.0 =
* Dresses everything WPCredits Program Manager 1.14.0 adds: the student's own editable
  details, the two new detail rows, and the mentor page's diary sitting beside its
  availability editor, divided by a rule that becomes a horizontal one when they stack.
* **Fixed: the student page's profile photos did not look like any other photo on the
  site.** They carried their own brand-tinted border instead of the shared photo mount -
  white ring, hairline, backdrop - that the mentor page and the header chip use. Both
  the student's portrait and their mentor's now take the shared treatment, and both are
  larger: 96px and 88px.
* The mentor page no longer prints its own title. The plugin makes the mentor's name the
  page heading, which says more than "My Students" did, so `wp:post-title` is out of
  `page-mentor-dashboard.html` and the script no longer lifts a title into the identity
  block. The name takes the heading size the title had, on screen and on paper.
* The student page is two stacked columns now, placed explicitly rather than left to
  auto-flow: their program and their own details on the left, their mentor and booking a
  call with that mentor on the right. Auto-placement would have paired "your details"
  with "your program" and pushed the mentor onto a row of its own.
* American English throughout, matching the plugin.

= 1.5.1 =
Cross-check against the plugin. Three findings, all at the boundary between the two.

* **Fixed: the theme could describe a different mentor than the page was showing.**
  `wpcredits_dashboard_mentor()` reimplemented the plugin's own resolution, because the
  plugin kept it private - and the copy tested for the Mentor *role*, which an
  administrator who also mentors never holds. The plugin rendered their own students
  while the script was handed the first mentor by name; the two join on Airtable record
  ID, so nothing matched and every row lost its end date and note count. It now calls
  `WPCPM_Mentors_Dashboard::current_mentor()` (plugin 1.13.0), with the old logic kept
  only as an older-plugin fallback. This is the same defect that was reported as
  "an administrator, a program lead, sees no mentors or students".
* **Removed the dead Slack code.** The plugin renders the handle as a link itself now,
  so the script's `linkSlack()` was unreachable from both sides - it bailed when the
  cell already held a link, and bailed again when the cell was "Not set". It was kept
  alive only by a `__()` call against the *plugin's* text domain, which a theme should
  not make: the string is not in the theme's own catalog, and finding a table row by
  matching a translated label breaks on any wording change. `wpcredits_slack_chat_url()`
  and its filter went with it - **filter `wpcpm_slack_url` in the plugin instead.**
* **The call calendar's stylesheet is now a declared dependency.** The plugin enqueues
  it from a render callback during `the_content`, long after `wp_enqueue_scripts`, so
  without the dependency it printed after this theme's sheet and won every tie. Nothing
  looked wrong only because every theme rule for the calendar is prefixed
  `.wpc-dashboard-page`; one unprefixed rule added later would have lost silently.

= 1.5.0 =
* Dresses the call calendar that WPCredits Program Manager 1.13.0 adds to both
  dashboards - the mentor's diary and availability editor, the student's month grid,
  slot picker and timezone chooser - in this card's grays, control shapes and 32px
  inset. The plugin's own calendar stylesheet is theme-agnostic in the way its
  dashboard sheet is, so the same three things are stood down: transparency for real
  grays, no inset for this one, and generic controls for wp-admin's.
* Brand tint marks a bookable day on the grid; today is a ring in the line color,
  because brand already means "bookable" there and today is frequently neither.
* The availability editor's chevron flips when it opens. The plugin reuses
  `.wpcpm-mentee__toggle` for it, which inherits this theme's masked icon - but not
  its open state, which is keyed to `.wpcpm-mentee__disclosure`.
* The student page's call section spans both columns rather than taking one: a
  calendar in half a card is unusable, and it is not a third peer of the program
  and mentor sections.
* Print keeps the booked-call list and drops the calendar's controls - a month grid
  of links, a slot picker, a timezone select and the availability form are no use on
  paper.

= 1.4.5 =
* "Your program" and "Your mentor" are two columns on the student page rather than
  one section under the other, divided by a vertical rule that runs the full height
  of the taller one. Back to a single column at 900px, where the mentor page's
  two-column body also collapses - these are label-and-value tables with a 170px
  label, and half of a card that narrow leaves nothing for the value.
* The plugin renders the two sections as siblings of the identity header and the
  "last updated" line rather than wrapping them together, so the grid is declared on
  the root and everything that is not a section is spanned back across both columns.
  Anything the plugin adds to that page in future will span full width by default,
  which is the safe direction. When the program has not synced yet the mentor
  section is alone and spans the card, rather than leaving half of it blank.
* Print puts the sections back in one column. Its rules carry the `.wpc-student-page`
  prefix so they match the screen rules they undo - this sheet loads later, but load
  order only settles ties between selectors of equal weight.

= 1.4.4 =
* An opened student is one box around the row and its details, rather than a
  bordered row with the table and notes loose beneath it. When the disclosure is
  open the border and the 20px inset move from the summary out to the disclosure
  itself, and the summary keeps only its bottom edge, as the rule dividing the
  header from the content. `overflow: hidden` on the container clips the summary's
  square corners to the container's radius, so the tint meets the rounded top edge
  without restating the radius in two places.
* Because the body's padding is now measured from a border 20px inside the card,
  it drops by that much - 52px and 12px where it was 72px and 32px - so the table's
  left edge and the notes panel's right edge have not moved. Halved again at 900px,
  where the inset is 10px.

= 1.4.3 =
* A collapsed student is a bordered box, `1px solid var( --wpc-line-card )` - the
  same hairline as the details table and the notes panel it opens onto - instead of
  a band separated from its neighbours by a single rule. Three things follow from
  that one border, and any of them will look wrong if it is changed alone: the row's
  own `border-bottom` is gone (two lines where one was wanted), rows carry 4px of
  vertical padding so adjacent 1px edges do not meet and read as 2px, and the
  summary is inset by a margin so the border clears the card's gutter, with the
  padding cut by the same amount so the text stays on the 32px measure. The spacing
  is padding on the row rather than a margin between rows because the search filter
  hides rows with the `hidden` attribute, and a sibling margin would strand a gap
  above whichever row survived.
* Opening a student now tints all four edges brand rather than only the bottom one,
  which was the only edge that existed before.
* Print resets the new margin as well as the border.

= 1.3.2 =
* The site header sticks again. `position: sticky` was on the `<header>` inside the
  template part, and a template part renders in a wrapper exactly its own height -
  so the bar had nowhere to travel and scrolled away with the page. The stickiness
  moved to the wrapper. This is what made the dashboard's "Need a call" bar look
  like it was floating over the site: once the bar had gone, it was the only thing
  left pinned.
* Bigger profile photos again: 80px for the mentor's portrait, 44px in a student
  row. Rows are about 70px tall as a result - worth knowing on a long list.
* A student's Slack handle now links to https://make.wordpress.org/chat/, filterable
  through `wpcredits_slack_chat_url`. The plugin prints the handle as plain text,
  rightly - Slack has no public URL for a person - but a handle is only useful next
  to the place you would type it. *(Superseded in 1.5.1: the plugin does this itself,
  and both the theme's function and its filter are gone. Use `wpcpm_slack_url`.)*

= 1.3.1 =
* "Past students" now starts on the same line as "Currently mentoring". Its heading
  lives in a `summary` and was carrying both the summary's indent and its own, with
  a leading disclosure triangle pushing it further right; the triangle now trails the
  heading and the padding sits in one place.
* Bigger profile photos: 64px for the mentor's portrait, 36px in a student row.
* Student names are no longer truncated. The name column is a minimum width rather
  than a fixed one - the institution beside it flexes instead.
* The call-notes panel is level with the details table beside it. The plugin gives
  that section a 1.25em top margin, which is right in one column and 20px of drift
  in two. It is also wider: 440px rather than 380px.

= 1.3.0 =
* Added `screenshot.png` - a 1200×900 capture of the landing page from a real
  install, taken at 2× and downscaled.
* The band and the search toolbar are built even when a mentor has no current
  students. The script used to stop at an empty list, which took the card's header
  and search box with it and left "Last updated…" flush against the card's edge.
* The toolbar sits above the group headings rather than inside the current group,
  so it lands in the same place whether or not that group has a list.
* Restored the footer on the mentor page template.
* Header content now measures 1240px, not 1240px minus its gutters, so the brand
  lines up with the left edge of the content below it.
* The navigation and the account chip sit at the right edge of the content. The
  reference centers that group around its wide blue button; an avatar centered there
  read as unaligned.
* Profile photos take the credits-program-mentors mount - a white ring with a
  hairline outside it - on the mentor page and in the header.
* Even space above and below the plugin's "no students" lines, and the Past students
  disclosure triangle is inset 32px like every other arrow in the card.
* Login screen: the site title's trailing word is upright rather than italic, the
  brand is the header's own layout and type - mark beside name at 38px and 20px -
  and the "Mentors land on My Students straight after logging in." line is gone.

= 1.2.0 =
* Header and footer now reproduce the WordPress Education Initiatives theme exactly:
  76px bar, 1240px measure with 40px gutters, brand-blue 38px mark, the site title's
  muted tail, a centered navigation with 240px dropdown panels, the 44px bordered
  mobile toggle and its drop-down panel, and the four-column footer over a credit
  bar.
* theme.json's `wideSize` is 1240px and root padding 40px, matching the reference,
  so the chrome and the page content share one measure. The dashboard card follows.
* Submenu panels are shown and hidden with `display` rather than core's opacity
  fade, which could not be overridden reliably at the specificity core uses.

= 1.1.0 =
* Fixed the triage grouping against WPCredits Program Manager 1.8.0, which splits
  the mentor page into "Currently mentoring" and "Past students": the script now
  triages the current list only, and past students are never filed under "Need a
  call".

= 1.0.0 =
* First release: block theme with the program landing page, branded login, gated
  and empty states, and the mentor dashboard skin with triage groups, filters,
  search and a print sheet.
