# The Education Hub in the WordPress.org look (theme 1.24.18): design

17 October 2026. WPCredits theme release 1.24.18, built from the live 1.24.17; the header's menu button moves to 1.24.19. Theme only: the Education Hub template's patterns, the hub's blocks where they print markup, the hub stylesheet and the theme's font files. No plugin change, and no change to any other page or dashboard.

## 1. Why

The Education Hub (the template "Education Hub home", today on a private preview page) is the front door for three audiences: students, institutions and companies. Its words are settled (theme 1.24.15 to 1.24.17). Its look is still the dashboards' look: a tinted hero panel, rounded pills and system type. It reads like a tool, not like an invitation.

The owner's words: "I would love them to see an opportunity. It's not about learning how to build sites using WordPress. It about WordPress as a tool that unlocks their potential, a tool that the students will use in their future career no matter the direction they will head, the potential of the open source ecosystem, that they can learn from and grow with."

The model, in the owner's words: "This might be a good example to aim for https://wordpresscertifications.com/ design wise, https://wordpress.org/ itself."

## 2. The owner's decisions

1. **The look of the mockup, the words of today** (16 October 2026): "I like the visual but keep the content of the current version." So no new section, band or message: the "every career" band and the community section of the mockup are out.
2. **The trial is approved** (17 October 2026): a throwaway trial of the look on a local copy of the site, seen at desktop and phone width. "Everything else looks good."
3. **No icons above the outcome figures** (17 October 2026): the trial first drew an empty ring above each number; the owner: "they don't make sense". The figures row has no icons.
4. **The pathway's footnote goes** (17 October 2026): remove "Items marked planned are not offered yet." The "(planned)" mark beside each item stays, so each item still says it itself.
5. **Push it live** (17 October 2026): "I like what I see on http://localhost:8883/hub-look-trial/ push it live". The owner chose the fonts bundled in the theme, and the release deployed once its checks pass and it matches the trial.

## 3. What a reader sees

The visual language throughout: a serif for headings and numbers (EB Garamond, regular weight, the serif of wordpress.org and of the certification site), Inter for everything else, the brand blue (`brand`, #3858e9) for actions and accents, charcoal (`ink`) for text, white cards with 1px hairlines (`line`) and square corners (2px radius), light gray picture bands (#f6f6f6), no tinted panels, no rounded pills, no drop shadows.

### The hero

- No panel behind it: the hero sits on the page's white.
- The eyebrow ("Education programs from the WordPress project") as today, small capitals spaced out, in Inter.
- The headline in the serif at display size, four lines at desktop width: "Start learning with WordPress." then "Every step counts." then "Keep climbing." The last phrase is set in white on a hand-drawn blue brush stroke (an irregular rounded shape with a lighter streak through it, as on the certification site).
- The lede and the two buttons as today ("Find where to start", filled; "Explore the pathway", outlined), the buttons square-cornered.
- To the right of the copy, a **collage of WordPress editor pieces**, decorative only (hidden from screen readers, not focusable, not clickable): a block toolbar strip; a selected block "Step 3 of 7 / Contributor / Get your first contribution accepted." inside a dotted selection frame with corner handles; a card "Every step counts. / From first workshop to first job" with a rising line chart and one point marked; a badge "Contribution badge / On your WordPress.org profile" with the W mark; an "Aa" type card on the light brand tint (`brand-tint`); a small blue "+" inserter square. Every word in it is already on the page; together they say the page's message (a tool you grow with, step by step) without a new claim.
- From 900px wide the copy takes about half the row and the collage the rest; below 900px the collage is not shown.

### The outcome figures

- The three figures move out of the hero into their own row right under it, full content width, between two hairlines: three equal columns with hairlines between them, each the number in the serif (about 56px), its label in Inter, its base in small gray text. The source line ("As of ... counted by this site ...") follows across the full width, between hairlines.
- Phones: one figure per row, separated by hairlines, aligned with the text above (no inner indent).
- As today, each figure is the plugin's stored figure or nothing; with no figure to show, the row is not shown at all (and the source line goes with it).

### Sign in

The sign-in section (the plugin's sign-in block) keeps its words and cards; its heading takes the serif, its cards are white with hairlines and square corners.

### Who this is for (the three doors)

- Three white cards with hairlines. Each opens with a light gray picture band holding one small editor-style card in the door's own words, outlined in blue:
  - Students: the W badge with "Your first contribution" and "Earn credit for it".
  - Institutions: "Ready-to-run program" with two ticked lines, "One-day workshop" and "WordPress Credits pilot".
  - Companies: a drawn profile picture (a blue person shape on the brand tint, not a photo) with "Verified WordPress experience" and "Graduates open to work".
- The pictures are decorative (hidden from screen readers).
- Under the picture, the door's eyebrow, title (Inter, semibold), text and link exactly as today.

### Every education program, in one list

- The heading in the serif. The filter buttons square-cornered, the chosen one filled charcoal.
- The program cards white with hairlines, square corners; each program's name in the serif (about 26px); the status tags ("Live", "In development") and the step line as today, square-cornered.

### The courses band and the fields of study

- Both headings in the serif.
- Each course's name (a link) in the serif, about 26px, in the brand blue with its arrow; the cards white with hairlines.
- The fields-of-study table as today in the new type.

### The pathway

- The students and institutions switch: square-cornered buttons.
- The heading in the serif.
- The seven steps (and, for institutions, the four levels) are white cards with a hairline, rising in height as today. "Step 1" to "Step 7" in the serif in the brand blue (about 32px), the step's name in Inter semibold. The chosen step is filled with the brand blue, its words white.
- The step's detail panel: the step's name in the serif (about 34px), the rest as today.
- The prompt "Select a step to see what it unlocks." in a square, hairline box.
- **The line "Items marked planned are not offered yet." is removed** (decision 4).

### The closing calls

The two closing cards (companies; sponsors and schools) white with hairlines, their headings in the serif (about 30px), the buttons as above.

### Unchanged

Every word on the page (except the removed footnote, and the collage and door pictures, whose words repeat the page's own), every link, the order of the sections, every behavior (the filters, the steps, the switch, the detail panel, the anchors), the WordPress.org bars, the site header (the menu button release handles it), the footer, every other page, every dashboard, the plugin.

## 4. Behavior the build must keep

- **No sideways scroll** at any width from 320px.
- **The collage never cuts or covers its own words.** In the trial, between 900px and about 1200px the pieces overlapped and two were cut off ("Contributor", "Contribution badge"). **Designer's call:** the collage keeps its composition and scales with its column (or drops its smallest pieces) so that at every width where it shows, each piece is whole and no piece covers another's words.
- **Decorative means decorative:** the collage and the door pictures are hidden from screen readers, have no focusable or clickable parts, and are not read twice.
- **The headline reads as one heading,** in order, with the brush purely visual; in forced-colors mode "Keep climbing." stays readable (the brush may disappear).
- **Contrast:** white on the brand blue (headline and chosen step) is large or bold text; all other text keeps the theme's current contrast.
- **The figures:** the row shows only figures the plugin has stored, as today; the anchors (`#programs`, `#pathway`) and the sticky headings still land right under the header.
- **No layout shift on load:** the fonts load with the page, and the page reads fine in the fallback serif and sans while they do.

## 5. Goals, non-goals, anti-goals

- **Goal:** a student, a faculty member or a recruiter landing on the hub sees the WordPress project's own visual voice and an invitation to grow, before reading a word.
- **Non-goals:** new content or sections; photography; the site header (its menu button, 1.24.19); the dashboards' look; the pathway's step cue between 900px and 1180px ("fine for now" in the menu button's design).
- **Anti-goals:** any change of meaning in the words; a picture that reads as a real record (a real person, a real badge holder, a real figure); a collage that overlaps or clips; a request to a third party for fonts on every page view; a page that looks right only with JavaScript.

## 6. Constraints

- US English, plain hyphens only (never an em or en dash), full product names (Student Report Card, Mentor Report Card), "Administrators"; every string, the collage's and the pictures' included, translatable in `wpcredits-theme`.
- **Designer's call: the headline's "Every step counts. Keep climbing." becomes two translatable strings** ("Every step counts." and "Keep climbing.") so the brush can sit on the second; the translation template changes accordingly.
- **Designer's call: the collage and the door pictures are markup in the patterns, not built by script.** The trial built them with a script; the release must work without JavaScript like the rest of the hub.
- Colors from the theme's tokens where they match (`brand`, `brand-dark`, `brand-tint`, `ink`, `ink-70`, `ink-60`, `line`); any new value (the picture band's gray, the hairline gray) becomes a token.
- **The fonts (owner, 17 October 2026): bundled in the theme.** EB Garamond and Inter, Latin and Latin Extended, woff2, from the official Google Fonts builds, each with its SIL Open Font License beside it, declared in the hub's stylesheet so only the hub's pages load them. The WordPress.org bars ask for Inter by name, so on the hub's pages they draw in it too, as in the approved trial and on WordPress.org.
- Version 1.24.18 in `style.css` and the readme, a changelog entry, the translation template regenerated; the zip built with `git archive`; the release through `~/Projects/wpcredits-release`, the live version read first, every outward step on the owner's yes.
- The theme's suites stay green: `bin/test-hub.php` (the footnote's check goes, new checks for the moved figures row, the split headline strings and the decorative pictures), `bin/test-hub-js.php`, `bin/check-selectors.php` against the plugin.
- The owner's spacing rule: every gap of the moved and restyled blocks measured at each width before shipping.
- The repository's docs are mirrored publicly: this note names roles, never people.

## 7. Testing

- Widths 320, 375, 390, 414, 640, 768, 820, 900, 1024, 1180, 1199, 1200, 1280, 1440; signed out and signed in (an Administrator, a student, an institution member); the students and institutions views of the pathway; each program filter.
- With no stored figures: no figures row, no source line, no gap left behind.
- JavaScript off: the hero, the collage, the pictures and the figures all render.
- Forced colors; a screen reader pass of the hero and the doors (the decorative parts silent).
- A translation stress check: the longest strings at 150% length do not overflow the collage pieces or the picture cards.
- A trial on a local copy of the live site with screenshots at 375, 1024 and 1280, side by side with the approved trial's.

## 8. Left to the builder

How the brush is drawn (inline SVG or a background), how the collage is laid out and scaled, the exact type sizes at each width within the trial's proportions, the CSS organization within the hub stylesheet, and how the figures row is placed in the hero pattern.

## 9. Settled after the builder's read (17 October 2026)

- **The fonts:** bundled (the owner's choice; section 6).
- **The release number:** this release is 1.24.18 from the live 1.24.17; the header's menu button follows as 1.24.19 on top of it (the owner chose "deploy when it matches").
- **Designer's call, the phone pathway:** below 900px each step keeps the brand bar along its foot that widens with the climb, now on the step's white; it is the one picture of the climb on a phone.
- **Designer's call, text links:** the doors', the sign-in cards' and the program cards' links are underlined at weight 500, as in the approved trial and on WordPress.org.
- **Designer's call, control borders:** the filters and the switch keep the theme's control border (3.32:1, a control's boundary) rather than the trial's lighter gray, which was under 3:1.
- **Designer's call, scope:** every rule names the hub's own classes, so a block another plugin places on the hub keeps its own look.
