=== WordPress Education Initiatives ===
Contributors: maciejpilarski
Requires at least: 6.6
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.5.0
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: education, one-page, full-site-editing, block-patterns, custom-colors, editor-style, block-styles, translation-ready

A block (Full Site Editing) theme for open-source education initiatives.

Try it instantly, no install required: https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/maciejpilarski/wordpress-education-blocks/main/blueprint.json

== Description ==

A Full Site Editing theme that renders the WordPress Education landing page.
Unlike a classic theme, everything here is managed in the WordPress Site Editor
(Appearance → Editor):

* The header and footer are editable **template parts**.
* The front page and all fallback views are editable **block templates**.
* Global colours, typography and spacing come from **theme.json** and are
  adjustable under Appearance → Editor → Styles.
* Each landing-page section (hero, feature cards, statistics, programs, campus,
  resources, stories, call-to-action) is a reusable **block pattern** available
  in the block inserter under the "Education" category.

The top navigation uses the core Navigation block, so its dropdown menus and the
mobile menu are edited like any other menu — no Customizer required. Photos are
editable Cover blocks that show a styled gradient placeholder until you add an
image. Statistics animate up from zero when scrolled into view; the target is
read from the number you type, so they stay fully editable.

== Installation ==

= WordPress Playground =

For a quick look with no install, open the theme in WordPress Playground:
https://playground.wordpress.net/?blueprint-url=https://raw.githubusercontent.com/maciejpilarski/wordpress-education-blocks/main/blueprint.json

This spins up a disposable, in-browser WordPress site with the theme installed
and activated. Nothing is saved outside that browser tab.

Or, with Node.js and the WordPress Playground CLI, run it locally from a
checkout of this repository:

`npx @wp-playground/cli@latest server --auto-mount`

= Manual install =

1. Appearance → Themes → Add New → Upload Theme.
2. Upload `wordpress-education-blocks.zip` and click "Install Now", then
   "Activate".
3. The landing page shows on the site's front page automatically
   (front-page.html).
4. Edit anything in Appearance → Editor: Templates, Patterns, Template Parts,
   and Styles.

== Frequently Asked Questions ==

= How do I edit the header menu? =
Appearance → Editor → Patterns → Template Parts → Header, then edit the
Navigation block. Or edit the menu directly on any template.

= How do I add the photos? =
Select a photo placeholder (a Cover block) in the editor and set its background
image. The gradient placeholder shows until you do.

= How do I change the colours globally? =
Appearance → Editor → Styles → Colors.

= Where are the section layouts? =
They are block patterns (Appearance → Editor → Patterns → Education), and are
also available in the block inserter when editing any page or template.

== Changelog ==

= 1.5.0 =
* Added WordPress Playground support: a blueprint.json and "try it live" links
  so the theme can be previewed in-browser or run locally via the Playground CLI
  (contributed by Jordan Hlebarov). Repointed the Playground URLs to this
  repository.

= 1.4.2 =
* Renamed the theme (as shown in the dashboard) to "WordPress Education
  Initiatives".

= 1.4.1 =
* Fixed the career path timeline: the connecting line now runs through the
  centre of the stage dots (dots are box-sized correctly and re-centred on the
  line), on desktop and mobile.

= 1.4.0 =
* Added a "Career path timeline" block pattern: an eight-stage vertical timeline
  with phase-colour legend and cards that expand on click (native <details>, no
  JavaScript). Styled to match the theme.

= 1.3.1 =
* Refined the "Why student contribution matters" pattern to match the rest of
  the site: added an eyebrow above the heading, moved the heading into the shared
  centered section-head, and tidied spacing and the image treatment.

= 1.3.0 =
* Added a "Why student contribution matters" block pattern: a rounded highlight
  panel with a centered heading, supporting copy, and an editable image.

= 1.2.2 =
* Reduced the empty space between the header and the page content: trimmed the
  hero's top padding and top-aligned the hero columns so the headline sits just
  below the menu, and tightened the top padding on inner pages.

= 1.2.1 =
* Pages no longer show their title automatically. The Page template omits the
  title; to display it on a specific page, add a "Post Title" block to that
  page's content. Removed the now-redundant "Page (no title)" template.

= 1.2.0 =
* Patterns now insert with their layout locked: each section and its main pieces
  (cards, columns, statistics, path cards) are protected from being moved or
  deleted by accident, while all text and images stay editable. To change the
  layout, select the block, open the ⋮ options → Unlock, adjust, then re-lock.

= 1.1.0 =
* Added a "Career paths" block pattern: four contributor career paths, each with
  progressive stages ending in a career outcome, plus a closing call-to-action.
  Styled to match the theme (eyebrow, rounded cards, four-colour palette).

= 1.0.1 =
* Fixed the hero/photo Cover blocks: the gradient placeholder now hides once a
  background image is set, and an Image block placed inside a Cover fills and
  clips to the Cover shape (e.g. the hero circle).
* Fixed card and statistics grids reading as left-aligned: they now use fixed
  column counts (with responsive breakpoints) instead of auto-fill, so items
  fill each row evenly.
* Fixed horizontal overflow on small screens by trimming the header (brand +
  hamburger only), tightening page gutters and clipping stray overflow.
* Increased the spacing between the Resources card icons and their headings.

= 1.0.0 =
* Initial release.

== Credits ==

* WordPress logo mark — from the WordPress project, GPL-compatible.
* Bundled illustrations are inline SVG authored for this theme.
