# The site header's menu button below desktop width (theme 1.24.20): design

16 October 2026. WPCredits theme release 1.24.20 (renumbered on 17 October 2026, after the Education Hub releases 1.24.18 and 1.24.19; live runs 1.24.19). Theme only: the header part, the two header blocks the theme owns, the stylesheet and one small script. No plugin change.

## 1. Why

When someone is signed in, the header shows the brand, the navigation (Homepage), the help button (Need help?), the links to the Student Report Card and the Mentor Report Card (whichever the reader has), Log out and the account picture. On a wide screen that is one row. Narrower, it wraps:

- on a phone (375px), an Administrator's header is four rows, about 210px, roughly a quarter of the screen before any content: the brand; Homepage and Need help?; Student Report Card and Mentor Report Card; the account picture alone on its line;
- on a small tablet in portrait (641px to about 786px), three rows, 149px;
- on an iPad in landscape (1024px), two rows, about 105px; the bar is one row only from about 1100px.

Signed out, the header is two compact rows on a phone (the brand; Homepage and Log in) and one row above 640px. That is fine.

The owner's words: "Menu on mobile should be a hamburger style or something like that."

## 2. The owner's decisions (16 October 2026)

1. **Option A of the mockups:** when signed in, the header is one row: the brand on the left, then the account picture, then a menu button. The menu holds the links.
2. **Where:** phones and iPads in both orientations, so the signed-in header is one row everywhere below desktop width. Desktop width keeps today's row of links.
3. **The pathway step cue** ("See what it unlocks" taking two lines between about 900px and 1180px on the Education Hub): "fine for now". Not in this release.
4. The owner had nothing to correct in the played-back behavior (section 3), including the signed-out header and keyboard behavior.

## 3. What a reader sees

### Signed in, below desktop width

- One row: the site logo and site title (both already link to the home page), the account picture (unchanged: the photo, or the initials when there is none), and a menu button labeled "Menu" for screen readers, drawn as the usual three-line icon.
- Pressing the button opens the menu under the bar, full width of the bar. It lists, in today's order on the desktop row, only what the reader has today (**designer's call: the owner's mockup listed the report cards before Need help?; keeping the desktop row's order keeps the markup, the desktop look and the tab order unchanged**):
  1. the navigation's own items (today: Homepage);
  2. Need help?, when the handbook assistant is available to the reader (it opens the plugin's assistant panel exactly as it does today);
  3. Student Report Card, when shown today;
  4. Mentor Report Card, when shown today;
  5. Log out (**builder's note: the owner's mockup did not show Log out; today it sits beside the picture, which opens nothing, so the menu is its place. This is the designer's call, not the owner's**).
- The page the reader is on is marked in the menu (the links already carry `aria-current="page"` and `is-current`).
- Pressed again, or Escape, or a click outside, closes the menu; Escape returns focus to the button. Following a link, or pressing Need help?, closes it. **Designer's call:** the plugin's assistant panel hands focus back to whatever opened it, which is inside the closed menu, so when that panel closes the theme moves focus to the Menu button (the theme watches the panel; no plugin change).
- The menu never holds a single item: with one item there is nothing to fold away, and in September the mentor coordinator found a menu that opened onto "Homepage" alone looked broken. A signed-in reader always has at least Homepage and Log out, so this holds; the builder keeps a guard for it anyway.

### Signed in, desktop width

Unchanged: today's single row of links.

### Signed out

- **Phones (640px and under):** one row: the brand and the Log in button. The separate Homepage link is not shown there, since the logo and the title already lead home. **Builder's note: designer's call, played back to the owner, who had nothing to correct.**
- **Above 640px:** unchanged (Homepage and Log in on the one row).
- No menu button at any width when signed out.

### Unchanged everywhere

The WordPress.org global header above the site header, the WordPress admin bar, the brand's look, the colors, every page's own layout below the header, the plugin's assistant panel, the dashboards' own toolbars.

### Settled by the designer after the builder's read (16 October 2026)

- **The account picture** is not focusable (a photo or initials), so drawing it beside the button while it sits last in the markup changes no tab order.
- **The narrowest phones (320px to about 360px):** the site title may take two lines rather than being cut or shrunk, so the brand stays whole; the closed bar may then be a little over 77px.
- **The navigation's items** are listed as the block renders them; a submenu, if one is ever added, shows its items expanded inside the menu (no nested toggles). Today the navigation holds one flat item.
- **No jump on load:** a one-line inline script in the page head marks the page as having JavaScript before it paints, and the one-row layout keys on that mark; without JavaScript the header keeps today's wrapped layout.

## 4. Behavior the build must keep

- **No sideways scroll** at any width from 320px up, open or closed.
- **Without JavaScript** (or before it runs) every link stays reachable: the header falls back to today's wrapped layout rather than hiding links behind a button that cannot open.
- **The header's measured height** (`assets/js/header-height.js` writes `--wpc-header-live`; anchored sections and sticky headings read it) stays right: the closed bar is one row, and opening the menu does not change what the rest of the page thinks the header height is (the menu drops over the page, under the bar).
- **One menu button only.** The core Navigation block in the header has its own responsive overlay (`overlayMenu: mobile`), which the theme currently keeps from opening a one-item menu (`wpc-nav--lone`). The release must never show two menu buttons on one bar.
- **Keyboard and screen readers:** the button is a real button with `aria-expanded` and `aria-controls`; the menu is a list of the same links (no duplicates left in the tab order while it is closed); focus is visible; forced-colors mode shows the button and the menu's lines.
- **Touch targets** at least 44px tall in the menu.

## 5. Goals, non-goals, anti-goals

- **Goal:** a signed-in header of one row, about 64px to 77px tall, from 320px up to desktop width, with every link one tap away under the menu button.
- **Non-goals:** the step cue (decision 3); the desktop header; the WordPress.org bars; the plugin's assistant panel; any change to which links a reader gets.
- **Anti-goals:** a menu holding a single link; two menu buttons; a Student Report Card or Mentor Report Card link a student or mentor can no longer find; a bar that jumps in height when the menu opens; any sideways scroll.

## 6. Constraints

- US English, plain hyphens only (never an em or en dash), full product names (Student Report Card, Mentor Report Card), "Administrators"; every new string translatable in `wpcredits-theme`.
- Version 1.24.20 in `style.css` and the readme, a changelog entry, the translation template regenerated; the zip built with `git archive`; the release through `~/Projects/wpcredits-release`, the live version read first, every outward step on the owner's yes.
- The theme's suites stay green (`bin/test-header.php` gains the new behavior; `bin/test-hub.php`, `bin/test-hub-js.php`, `bin/check-selectors.php` against the plugin).
- The owner's spacing rule: every gap of the changed header measured at each width before shipping.
- The repository's docs are mirrored publicly: this note names roles, never people.

## 7. Testing

- Widths 320, 375, 390, 414, 640, 641, 768, 820, 900, 1024, 1099, 1100, 1280; signed out; signed in as a student, a mentor, a student who is also a mentor, an Administrator, an institution member (no report card links: the menu holds Homepage, Need help? when available, Log out) and a sponsor member.
- Open, close (button, Escape, outside click, following a link, Need help?), focus order and return, VoiceOver's reading of the button and its state, JavaScript off, forced colors.
- A trial on a local copy of the live site signed in as each of those readers, with screenshots at 375, 820 and 1024.

## 8. Left to the builder

How the button and menu are made (the core Navigation block's overlay, or a small theme-owned disclosure around the existing links), the icon, the menu's exact look within the mockup's lines (white panel under the bar, 15px links, hairlines between them), and the exact width where desktop begins (measured: the signed-in row fits from about 1100px).
