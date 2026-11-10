# The Administrator guide in the frame, and one name per page (design, 11 November 2026)

Plugin: WPCredits Program Manager 1.123.70, with WPCredits theme 1.32.23. Follows the dashboards' frame
(`2026-11-10-dashboard-frame-design.md`, 1.123.69).

## 1. The product owner's asks (11 November 2026)

1. **"Duplicated".** On the live Administrator Dashboard the page's name was shown two or three times: the
   menu opened with "Dashboard, Administrator Dashboard", the head's H1 said "Administrator Dashboard", and the
   cohort timeline added "Administrator Dashboard" as a link back. The page names itself once.
2. **The Administrator guide in the same layout:** "with the sidebar menu on the left for navigation, on the
   right of the content it should list of sections for easy navigation around the guide", like a reference the
   product owner shared (a plain "In this article" list of links beside the text, sub-sections indented), "but
   design adjusted to the site style, design and layout".

## 2. One name per page

- The menu passes no `context` on the Administrator Dashboard. The head's H1 is the one place the name is
  shown; the menu's label still names it to a screen reader. The frame keeps `context` for what a page's head
  does not say, such as whose record a page is when one person may hold several.
- The cohort timeline has no link back: the menu is the way back. It opens with the frame's head, as the
  Reference view does: the page's name, then "Cohort timeline" in the serif, the lede and when the records were
  read.

## 3. The Administrator guide

- **Where:** the page at `WPCPM_Settings_Screen::GUIDE_PAGE_PATH` (page 560 on live), read by Administrators
  only. The theme draws it on a bare canvas, `page-administrator-guide`, the dashboard's; the plugin wraps the
  page's content in the frame for an Administrator in the main loop. The page keeps naming the Documentation
  template: the theme moves its own template ahead of it only while the plugin can draw the frame, so without
  the plugin, or under another theme, the guide is a Documentation page as before.
- **The menu:** the Administrator Dashboard's own, read the same way with the same counts, the Administrator
  guide marked current.
- **The head:** the page's title as the one H1, in the serif.
- **The list of sections:** the guide's `h2`s, and under the section being read its `h3`s, in a column of
  248px beside the guide. **Builder's call:** the guide has 30 sections and 85 parts, too many to show at once
  and keep in view, so only the section being read shows its parts. The column stays in view as the guide
  scrolls, and a list taller than the window scrolls inside itself, keeping the marked entry in view. The part
  being read is marked as the menu marks where the reader is (the blue tint, Blueberry, semibold); its section
  is in ink, semibold. The heading is the menu's small uppercase heading, "On this page".
- **Narrow:** below a main area of 960px the list sits under the title, folded behind "On this page" with a
  chevron, the sections alone; a section picked closes it. Without the script the whole list is shown.
- **Anchors:** the ids bin/build-docs.php writes are kept; a heading without one gets the build's slug.
- **The text:** Inter at 16px, headings 26, 20 and 16px (DESIGN.md's dashboard scale), a hairline above each
  section, images and boxes at 2px corners, code in DESIGN.md's mono (IBM Plex Mono where the reader has it,
  else the system's), breaking anywhere so no long name pushes the page or a table sideways. A table that
  still does not fit scrolls in its box, which is then a stop for the keyboard.

## 4. Found on the way

The guides name two shortcodes in their text, and the published Administrator guide ran them: it showed a
working Mentor Application form and a Student Interest Form mid-text. The build writes a bracket as `&#91;`
from 1.123.70, so a name reads the same and runs nothing; deploy republishes the guides.

## 5. Not in this release

The other guides in their dashboards' frames, which follow each dashboard; the Ask box's answers on the page.
