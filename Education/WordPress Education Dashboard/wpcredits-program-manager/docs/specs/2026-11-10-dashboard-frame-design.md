# The dashboards' frame, first on the Administrator Dashboard (design, 10 November 2026)

Plugin: WPCredits Program Manager 1.123.69, with WPCredits theme 1.32.22. Built on the cohort timeline
(1.123.68), which ships in the same release.

## 1. Why

The five dashboards open like settings screens under the site's header, in a 1240px column. The version 3
concepts of 8 to 10 November 2026 draw them in the homepage's voice: a frame with a menu down the side and a
main area as wide as the window, a serif head that says where the person stands, the homepage's figures band,
and what waits as rows. The product owner approved the concepts and asked for the design to be built one
dashboard at a time, the Administrator Dashboard first, then the Sponsor and Institution Dashboards, then the
Mentor and Student Report Cards.

## 2. The product owner's decisions (10 November 2026)

1. **Full width with a side menu**, on the left in a left-to-right language and on the right in a
   right-to-left one.
2. **The concepts as drawn**, not a restyle of the old cards.
3. **The Ask box goes down the side menu**, under Help.
4. **What waits is a status tag in words** on a plain card, the homepage's program-card tag, never a colored
   edge (option A of two). DESIGN.md's rule changes to say so.
5. **One mark for counts and states**: the menu's counts, the rows' ages and the cohort timeline's tiles,
   flags and outlook are all that tag.
6. **Release the Administrator Dashboard alone**; the other dashboards follow one by one.

The rest is the builder's account, the builder's calls marked as such.

## 3. The frame (`WPCPM_Dashboard_Frame`)

A top bar (the site's name, the homepage, the viewer's photo with Log out), a menu (what the viewer is looking
at; the views; a group of links; Help with the Ask box; Collapse menu) and a main area. Logical properties only.
Above 1,100px the menu is 256px and folds to its icons; from 783px to 1,100px it shows its icons alone with a
dot where something waits; at 782px and below it is a drawer behind a Menu button, which holds focus and closes
with Escape. **Builder's call:** the folded menu is remembered in the browser, not on the account. The theme
names the dashboards it draws as a bare canvas (`add_theme_support( 'wpcpm-dashboard-frame', array(
'administrator' ) )`); only then does the frame print its top bar and the page its H1, so the plugin still draws
correctly under an older theme.

## 4. The Administrator Dashboard

- **Views** by `?wpcpm_view=`: Overview, the cohort timeline (1.123.68) and Reference. **Builder's call:** the
  menu's other items link the existing wp-admin screens, marked as leaving the page (the concepts' open
  question 6, answered with the recommendation).
- **Head:** the number of decisions waiting (the sum of `counts()`'s waiting queues), the oldest queue and how
  long it has waited, read from the rows' own dates, and Review the oldest.
- **Figures:** the homepage's two shares while they may be shown, the active mentors and the programs running,
  with one line saying when each was read.
- **Waiting:** only queues with something in them, each a folded row with its age and a Review cue; the mentor
  applications as a row to their screen; every empty queue named once with a link. A card that holds the
  outcome of the decision just taken is drawn, open, even when its queue is now empty.
- **Watch:** beside the rows on a wide main area, every card folded.
- **Reference:** the sponsors, the programs running, mentor engagement, the Tools from our sponsors and the
  resources.
- Inter for the text and EB Garamond for the head's title and the figures, from the theme's own files.

## 5. Not in this release

The other four dashboards; the Ask box's answers on the page itself; a folded menu remembered per account;
the old attention strip's markup (`render_strip()` stays, unused on the page, for the wp-admin Overview's
sake to be decided later).
