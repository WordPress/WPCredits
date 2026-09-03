=== WPCredits ===

Contributors: gomp
Requires at least: 6.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.16.6
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: education, full-site-editing, block-patterns, custom-colors, custom-logo, custom-menu, translation-ready, full-width-template, block-styles

A block theme and the front end for the WPCredits Program Manager plugin.

== Description ==

WPCredits gives the WordPress Credits Program a landing page, a branded login, and
a full-width "My Students" page that dresses the plugin's own mentor dashboard as a
compact, triaged, printable list.

= The chrome =

The header and footer reproduce the WordPress Education Initiatives theme: a 76px
sticky white bar on a 1240px measure with 40px gutters, the WordPress mark in brand
blue at 38px, the site title with the word "Education" picked out in blue and
whatever follows it muted, a centered navigation whose dropdowns are 240px panels
with 8px corners and a soft shadow, and a four-column footer over a credit bar that
signs off "Code is poetry."

That measure is theme.json's `wideSize` and root padding too, so the brand lines up
with the content beneath it. Inside the dashboard the patterns are wp-admin native
instead — 13px body text, sentence case, 2px radii on controls, flat notices, no
shadows.

Three details are the theme's rather than the reference's, and are deliberate:

* The navigation marks the current menu item in brand blue. The reference's nav is
  hard-coded anchors with no such state; a real menu on a multi-page site needs one.
* The reference's blue "Join the Initiative" button is the account chip here — a
  sign-in button for visitors, and "My Students" plus a log-out link and profile
  photo once someone is signed in. It keeps the button's shape and position.
* The sign-in button stays in the bar below 960px rather than folding into the menu
  panel, because it is the way in to everything else on the site.

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

* `wpcpm_stale_note_days` (in the plugin since 1.64.0; was `wpcredits_stale_note_days` here) — how long a student can go without a note before
  they need a call. Default 30, matching the plugin's own wording on the page.
* `wpcpm_ending_soon_days` (in the plugin since 1.64.0; was `wpcredits_ending_soon_days` here) — how close an internship end has to be to count as
  ending soon. Default 60.

Dates are compared against the site's today, not the browser's, so a mentor
traveling sees the same grouping as the program manager looking at the same list.

= Templates =

* Front page — the landing page, built from four patterns.
* `page-mentor-dashboard` — full width, no sidebar. Applies automatically to the
  page the plugin creates, which has the slug `mentor-dashboard`.
* Full width page — a custom template for any other page that needs the whole
  width.
* Page, single, index, search and 404.

= Patterns =

Four sections under "WPCredits program" in the inserter: the hero, the four
audiences, "How an internship runs", and the mentor call to action. The hero ships
with its own photo, so the landing page is showable the moment the theme is
activated.

The front-page template references the patterns, so editing a pattern changes the
front page — but only while the page is still referencing them. To edit the landing
page inline instead — to swap the hero photo, say, or change the mentor count — open
the front page in the Site Editor, select the pattern and detach it. **Detaching
copies the markup into the page**, and from then on that copy is what visitors see:
later changes to the pattern, and to anything set as a block attribute rather than in
CSS, stop reaching it. Styling still applies, because that comes from the stylesheet.

= The login screen =

`wp-login.php` is not a template, so the branding is applied through the login
hooks: the header's own brand — mark beside name — over the form, and a note about
where a first password comes from underneath it. Nothing about authentication
changes.

== Installation ==

1. Upload the theme and activate it.
2. Install and activate WPCredits Program Manager. 1.13.0 or later is what this
   version is built against; 1.7.2 is the floor, and everything newer than a given
   plugin degrades rather than breaking — every call into the plugin is guarded on the
   class or method existing. Without the plugin at all the theme is a small, working
   marketing theme; the dashboards and the viewer chip simply have nothing to show.

   Two things specifically need 1.13.0: the call calendar's styling, and
   `WPCPM_Mentors_Dashboard::current_mentor()`. On an older plugin the theme falls back
   to resolving the mentor itself, which is wrong for an administrator who also mentors
   — see Known limitations.
3. Build the header menu in the Site Editor. Top-level items with children get the
   reference's dropdown panels; items without stay plain links.
4. Set a static front page under Settings → Reading if you want the landing page.

The footer's three link columns are List blocks, not menus, so a site with one
navigation does not need three more. Point them at real pages in the Site Editor —
they ship with the reference's labels and `#` placeholders.

== Known limitations ==

* A student whose Airtable record has no ID is left ungrouped: the plugin omits the
  anchor for those rows, so there is nothing to match them to. They stay in the list
  in the plugin's own order.
* Visiting a gated page directly is handled by the plugin, not the theme: a
  logged-out visitor is redirected to the login form, and a logged-in one without
  the level gets `wp_die()`. That last screen is WordPress's own and is not themed
  here — restyling it would mean intercepting the plugin's denial. The plugin's
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
  ID, nothing matches — rows lose their end date and note count. There is no fixing it
  from the theme; the plugin exposes `current_mentor()` from 1.13.0 precisely so the
  theme can stop guessing.

== Changelog ==

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
* **A menu holding one item no longer hides behind a hamburger.** On a phone the header collapsed its navigation into a toggle whatever was in it, so tapping the hamburger revealed a single link. Reported by Celi Garoe on rechecking the phone layout. A lone item now sits inline beside the logo; add a second and the toggle comes back on its own.

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
* Adds the official wordpress.org global header and footer, above and below the site own chrome —
  the arrangement wordpress.org properties use, with the global bar outermost and the site own
  navigation inside it. They come from the WordPress.org Global Header and Footer plugin, which
  vendors the blocks from WordPress/wporg-mu-plugins.

= 1.10.2 =
* The Documentation sidebar card ends under its search instead of matching the article height.
  Stretched, it left thousands of pixels of empty white below the search with nothing to put there.

= 1.10.1 =
* The Documentation sidebar search sits directly under the contents list rather than at the foot of
  the card. The card is as tall as the article beside it, so the foot of it is thousands of pixels
  below the list — off screen on load, and reachable only by scrolling to the end of the guide.

= 1.10.0 =
* **The landing page mentor figure is live.** It was typed in as 92; the site had 88, and nothing
  would ever have said so. A new **Program figure** block counts the accounts holding a program role
  — the same number the Mentors screen reports — and the sync keeps that in step with the mentors
  Airtable lists as active, so the page never asks Airtable anything. Cached for an hour, since it
  only moves when a sync runs.
* The block takes a fallback, used when the plugin is inactive or the count is zero: a landing page
  opening with 0 mentors is worse than one showing the last figure somebody checked.

= 1.9.6 =
* A search box at the foot of the Documentation sidebar, and the sidebar card is now the same height
  as the article beside it — so the page reads as two panels rather than a short box next to a long
  one, and the search sits at the bottom of the column rather than adrift under the contents list.

= 1.9.5 =
* The gutter between the two cards on the Documentation template is the space the band leaves above
  them — one measurement in one place rather than two that happened to look similar, and both halve
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
* The contents list loses its rule and its indent — the card around it is the edge now.

= 1.9.2 =
* The Documentation template lines its article up with the site logo. The header is a centred
  1,240px container, so its left edge moves with the window — the page now calculates the same
  gutter instead of guessing a fixed inset, and falls back to 40px on windows narrower than that.
  The right side stays free: nothing centred, nothing capped.

= 1.9.1 =
* The Documentation template is full width, with the contents list down the right.
* The contents list never scrolls on its own and is never cut off — the program manager guide has
  fifty-five headings, and every one of them is reachable in the page own scroll. It does not stick:
  a sticky list taller than the window is a list whose last entries cannot be reached at all.
* Narrow layouts follow core own 781px stacking width rather than a made-up one. Core ships
  `flex-wrap: nowrap !important` above that width, so a theme cannot stack a columns block earlier
  however the rule is written.

= 1.9.0 =
* **A Documentation page template**, for the three program guides. Slightly wider than a normal page
  to make room for a sidebar, with a contents list in it and the article beside it capped at a
  readable measure — the extra width buys the sidebar, not longer lines.
* The contents list is a theme block, **Table of contents**. WordPress has one, but it is provided by
  the Gutenberg plugin rather than by core, and it returns nothing at all outside `the_content` — so
  it cannot be used in a template's sidebar, which is where a contents list belongs.
* The list sticks beside the article and scrolls on its own when it outgrows the window; below 900px
  it becomes a block at the top of the page instead.

= 1.8.25 =
* Dresses the feedback scales as stars for plugin 1.59.0 — amber when chosen or hovered, ink when
  reading somebody else's answers, and the focus ring on the star since the radio it belongs to is
  not drawn. Drops the boxed treatment the scales had, including a white-on-chosen rule that would
  have painted the chosen star white.

= 1.8.24 =
* Removes the design preview added in 1.8.23. It was a trial layer behind a query flag and is gone
  in full — the stylesheet and the three functions that loaded it. Nothing else changes: it never
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
  heading, so everything about them — including their name — is on their side of the line.

= 1.8.20 =
* A survey question now takes the card's body size and ink rather than the 12px muted treatment the
  report form uses for its field labels. A question is read; a field label is scanned.

= 1.8.19 =
* Dresses the feedback surveys the plugin adds in 1.58.0: the 1-to-5 scales, the consent line, and
  the permissions box that has to read as separate from the questions above it. The forms
  themselves reuse the report form's treatment, since to a student they are the same kind of thing.
* The chosen step on a scale is filled rather than ringed — on a row of five, a border alone is
  hard to pick out, which is the one thing a scale has to make obvious. Where `:has()` is
  unsupported the radio stays visible instead, so the answer is never invisible.

= 1.8.18 =
* **Fixes the version stamped on every stylesheet and script the theme loads.** It was written out
  by hand and had sat at 1.7.1 through ten releases, so browsers and edge caches kept serving the
  CSS and JavaScript they already had — a fix would ship and the page would carry on looking the
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
* Dresses the session planner as the availability panel's twin — same box, same inset — so the
  right-hand column reads as two controls rather than as a panel with a loose paragraph under it.
* The closed report form on a mentee's card takes the disclosure's own colour and type, so a link
  standing in for a `<summary>` is not a different kind of thing to look at.

= 1.8.14 =
* **The report-form link stays in the card.** The script used to hoist it into the student's row,
  which was right while it opened the Airtable form — one link, reachable without opening the card.
  Since plugin 1.55.0 it opens that student's report *on this page*, at the foot of their own card,
  so a control in the row that scrolls you into the card below it is a worse version of the
  disclosure triangle already there. The row keeps its "Add note" action for a student who needs a
  call.
* Dresses the mentor section's new left-hand box: it carries the inset, so the diary and the group
  sessions under it keep the same margin.

= 1.8.13 =
* **"No availability set" takes the same red as "Need a call".** Both say the same kind of thing —
  something is not set and somebody is waiting on it — and this one had a pale red of its own.
* Gives group sessions the space the grid used to provide when they spanned the row, now that they
  follow the diary in the same column.

= 1.8.12 =
* The availability panel takes a call card's inset — same 12px/14px — so the schedule and the
  booked calls beside it are two of the same box. Its body keeps only the rule and the space over
  it, since the panel now carries the padding.

= 1.8.11 =
* **"Save my report" takes the same treatment as the card's other actions** — the size and radius
  the course, report-form and Need help? buttons use. It is the one thing a student presses on that
  form, and it was the size of a table control.
* Follows the plugin's availability disclosure, which is now the same control group sessions uses:
  the toggle takes the sessions treatment, and the rules that dressed the old bar — its title span
  and its borrowed chevron — are gone with the markup they styled.

= 1.8.10 =
* Dresses the report form's two new section marks in the card's tokens: the headless divider takes
  the same soft rule as a headed one, and the note under a run of fields takes the muted ink every
  other hint on the card uses.

= 1.8.9 =
* Dresses the report form's lesson headings in the card's own tokens — the soft rule and the muted
  ink — one step below the group legend, so a group, a lesson and a field are three distinct
  levels rather than three things shouting at the same volume.

= 1.8.8 =
* The Save button beside the hours box takes the same type and padding as the controls, so the box
  and the button read as one row rather than as a field with something taller attached to it.

= 1.8.7 =
* **The hours box takes the report form's controls** — same border, radius, type and padding — so
  the field outside the form matches the ones inside it.
* A team's name in the contribution list is an answer rather than a field label, so it keeps the
  card's body type instead of the bold, muted, 12px treatment every question above it takes.

= 1.8.6 =
* Follows the hours box, which draws its own label rather than one of the form's field rows, so the
  label keeps the muted treatment every other label on the card has.

= 1.8.5 =
* **Dresses My course's two columns** — the button that opens the course beside the hours box the
  plugin moved there — and gives the hours label the same muted treatment every other field label
  on the card has.

= 1.8.4 =
* **Dresses the report form's grouped fieldsets.** The plugin's own border and radius are stood down
  for the design's card line, and each group's legend takes the same uppercase treatment as every
  other heading inside a card — so a group reads as a section rather than as a browser default.

= 1.8.3 =
* **Dresses the student's new report form.** Its inputs and textareas take the same controls as the
  availability editor and the session forms, checkboxes are left as checkboxes rather than being
  given a text box's border, and a form a program manager is reading rather than filling in reads as
  information instead of as something broken.

= 1.8.2 =
* **Dresses the plugin's new group sessions.** Each session is a bordered block with the design's
  own card line and radius rather than the plugin's theme-agnostic default, its counts and hints use
  the real grays instead of `opacity`, and the create and note forms take the same controls as the
  availability editor — so the two forms on a mentor's card do not look like they came from
  different plugins.

= 1.8.1 =
* **Dropped "Printing opens every student first." from the ordering line** under the student list.
  The behaviour is unchanged — printing still opens every student, so a printed list is never
  half-empty — but it is the plugin's doing and a mentor reading a list does not need telling.
  The line now reads "Ordered by internship end date within each group, soonest first."

= 1.8.0 =
* **The hero ships with its photo.** Where the pattern had a dashed box saying what picture
  belonged there, it now has the picture: a student taking notes through a video call with their
  mentor, bundled at `assets/images/hero-mentoring.jpg`. A landing page whose main image is an
  empty outline reads as broken, and every install had to do the same first job before the page
  could be shown to anyone. Swap the Image block for your own whenever you like.
* The photo is 1240x745 and 161KB — a JPEG rather than the 478KB PNG it came from, which is the
  right format for a photograph — and its column holds that ratio with a tint behind it, so the
  page does not jump as the image arrives.
* `screenshot.png` is re-captured from the pattern itself, so what Appearance shows is now what
  a fresh install actually looks like.

= 1.7.21 =
* **A current `screenshot.png`.** The old one was captured before the site had a photo, so
  Appearance showed the hero as an empty dashed box — and it still carried the previous site
  name, the old eyebrow, the old audience copy and the small statistic. The new capture is the
  landing page as it stands: the hero with its photo and the statistic beside it, the four
  audiences, and "How an internship runs". Taken at 1440px and 2x, then scaled to the 1200x900
  the theme directory asks for, so the type is sharp rather than resampled from a 1200px capture.

= 1.7.20 =
* **Fixes the Log in button being drawn across "Remember Me".** 1.7.19 addressed the wrong thing:
  the row's spacing was never reaching the page. Core styles it from `.login form .forgetmenot`
  and `#login form p`, both of which outrank anything a class can say, so the row stayed floated
  — out of flow, with the submit paragraph riding up into it — and its margins stayed at zero.
  The rule is written against `#login` now, which is the specificity core set, and the row is
  back in flow with 24px between it and the button. Checked against wp-login.php on the staging
  site rather than reasoned about: 20px above, 24px below, no overlap.

= 1.7.19 =
* **The login card's brand no longer overhangs the form under it.** At the site header's sizes
  — a 38px mark beside a 20px name — "WordPress Education Dashboard" measured about 347px
  against the 294px between the card's insets, and `white-space: nowrap` sent the difference out
  over the padding. The mark is 32px and the name 16px on this card, and a name too long for one
  line now wraps inside the padding instead of hanging off the card.
* **Balanced the space around "Remember me".** It had over 40px above it and 20px below, because
  the gap above belongs to whatever `login_form` printed before it — a captcha plugin's markup,
  carrying core's paragraph margin — rather than to the row itself. That element ends 16px above
  the checkbox now, and the step down to the Log in button is 24px.

= 1.7.18 =
* **The hero's statistic now borrows the hero's own type.** The number is the headline's size
  and color — 44px brand blue, 34px below 900px, where the headline steps down too — and the
  line under it is the lede's size and color at a heavier weight. The two lines were also
  crowded together; there is 10px between them now, so the card reads as a figure with a
  caption rather than as one block of text.

= 1.7.17 =
* **The hero's statistic is a card again, not a caption.** Bigger — the number at 34px, the line
  under it at 13px and semibold instead of 12px and gray — and overhung further off the photo's
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
  only ever ran over the current list — and any match would have been inside a closed disclosure
  anyway. Past students are counted in the match total now, and the section opens on a hit and
  closes again when the search is cleared. The triage filters are unchanged: "Need a call" and
  "Ending soon" name states only a current student can be in, so the section drops out of a
  filtered view rather than sitting under it unfiltered.
* **No more "Share this:" under a post.** Jetpack printed sharing buttons and a "Customize
  buttons" link after every post. This site is signed-in only, so each button shared a page the
  recipient cannot open.

= 1.7.15 =
* **The expanded student panel is symmetric now.** Its left padding was 52px against the right's
  12px — a 72px text indent inherited from an earlier layout. Two things were wrong because of
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
* **Halved the band between the header and the card, to 16px** — 10px below 900px. It applies to
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
  it — so a white band ran under the header's rule and along the top of the footer. Removed on
  the dashboard and content mains and on the footer that follows them. The previous release
  added padding inside the background, which was right for the card's breathing room but could
  never close this gap.

= 1.7.10 =
* **Single posts and pages are cards now**, on the soft surface, matching the Report Cards and
  the archive's entries. They were prose on bare white, which on a site where every other view
  is carded read as a missing template rather than as a choice. Edge to edge below 782px.
* **Fixed the white bands above and below the dashboard card.** The 32px and 48px were margins
  on the card, and a `main` with no padding or border of its own does not contain them — they
  collapsed out, so those bands fell outside the background and painted in the page colour.
  They are the parent's padding now.

= 1.7.9 =
* **"Mentor sign in" in the header is now "Log in".** Students, institutions and administrators
  all use the same form, and the old label read as though it were only for mentors.
* Dresses the Resources section's two columns: an equal 32px either side of a vertical rule,
  the same divider idiom the Report Card's own two columns use, stacking at 900px — the width
  the page's other columns stop being columns.
* Styles the Updates list: title above date, soft rules between items, brand-coloured
  "All updates" link.

= 1.7.8 =
* Sizes the Slack logo to the action buttons' own height — their 1px border, 15px padding and
  24px line, twice over, so 56px — set in this file because the button height is set here.

= 1.7.7 =
* The Slack logo is no longer styled as a button — no border, fill or padding, and these rules
  now exist to keep anything else from putting a box back around it. It lifts slightly on hover
  instead of taking a background, since Slack's colours may not be changed.

= 1.7.6 =
* Dresses the Slack button for Slack's full logo rather than a square icon: the same 54px height
  as the labelled buttons beside it, with narrower side padding, because the logo carries its own
  breathing room and the buttons' usual 32px on top of it reads as a gap.

= 1.7.5 =
* Dresses the Resources section's icon-only Slack button: square, and at exactly the height of
  the labelled buttons beside it — the same 15px of padding around a 24px box. It keeps a light
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
  been synced, or the reader is not allowed to use it — one question asked of the plugin
  rather than four rules reimplemented here.

= 1.7.2 =
* The mentor dashboard template renders the page title again, as "Mentor Report Card" above
  the card, matching the student page. The script no longer folds it into the mentor's
  identity line — the page's title and whose page it is are two different things.
* Drops a dead `.wpc-dash-enhanced .wpc-dash__title` rule. It zeroed the title's inset for
  the arrangement where the script moved the title inside the dashboard root; that class
  goes on an element the title is a sibling of, so it could never have matched.
* The "Viewing as student" control has space beneath it on the Student Report Card. The
  shared rule leaves none, because on the mentor page the header below brings its own
  padding — on the student page the next section's top rule was drawn hard against it.

= 1.7.1 =
* The course and report-form buttons are much larger. Everything above them on the Student
  Report Card is reference; those two links are the only things on the page a student does,
  and at the shared 13px button size they read as two more table controls. They span the
  column on a phone rather than wrapping a label mid-word, and print at text size.
* Both call sections split at the card's centre line. The 24px gutter was a `column-gap`, so
  the rule between the halves — which sits on the leading edge of the second column — landed
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
  function body that would have rendered an empty class attribute — caught by adding
  `VariableAnalysis` to the ruleset.
* Notices from the plugin now arrive inside the content rather than above the header
  (WPCredits Program Manager 1.21.0), so the band styling applies where the page begins.

= 1.6.13 =
* Dresses the plugin's new header notices (WPCredits Program Manager 1.19.0) as a band
  above the sticky header: amber tint, and the text held to the chrome's own measure so it
  lines up with the brand directly above it rather than starting at the window edge. In
  `style.css`, not the dashboard skin — a notice appears on every page, and that skin only
  loads on the two dashboards.
* Two notices, for somebody in two audiences, separate with a hairline rather than
  repeating the band's edge. Print drops them.

= 1.6.12 =
* Follows the plugin's 1.18.1 move of My mentor call out of the page grid and across the
  card: its internal booked-beside-picker split gets this design's gutter and divider,
  matching the mentor page's diary-beside-availability treatment.
* The program section stops spanning two rows and the vertical rule belongs to the mentor
  section alone — a full-width section carrying a left border would have drawn a stray rule
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
  part of the schedule — and so it is not mistaken for the form's own Save.
* Its status line sits on its own row, so a message appearing does not shuffle the controls
  sideways.

= 1.6.8 =
* **A mentor's empty fields are no longer flagged amber** in the My mentor card. The amber
  means "this is yours to fill in" — true of the student's own rows, three of which carry
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
  card's 32px inset at 782px — inside a section that inset comes from the section, and
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
* Team icons take the same gray as the contact-row icons, and keep it inside the link —
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
  control are no longer competing with placed sections for a row — which is what put the
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
  site.** They carried their own brand-tinted border instead of the shared photo mount —
  white ring, hairline, backdrop — that the mentor page and the header chip use. Both
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
  plugin kept it private — and the copy tested for the Mentor *role*, which an
  administrator who also mentors never holds. The plugin rendered their own students
  while the script was handed the first mentor by name; the two join on Airtable record
  ID, so nothing matched and every row lost its end date and note count. It now calls
  `WPCPM_Mentors_Dashboard::current_mentor()` (plugin 1.13.0), with the old logic kept
  only as an older-plugin fallback. This is the same defect that was reported as
  "administrator Isotta Peira sees no mentors or students".
* **Removed the dead Slack code.** The plugin renders the handle as a link itself now,
  so the script's `linkSlack()` was unreachable from both sides — it bailed when the
  cell already held a link, and bailed again when the cell was "Not set". It was kept
  alive only by a `__()` call against the *plugin's* text domain, which a theme should
  not make: the string is not in the theme's own catalog, and finding a table row by
  matching a translated label breaks on any wording change. `wpcredits_slack_chat_url()`
  and its filter went with it — **filter `wpcpm_slack_url` in the plugin instead.**
* **The call calendar's stylesheet is now a declared dependency.** The plugin enqueues
  it from a render callback during `the_content`, long after `wp_enqueue_scripts`, so
  without the dependency it printed after this theme's sheet and won every tie. Nothing
  looked wrong only because every theme rule for the calendar is prefixed
  `.wpc-dashboard-page`; one unprefixed rule added later would have lost silently.

= 1.5.0 =
* Dresses the call calendar that WPCredits Program Manager 1.13.0 adds to both
  dashboards — the mentor's diary and availability editor, the student's month grid,
  slot picker and timezone chooser — in this card's grays, control shapes and 32px
  inset. The plugin's own calendar stylesheet is theme-agnostic in the way its
  dashboard sheet is, so the same three things are stood down: transparency for real
  grays, no inset for this one, and generic controls for wp-admin's.
* Brand tint marks a bookable day on the grid; today is a ring in the line color,
  because brand already means "bookable" there and today is frequently neither.
* The availability editor's chevron flips when it opens. The plugin reuses
  `.wpcpm-mentee__toggle` for it, which inherits this theme's masked icon — but not
  its open state, which is keyed to `.wpcpm-mentee__disclosure`.
* The student page's call section spans both columns rather than taking one: a
  calendar in half a card is unusable, and it is not a third peer of the program
  and mentor sections.
* Print keeps the booked-call list and drops the calendar's controls — a month grid
  of links, a slot picker, a timezone select and the availability form are no use on
  paper.

= 1.4.5 =
* "Your program" and "Your mentor" are two columns on the student page rather than
  one section under the other, divided by a vertical rule that runs the full height
  of the taller one. Back to a single column at 900px, where the mentor page's
  two-column body also collapses — these are label-and-value tables with a 170px
  label, and half of a card that narrow leaves nothing for the value.
* The plugin renders the two sections as siblings of the identity header and the
  "last updated" line rather than wrapping them together, so the grid is declared on
  the root and everything that is not a section is spanned back across both columns.
  Anything the plugin adds to that page in future will span full width by default,
  which is the safe direction. When the program has not synced yet the mentor
  section is alone and spans the card, rather than leaving half of it blank.
* Print puts the sections back in one column. Its rules carry the `.wpc-student-page`
  prefix so they match the screen rules they undo — this sheet loads later, but load
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
  it drops by that much — 52px and 12px where it was 72px and 32px — so the table's
  left edge and the notes panel's right edge have not moved. Halved again at 900px,
  where the inset is 10px.

= 1.4.3 =
* A collapsed student is a bordered box, `1px solid var( --wpc-line-card )` — the
  same hairline as the details table and the notes panel it opens onto — instead of
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
  template part, and a template part renders in a wrapper exactly its own height —
  so the bar had nowhere to travel and scrolled away with the page. The stickiness
  moved to the wrapper. This is what made the dashboard's "Need a call" bar look
  like it was floating over the site: once the bar had gone, it was the only thing
  left pinned.
* Bigger profile photos again: 80px for the mentor's portrait, 44px in a student
  row. Rows are about 70px tall as a result — worth knowing on a long list.
* A student's Slack handle now links to https://make.wordpress.org/chat/, filterable
  through `wpcredits_slack_chat_url`. The plugin prints the handle as plain text,
  rightly — Slack has no public URL for a person — but a handle is only useful next
  to the place you would type it. *(Superseded in 1.5.1: the plugin does this itself,
  and both the theme's function and its filter are gone. Use `wpcpm_slack_url`.)*

= 1.3.1 =
* "Past students" now starts on the same line as "Currently mentoring". Its heading
  lives in a `summary` and was carrying both the summary's indent and its own, with
  a leading disclosure triangle pushing it further right; the triangle now trails the
  heading and the padding sits in one place.
* Bigger profile photos: 64px for the mentor's portrait, 36px in a student row.
* Student names are no longer truncated. The name column is a minimum width rather
  than a fixed one — the institution beside it flexes instead.
* The call-notes panel is level with the details table beside it. The plugin gives
  that section a 1.25em top margin, which is right in one column and 20px of drift
  in two. It is also wider: 440px rather than 380px.

= 1.3.0 =
* Added `screenshot.png` — a 1200×900 capture of the landing page from a real
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
* Profile photos take the credits-program-mentors mount — a white ring with a
  hairline outside it — on the mentor page and in the header.
* Even space above and below the plugin's "no students" lines, and the Past students
  disclosure triangle is inset 32px like every other arrow in the card.
* Login screen: the site title's trailing word is upright rather than italic, the
  brand is the header's own layout and type — mark beside name at 38px and 20px —
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
