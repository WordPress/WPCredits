# One set of interface parts (design, 13 November 2026)

Plugin: WPCredits Program Manager. The build starts from main at 1.123.90 (the site emails a mentor when a
student is assigned); the branch merges main before release 1. Lines below were read at 1.123.89, and
1.123.90 changed none of the files this document cites, so they hold at 1.123.90. Theme: WPCredits theme
1.32.27. Brand: `DESIGN.md` (WordPress.org Education), unchanged.

Sources, in the order they win when they disagree: the controller's rulings on this document (marked
"controller's ruling" where they settle a question), the controller's fix-round rulings (sections A to I, with
the late rules of the design brief), then the prototype builder's latest report (round 3, then round 2, then
round 1), then the prototype's own spec, then the independent critique. The prototype (one stylesheet and one
script laid over ten real screens on the local copy) is the reference for exact values; it is not the code to
ship. Every file and line below was read at 1.123.89; where a report gave a line that has moved, the line here
is the one at the branch head.

## 1. Why

Every screen built its own buttons, folds, forms, tables and lists. Ten button looks, eight kinds of fold,
fields of seven heights and gaps off the rhythm in 69 places on the front end and 83 in wp-admin were measured
on the screens the owner marked as broken. A fix was always local, so the same defects came back on the next
screen. This build replaces them with one set of shared parts, printed by a few PHP helpers and drawn by one
stylesheet, and moves the screens onto them in four releases. The owner approved the look on real screens
("All looks good", with one button since fixed).

## 2. The parts

Values are CSS pixels. "Front end" is every dashboard page; "wp-admin" is the plugin's own screens, where the
parts take WordPress's idiom. Rhythm, from `DESIGN.md`: 14px between fields, 16px between blocks of a card,
24px between two blocks that are not each other's, 22px between modules, 10px between folds, 6px from a label
to its box and from a box to its hint, 12px between buttons in a row.

### 2.1 Button

- **Four variants and nothing else.**
  - Primary: Blueberry `#3858e9` fill and border, white words. Hover and pressed: Deep Blueberry `#213fd4`.
  - Secondary: white fill, Blueberry 1px border and words. Hover: Deep Blueberry fill, white words
    (`DESIGN.md` `button-secondary-hover`; this replaces the frame's tint hover, `dashboard-frame.css:748-751`,
    on a surface that is on: section 3.1).
  - Danger: white fill, red `#cc1818` 1px border and words; hover the red tint `#fff6f5`. Never filled.
  - Quiet: words only, Blueberry, no border, transparent; hover Deep Blueberry and an underline 3px under the
    words. Red when destructive (the hover stays red).
- **Size, front end, every variant:** min-height 40px, padding 9px 16px, font 600 14px/20px in the page's
  face, 2px corners, 6px gap to an icon, `white-space: nowrap` (a label never wraps). Quiet: the same 40px box,
  padding 10px 8px.
- **Size, wp-admin, every variant:** WordPress's own `.button` and `.button-primary` as WordPress 7 draws them
  (40px, 500 13px, 2px corners). The danger and quiet looks take the same 500 13px. Every secondary button has
  a white fill (WordPress draws it transparent on the grey page), except while pressed.
- **States:** hover as above; `:focus-visible` a 2px solid Blueberry outline 2px off the box, on every
  interactive element; disabled at 55% opacity with the not-allowed cursor; busy is the plugin's own
  `data-wpcpm-busy` label swap (`assets/js/forms.js`), unchanged.
- **Pressed** (an opener whose panel or row form is open, `aria-expanded="true"`): secondary fills with
  `#eff2ff`, danger with `#fff6f5`, border unchanged; a primary opener pressed is Deep Blueberry (a tint under
  white words was unreadable). A pressed quiet action takes the tint of its color and 8px of side padding, so
  the tint is not tight to the word (late rule), with an equal negative side margin so the word does not move.
- **The row action rule.** In a list row or a table row the action is the quiet button, with no side padding
  at rest so its words line up with the row's text (at the row's right edge at desktop widths, at the left on a
  phone). Destructive row actions are quiet red. Cards and forms keep bordered buttons.
- **Which variant.** One primary per card or form. Anything that removes, rejects, declines, revokes,
  withdraws, leaves, ends, deletes, cancels an invitation or marks as spam is danger, never primary. Cancel in
  a panel is quiet.

### 2.2 Status tag and topic chip

- **Status tag:** the frame's `.wpcpm-status`, one look everywhere: 12px 500, line 18px, uppercase, 0.06em
  tracking, padding 1px 8px, 2px corners, 1px border, 22px high, never wraps. Four tones only:
  - neutral: `#f4f4f4` fill, `#e6e6e6` border, `#40464d` words;
  - red: `#fff6f5`, `#daa39b`, `#cc1818`;
  - amber: `#fff7e1`, `#d0b481`, `#926300`;
  - green: `#ebffed`, `#8ac894` (green gains its border; the frame had none, `dashboard-frame.css:784-787`),
    `#008030`.
  The neutral tag's border and the green tag's border are new looks of an existing class, so they apply only
  on a surface that is on (section 3.1).
  It replaces bare state words ("New", "Draft"), colored running text, round bordered pills, the 11px amber
  mark (`admin.css:631-642`) and grey code boxes.
- **Topic chip** (the mentor card's areas): `DESIGN.md` `chip`, Blueberry tint `#eff2ff` fill, ink words,
  12px 500, line 20px, padding 1px 10px, fully round, no border. A "+N" chip is the same shape on `#f6f6f6`
  with `#40464d` words.
- **Count chip** (filters and the Pipeline's stage headings only; a fold head says its count in words):
  `#f6f6f6` fill, 1px `#e6e6e6` border, fully round, 12px 500, 20px high, min-width 20px, padding 0 6px, 8px
  after its label.

### 2.3 Fold

One grammar at two sizes. Nothing in a head is placed absolutely and nothing hangs under the box.

- **Card fold:** a 1px `#e6e6e6` box, 2px corners, white, no shadow.
  - **Head:** the same height open and closed, min-height 56px, padding 8px 16px (a 56px head holding a 40px
    control), one row in normal flow, gap 12px.
  - **What a head holds, in order:** a lead group (title; then the phrase that says what the card holds), then
    an end group (the status tag; the cue; the help link "How to do this"), then ONE chevron at the far right.
  - Title: 600 13px/20px, uppercase, 0.06em, ink. Phrase: 400 14px/20px, muted `#656a71`.
  - **The count is said once, in words.** A count chip that repeats the phrase's number is not printed; a head
    that has only a count gets a phrase instead ("2 students", "1 person").
  - **The cue** ("Review"): words inside the toggle, drawn as the secondary button (min-width 84px), shown
    while the card is folded. Open, no cue is shown and its place stays, so nothing in the head moves; the
    chevron carries the state. "Close" is never a cue. Hovering the head gives the cue the secondary hover.
  - **The help link:** 400 14px/20px, muted, underlined in `#e4e4e4` 3px under the words, Blueberry on hover,
    in a 104px slot, right-aligned. A head with no help link keeps the empty slot, so every cue lines up.
  - **Chevron:** 20px square, `#40464d`, pointing down while folded and up while open (a 150ms turn, none
    under `prefers-reduced-motion`).
  - **Open:** a hairline under the head (drawn as the body's top edge, so the head does not grow by 1px), the
    body inset 16px with 20px above and below. The body's first block has no space above it and its last none
    below; its blocks are 16px apart.
  - **Spacing:** 10px between sibling card folds, 22px from a group of folds to the next module.
- **A row that does not open** (the Overview's Mentor applications): exactly the same box and head, with its
  waiting tag, its help link, a real "Review" link drawn as the secondary button at the cues' width, and in the
  chevron's place the frame's external-link icon (20px), because the row leaves for wp-admin.
- **Phone (600px wide and under):** every card head is two lines, the same on every row. Line 1: the title,
  and the chevron (or the external-link icon) at the right. Line 2: the phrase, then the tag. Padding 12px
  16px, gap 6px 12px. No cue and no help link in the head: "How to do this" is the first line inside the open
  card, 16px over the next block. On the row that does not open, the whole row is the press. Measured: every
  head 97px at 390.
- **Inner fold** (a fold inside a card or a section, and every small `details`: Read the application,
  Consent, Answers and consent, the Pipeline's folded stages, the student rows, "Correct these", Details on
  Settings when it moves): the same grammar one size smaller.
  - No box: a hairline above, and below the last of a run.
  - Head: min-height 44px, padding 12px 0, title 600 14px/20px in sentence case (13px in wp-admin), gap 12px,
    the chevron at the far right; Blueberry on hover.
  - Body: 12px under the head, 16px under the body. 16px between a run of inner folds and the blocks around
    it. A fold that follows a table or another hairline draws no second hairline.
  - Phone: the head is a two-column grid, the text column (title, and a second piece stacked under it, 2px
    apart) and a fixed 20px chevron column pinned at the top right.

### 2.4 Field

- **Label** above the box, 600 14px/20px ink (13px in wp-admin); "Required" or "Optional" in words after it,
  regular weight, muted, 6px away. A label never sits on the line of its box (the one exception: the
  Administrators-only "Viewing as" toolbar) and is never closer to a button above than to its own box.
- **Box:** 40px high, 1px `#8d8d8d` border, 2px corners, padding 9px 12px, white, 400 14px/20px in the page's
  face (never monospace). Hover: border `#40464d`. Focus: border Blueberry and the focus ring.
- **Widths, one rule on both sides of the site:**
  - single-line text, email and address boxes: the container's width, at most 640px;
  - a select: as wide as its longest option, at least 200px, at most 320px; a select marked long, at most
    640px (fix-round G8; the spec's "at most 640px" for every select is overruled), with its own chevron 10px
    from the right and 36px of right padding;
  - a date or a number: 200px;
  - a text area: the card's width, at least 96px high, resizable up and down.
- **Hint** under the box, 6px away, 400 14px/20px muted (13px in wp-admin), tied to the box with
  `aria-describedby`. 14px to the next field.
- **A label sentence is split:** where today's label is a sentence that also says what happens to the words,
  its first clause is the label and the rest the hint (section 5 lists each). A placeholder that repeats the
  label or the hint is not printed.
- **Choice group:** a `fieldset` with no border; its legend is the field's label (6px over the first
  option). Each option: a 16px radio or box and its words beside it, the words with a hanging indent (a
  second line starts under the first word, not under the box), line 20px, 8px between options; an option's
  own hint muted under its words. A checkbox that is its own question is its own field, 14px from the group.
  In a grid of choices a two-line name does not push its row's other items down.
- **File box:** the secondary button "Choose a file" and the chosen file's name beside it (muted 14px, "No
  file chosen" until one is), 12px apart; the browser's own control stays in the form, visually hidden, so its
  label, its required check and the upload work as today.

### 2.5 Action row

`display: flex; flex-wrap: wrap; gap: 12px`, left-aligned, 16px under the last field. Order: the primary,
then secondary, then danger, then the quiet Cancel. Under a button, only that button's own consequence, as a
muted 14px hint 6px under it (or beside it). Any other paragraph that sat under a button moves above its form
as the form's intro (ink `#40464d`, 14px/20px; 13px in wp-admin), 8px over the form. A note that introduces
what follows takes 24px above and 8px below.

### 2.6 The Decide area

Where one item offers several decisions (an institution application, a signed agreement, a school's request,
a sponsor's post, a mentor application, a student interest):

- One heading "Decide": 600 16px/22px (14px/20px in wp-admin), 24px above it, 12px under it. No heading
  above it repeats it.
- ONE action row holding every decision as a button: the forward act primary, a question or a return
  secondary, the destructive acts danger.
- **A decision that needs no note acts at once**, with the browser question it asks today
  (`data-wpcpm-confirm`).
- **A decision that needs a note** is an opener (`aria-expanded`, `aria-controls`). Pressed, its panel opens
  under the row: 16px from the row to the panel's hairline (1px `#e6e6e6`), 16px from the hairline to the
  label, then the field (label, box, hint), then the panel's own action row 16px under it: the confirming
  button and the quiet Cancel. The opener shows the pressed state while its panel is open.
- **Only one panel is open at a time.** Opening one closes the other; focus moves to the panel's first field;
  Cancel closes it and puts focus back on its opener.
- **The confirming button names the act in full** where its own label would only repeat the opener's word
  ("Reject the application"); a form that already has its own label keeps it ("Send this question").
- **Every panel's hint says who receives the note**, or that nobody does.
- A school's request: Mark as handled (primary) and Decline (danger) in the row; either opens the one optional
  note, with that decision's confirming button and Cancel.
- A link that decides nothing (Preview) is not in the row: it joins the item's facts ("Preview the post").
- No form is open before it is asked for, no button sits beside another form's label, and no two decisions
  are stacked with rules between them.
- In wp-admin, 16px from the Decide row (or an open panel's action row) to the item box's bottom edge.

### 2.7 Table

- One look: full width, collapsed borders, no outer border, no fill, no stripes. Cells 12px padding,
  400 14px/20px ink, top-aligned, a soft `#ededed` hairline under each row. Header: 600 12px/18px muted, a
  `#e6e6e6` hairline under it, never wraps. In wp-admin cells are 13px.
- Inside a card the outer cells have no padding at the table's edges, so its text lines up with the card's.
  Outside a card the padding stays (fix-round B1).
- Columns of the same kind start at the same place in consecutive tables of one family: a `colgroup` per
  table family (the Pipeline's nine stage tables).
- Numbers right-aligned in tabular figures; numbers, dates, ages and other short cells never wrap; a reference
  such as `MAPP-2026-1922` never breaks. An action column has a header cell "Action" for screen readers only;
  its cell is right-aligned, vertically centered, padding 2px above and below, holding the quiet row action.
- **Key and value:** one label column, 320px wide above 600px, labels 600 muted `#656a71`; an empty value
  keeps its words ("no answer", "Not recorded", "Not given yet") and is muted; a real value is ink. The table
  ends with one hairline, and none of its own when it is the last thing in a fold.
- **Phone (600px and under):** a key and value table stacks label over value; a wide table becomes one block
  per row, each value under its column's name (`data-label`); the action cell is the row's last line, its
  quiet action at its natural width on the left; number cells read from the left. Nothing runs out of its card
  and no page scrolls sideways.

### 2.8 List row with a state and an action

The Sponsors Agreements tab, the member and invitation rows, and the same kind of content elsewhere.

- A list with a hairline over it; each row a soft hairline under it.
- The row's line: a grid of the name (600 14px/20px, `1fr`), the state as a status tag (160px), the facts
  (muted, tabular figures, `1fr`), and the action (200px, at the right); min-height 56px, padding 8px 0, gap
  8px 16px.
- One action per row, the quiet button. An action that needs fields opens the row's form directly under the
  row, one row at a time, its opener pressed: a `#f6f6f6` band, a soft hairline above it, padding 16px; the
  shared fields, then its action row (the confirming button and Cancel). In such a form a date box is 200px
  wide (late rule). An action with no fields acts at once with its browser question.
- People rows: line 1 the name with its tag; the facts under it (a role such as "Representative" is muted text
  in the facts, not a tag); the action at the row's end. Two actions on one row are 16px apart.
- Phone (782px and under in wp-admin, 600px and under on the front end): line 1 the name with its tag at the
  right; the facts under; then the action, left, at its natural width (two actions on one line). A facts cell
  with nothing in it takes no line.
- When a filter or search leaves no row: one muted line ("No company matches.").

### 2.9 Filter with counts, and the search field

- **Filter:** links (or, where the list filters in place, buttons with `aria-pressed`) with a count chip each,
  4px apart, left-aligned, no pipes. Each: 500 13px/20px Blueberry, min-height 32px, padding 5px 10px, a
  transparent 1px border, 2px corners, underlined on hover. The selected one: `#eff2ff` fill,
  `#c7d1ff` border, ink words, 600.
- **Search field:** the field part, label over the box, 320px wide; a button beside it, 12px away, only where
  the search needs a submit.
- **The bar:** the filter and the search on one row, the search at the right, bottoms aligned, gap 12px 24px,
  16px above and 12px under. A filter keeps to one line: when it does not fit beside its search, the search
  goes under it rather than the filter wrapping into two lines beside it (late rule; this replaces the
  prototype's correction that let the filter wrap beside the search). A filter wider than the whole bar wraps
  on its own lines. At 782px and under the search is under the filter at full width.

### 2.10 Item header

An application, an agreement, a request, a post, a mentor application, an interest:

- Line 1: the name, 600 16px/22px (a link where it is one), then the reference in IBM Plex Mono 400
  13px/20px, muted, no box; 4px 12px gap, wrapping.
- Line 2, 4px under: the status tag if there is one, then the facts, 400 14px/20px muted, separated by middots
  in `#979aa1`. No middot follows the tag, and none starts or ends a line: each fact draws its own before it
  and the line's first is clipped away.
- No heading above it ("Awaiting review" becomes the tag). In wp-admin the facts line is 16px over the next
  block.

### 2.11 Headings

- **In-card heading:** 600 16px/22px ink, sentence case, 24px above and 8px below (14px/20px in wp-admin).
  The first heading in a card or a panel takes no room above it (late rule).
- **Section title** (the Institution Dashboard's Students, Semester report, Representatives and agreement):
  one level above a card fold's title, 600 20px/26px ink, sentence case. Where a section holds one fold whose
  title repeats the section's, that fold prints no head and its body is the section's content.
- A heading that repeats its card's phrase with nothing under it is not printed ("Friend" on Your
  sponsorship).

### 2.12 Mentor card and its phone row

- A grid of `repeat( auto-fill, minmax( 220px, 1fr ) )`, 16px gap. Every card the same size, six fixed lines
  on a subgrid so each line sits at the same height across a row: photo or initials, name, students, tags,
  profile link, button. An empty line keeps its height.
- Card: 1px `#e6e6e6` box, 2px corners, white, padding 20px 16px 16px, 8px between lines, centered.
- Photo: 72px round; with no photo, the initials (600 22px, Deep Blueberry on the tint). Name 600 16px/22px
  ink; students 400 14px/20px muted; tags: topic chips, 6px apart, as many as fit two lines (up to six short
  ones at 1280; one line on a phone), then "+N"; profile link
  "WordPress.org profile" Blueberry 14px, underlined; the button, secondary, full width, one line: "Sponsor
  this mentor".
- Phone (600px and under): one compact row per mentor: the 48px photo left; name, students, one line of tags
  and the "WordPress.org profile" link right, each on its own line; the full-width button under.

### 2.13 Small parts kept with their one screen

- **Section movers** (Institution Dashboard): bordered 32px squares, 1px `#8d8d8d`, 2px corners, a 16px up or
  down arrow in `#40464d`, never the fold's chevron; hover `#f6f6f6`; disabled at 40%; 8px apart. They live in
  `institution.css` under a class of their own (`wpcpm-institution__mover`), and stay as long as the sections
  are movable (the Institution Dashboard's move into the frame is not in this build).
- **"Viewing as" toolbar** (Administrators only): the label beside the select; "Show" is the secondary button.

## 3. Where the parts live in code

### 3.1 One stylesheet: `assets/css/parts.css`, handle `wpcpm-parts`

- **Front end:** registered in `WPCPM_Mentors_Dashboard::register_assets()`
  (`includes/modules/class-wpcpm-mentors-dashboard.php`, beside `dashboard.css`) and made a dependency of
  `dashboard.css` (`WPCPM_Mentors_Dashboard::STYLE`). Every dashboard's sheet already depends on
  `dashboard.css`, so every dashboard loads the parts first and its own sheet after. `dashboard-frame.css`
  (`WPCPM_Dashboard_Frame::register_assets()`, `includes/class-wpcpm-dashboard-frame.php:51-59`) depends on
  it too.
- **wp-admin:** enqueued in `WPCPM_Admin::enqueue_assets()` (`includes/class-wpcpm-admin.php:201-235`) as a
  dependency of `wpcpm-admin` (`admin.css`), on the plugin's screens only.
- **What it styles:** only the parts' own classes (section 3.4) and the frame's button and tag it takes over
  (below), never an element by type and never another legacy
  class. So it changes nothing on a screen that prints none of them (the Mentor and Student Report Cards load
  it and look as before).
- **Tokens:** the frame's tokens (`dashboard-frame.css:14-39`, `--wpcpm-f-*`, each falling back to the
  theme's `--wpc-*`) move into `parts.css` on `:where( .wpcpm-frame, .wpcpm-dashboard, .wpcpm-wrap )`, so the
  Institution Dashboard outside the frame and wp-admin have them too. `dashboard-frame.css` keeps using them
  and declares none of its own. Added beside them: `--wpcpm-f-green-border: var( --wpc-green-border,
  #8ac894 )`, `--wpcpm-f-neutral: #f6f6f6`, `--wpcpm-f-line-card: #e4e4e4`, `--wpcpm-f-primary-hover:
  var( --wpc-brand-dark, #213fd4 )` and the rhythm (`--wpcpm-f-field: 14px`, `--wpcpm-f-block: 16px`,
  `--wpcpm-f-apart: 24px`, `--wpcpm-f-module: 22px`, `--wpcpm-f-fold: 10px`, `--wpcpm-f-tie: 6px`,
  `--wpcpm-f-gap: 12px`).
- **The face:** the front end uses `--wpcpm-f-sans` (Inter, as the frame does); in wp-admin every part uses
  `font-family: inherit`, WordPress's own stack.
- **The frame's button and tag move here, unchanged:** `.wpcpm-btn` and `.wpcpm-btn--secondary`
  (`dashboard-frame.css:723-751`) and `.wpcpm-status` with its tones (`dashboard-frame.css:753-787`) are cut
  from the frame's sheet into `parts.css` value for value. Every rule that changes how one of these existing
  shared classes looks (the neutral tag's `#e6e6e6` border, the green tag's border, the secondary button's
  Deep Blueberry hover, the new variants' interplay with the old ones) is scoped under `.wpcpm-ui-on`, the
  class a surface's root carries while its switch is on (section 3.5; controller's ruling O). So the Sponsor
  Dashboard's tags and buttons do not change in release 1, not even on hover. The parts' own `wpcpm-ui-*`
  classes need no such scope: only a surface that is on prints them.
- **wp-admin's button metrics are respected:** in wp-admin the helpers print WordPress's `.button` and
  `.button-primary` and add only `wpcpm-btn--danger` or `wpcpm-btn--quiet`; `parts.css` scopes those two
  modifiers, the white secondary fill and the pressed tints to `.wpcpm-wrap .button`, and sets nothing else
  of WordPress's (height, padding, weight and size stay WordPress's).
- **Breakpoints (controller's ruling Q):** in wp-admin every part switches at WordPress's own 782px (the
  Pipeline's stages close at 782px or less); on the front end the phone rules switch at 600px and the frame's
  own breakpoints (its 1,100px and 782px widths, its container queries) are unchanged. Section 8 says which
  pictures cover which side of each.
- **One `!important`, none else:** `[hidden] { display: none !important; }` inside the parts, since the parts
  set `display`. No doubled classes: the legacy rules the prototype fought are deleted (section 6).

### 3.2 One small script: `assets/js/parts.js`, handle `wpcpm-parts`

Registered once, in the footer, depending on nothing (as `WPCPM_Dashboard_Card::enqueue_opener()` registers
the opener), on both sides of the site. Enqueued by the helpers that print something it drives, so a page
loads it only when it needs it. It does these things and nothing else:

1. **Decide panels and row forms:** an opener shows its panel (or row form) and hides any other in the same
   Decide area or list; sets `aria-expanded`; moves focus to the first field; Cancel, and Escape inside the
   panel, close it and put focus back on the opener. On a request, the opener pressed shows only its own
   confirming button in the shared panel.
2. **The in-place filter and search** of a list (Agreements): the filter buttons set `aria-pressed` and show
   the rows of that state; the search narrows by name as the reader types; "No company matches." when none
   is left.
3. **The file box:** "Choose a file" opens the hidden browser control; the name beside it follows the choice
   (`aria-live="polite"`).
4. **The tags that fit** (the mentor card, controller's ruling): in a block marked `data-wpcpm-fit`, whose
   height is fixed in CSS to two lines (one on a phone), it hides the chips that do not fit and adds the "+N"
   chip, its accessible name and `title` carrying the full list. The words come from PHP as data attributes
   (`data-wpcpm-more`, `data-wpcpm-more-label`), so they are translated. It runs again when the width
   crosses 600px.
5. **The Pipeline on a narrow screen** (controller's rulings H and Q): at 782px wide or less, wp-admin's
   breakpoint, on load, it closes every stage's inner fold but the first.

It never posts, fetches or stores anything.

**Without it nothing is lost:** the server prints every panel and row form open, and the openers, the Cancel
buttons and the in-place filter bar with `hidden`. The script, on load, reveals the openers and Cancels and
folds the panels and row forms, except one that holds kept typing or a refusal (`WPCPM_Typed_Text`,
`WPCPM_Form_Errors`), which stays open with its opener pressed. With no script every form is on the page,
every button posts, and the browser's own file control shows; a tag block clips at its height and shows no
"+N"; the Pipeline's stages stay open, as today. A JS test beside the others in `bin/js/` covers it.

### 3.3 The PHP helpers: `WPCPM_UI` (`includes/class-wpcpm-ui.php`)

Static methods that return escaped markup (callers echo with the plugin's usual
`phpcs:ignore ... Escaped in WPCPM_UI` note). Each picks the front-end or the wp-admin markup by `is_admin()`,
unless the `side` argument (`'front'` or `'admin'`) says otherwise. Behavior hooks stay data attributes the
plugin already reads (`data-wpcpm-once`, `data-wpcpm-busy`, `data-wpcpm-confirm`).

- **`button( array $args )`**: `label`; `kind` (`primary`, `secondary`, `danger`, `quiet`, `quiet-danger`);
  `type` (`submit` or `button`); `href` (prints an `a`); `name`, `value`, `form`; `confirm` (the browser
  question); `controls` (an opener: prints `aria-controls` and `aria-expanded`); `expanded`; `context` (words
  for a screen reader after the label, inside the control); `aria_label`; `hidden`; `side`.
- **`field( array $args )`**: `id`, `name`, `label`, `type` (`text`, `email`, `url`, `search`, `date`,
  `number`, `textarea`, `select`), `value`, `options` (a select's), `long` (a select up to 640px), `need`
  (`required`, `optional` or ''), `hint`, `rows`, `attrs` (other attributes: `minlength`, `maxlength`,
  `placeholder`, `autocomplete`), `problem` (a refusal's key, passed to `WPCPM_Form_Errors::attrs()` and
  `::problem()`), `required_attr` (false: Required in words with no `required` on the box, which then carries
  `aria-required="true"`). Prints the wrapper, the label with one space and its word, the box at its width and the
  hint with its id.
- **`choices( array $args )`**: `id`, `legend`, `name`, `type` (`radio` or `checkbox`), `options` (value to label,
  or to label and hint), `checked`, `need`; a fieldset with its legend, or one checkbox alone as its own question;
  `required` on a radio group's inputs and on a checkbox alone, in words only on a group of checkboxes.
- **`file( array $args )`**: `id`, `name`, `label`, `need`, `hint`, `accept`.
- **`actions( array $buttons, $consequence = '' )`**: the action row from `button()` markup, with the
  button's own consequence as the 6px hint.
- **`fold_head( array $args )`**: the head's inner markup for both sizes: `size` (`card` or `inner`), `title`,
  `title_id`, `phrase`, `count` (printed only when there is no phrase), `tag` (words and tone), `cue`, `help`
  (`WPCPM_Guide_Jobs::link()` markup), `leaves` (the external-link icon in the chevron's place), `second` (an
  inner fold's second piece). `WPCPM_Dashboard_Card::open()` calls it; so does every inner fold through
  `inner_open( array $args )` and `inner_close()`.
- **`decide( array $args )`**: `id` (the base for the panels' ids), `title` (default "Decide"), `level`, `context`
  (plain text naming the item, for a screen reader on every opener and every at-once submit), `decisions`,
  each: `key`, `label` (the opener or the button), `kind`, `hidden` (a callable that prints the form's hidden
  inputs: the nonce, the action, the return field), `fields` (markup from `field()`, '' for none),
  `confirm_label` (the act in full, for the panel's button), `confirm` (the browser question), `open` (true
  when the panel holds kept typing or a refusal), `panel` (the key of a shared panel, for a request's two
  openers), `cancel_label` (the panel's dismissing button, default "Cancel"). The row is printed primary, then
  secondary, then danger, whatever the caller's order. Enqueues the script when any decision has a panel.
- **`item_header( array $args )`**: `name`, `href`, `mark` (the test institution's mark, already escaped),
  `ref`, `tag` (words and tone), `facts` (a list of text, or of `array( 'html' => ... )` for a link already
  escaped), `level` (`h3` or `h4`).
- **`filter( array $args )`**: `label` (the group's name for a screen reader, "Show"), `items` (each `key`,
  `label`, `count`, `href` for a link or none for an in-place button, `current`), `in_place`.
- **`search( array $args )`**: `id`, `name`, `label`, `value`, `button` (a label, or '' for none), `in_place`.
- **`tag( $words, $tone = '' )`**, **`chip( $words )`**, **`heading( $text, $level = 'h4', $id = '' )`**.
- **`intro( $text, $tone = '', $id = '', $html = '' )`**: one plain line; `tone` `muted`; an `id`; `html` a line the
  caller escaped (a sentence with its link) in place of the text.
- **`note( $text )`**: a note a person typed, one paragraph, its line breaks kept.
- **`checklist( array $lines )`**: sentences to read, not to tick, one plain list.
- **`item_open( $names, $id = '' )`**: an item's article, its own classes and anchor, then the part's class.
- **`help_link( $link )`**: the card's "How to do this" link with the fold's help class added.
- **`fold_foot( $href, $label, $context = '' )`**: the line a card's body ends with, the way to its home.
- **`table_open( array $columns, array $args )`**, **`cell( $column, $html, $kind )`**, **`table_close()`**:
  the colgroup, the header (with the hidden "Action" name), `data-label` and the cell kinds (`num`, `date`,
  `short`, `empty`, `action`); `args` says `keyvalue`, `caption`, `labelledby`, `end` (the table ends its card
  though it is not the body's last child), `wide` (seven columns or more, stacked while the main area is narrow)
  and `fixed` (a family's table, laid out by its columns' widths). A column with `'header' => true` prints a key and
  value table's row header; `'header' => 'row'` a wide table's row header (`wpcpm-ui-cell--rowhead`).
- **`cell_sub( $text )`**: a cell's muted second line, after one space.
- **`list_open( array $args = array() )`**, **`list_close()`**, **`list_empty( $text )`**: `facts` `'under'` for a
  list whose rows carry no tag (the people rows of 2.8: the facts under the name, the action across both lines).
- **`row( array $args )`**: one list row: `name`, `href`, `mark`, `tag`, `facts`, `actions` (from `button()`),
  `note` (plain text under the row's line), `media` (a 40px box at the row's start), `level` (the name inside an
  `h4` or `h5`), `id`, `form` and `form_id` (the row form's markup, printed under the row with its id). The
  in-place filter's `state` and `search_name` are not built in release 1 (the Agreements tab moves in release 2).

**Reused, not rebuilt:** `WPCPM_Dashboard_Card` stays the one shell of a card fold: `open()`
(`includes/class-wpcpm-dashboard-card.php:461-552`) prints its head through `fold_head()`, and `close()`
(`:573-602`) no longer prints the help link after `</details>` (section 4.1); both take the parts' branch
by the surface switch (section 3.5), not by an argument. `WPCPM_Dashboard_Frame` stays
the frame; `WPCPM_Icons::svg()` gives the chevron (`chevron-down`) and the external-link icon (`external`);
`WPCPM_Guide_Jobs::link()` gives the help link; `WPCPM_Dashboards::render_combo()` stays the one combobox.

### 3.4 Class names

The button keeps the frame's `wpcpm-btn` (with `--secondary`, `--danger`, `--quiet`) and the tag keeps
`wpcpm-status` (with `--red`, `--amber`, `--green`). Every other part is new under `wpcpm-ui-`:
`wpcpm-ui-fold` (`--inner`, `--row`, `__head`, `__lead`, `__title`, `__phrase`, `__end`, `__cue`, `__help`,
`__chev`, `__ext`, `__body`), `wpcpm-ui-field` (`__label`, `__need`, `__hint`, `__control`), `wpcpm-ui-choices`,
`wpcpm-ui-choice`, `wpcpm-ui-file`, `wpcpm-ui-actions`, `wpcpm-ui-consequence`, `wpcpm-ui-intro`,
`wpcpm-ui-decide` (its heading is the shared `wpcpm-ui-h`), `wpcpm-ui-panel`, `wpcpm-ui-table` (`--keyvalue`), `wpcpm-ui-list` (`__row`,
`__line`, `__name`, `__facts`, `__act`, `__form`, `__empty`), `wpcpm-ui-filter`, `wpcpm-ui-count`,
`wpcpm-ui-search`, `wpcpm-ui-item` (the item's article, section 13; `__title`, `__ref`, `__facts`, `__fact`), `wpcpm-ui-note` (section 13), `wpcpm-ui-checklist` (`__item`, section 13), `wpcpm-ui-h`,
`wpcpm-ui-section-title`, `wpcpm-ui-chip`. A moved screen drops its legacy layout classes; anchors (ids) and
the data attributes stay.

### 3.5 One screen changes whole: the surface switch (controller's rulings A, K, M, O)

No tab of wp-admin and no block of a dashboard shows the new parts while the rest of its screen still shows
the old ones on the live site. Every block on a moved screen is converted in that screen's release. A renderer
shared between surfaces (a decision drawn on the Overview and on a wp-admin tab, a member row drawn in
wp-admin and on the Institution Dashboard, a block a Report Card prints too) keeps printing its old markup for
a surface until that surface's release.

- **The surfaces, one switch each, all in `WPCPM_UI`:**
  - `administrator`: the Administrator Dashboard, every view (Overview, Watch, The program, the cohort
    timeline). On in release 1.
  - each wp-admin page and tab, named `<page slug>/<tab>` (for example `wpcpm-institutions/pipeline`): the
    switch in wp-admin is per tab, not one for all of wp-admin (ruling M). Release 2 turns on exactly the
    tabs section 7 lists; every other tab stays off in this build.
  - `sponsor`: the Sponsor Dashboard, every view. On in release 3.
  - `institution`: the Institution Dashboard. On in release 4.
  - `report-cards`: the Mentor and Student Report Cards. Off for the whole of this build (ruling K).
- **The switches are one constant**, `WPCPM_UI::LIVE`, the list of the surfaces and tabs that are on; a release
  adds its own to it.
- **Which surface is drawing:** the surface a screen named when its render began: `WPCPM_UI::enter( 'administrator' )`
  at the top of the Administrator Dashboard's render (and so on for each dashboard and both Report Cards), and
  in wp-admin each module screen calls `WPCPM_UI::enter( $page . '/' . $tab )` once it has resolved its
  current tab, before it prints the tab; `WPCPM_UI::leave()` at the end. Anywhere that names none, nothing is
  on.
- **How a renderer asks:** `if ( WPCPM_UI::on() )` prints the parts' markup, else the old markup, unchanged.
  Nothing is passed through renderers; no renderer takes a "look" argument. The helpers in 3.3 are only
  called inside the parts' branch. A renderer shared between tabs (for example `WPCPM_Mail::render_invite_card()`)
  prints the parts on a tab that is on and its old markup on every other tab.
- **The root class:** while its switch is on, a surface's root prints `wpcpm-ui-on`: the dashboard's root
  element (`.wpcpm-dashboard`) and, on a framed page, `.wpcpm-frame`; in wp-admin the tab's `.wpcpm-wrap`.
  `parts.css` scopes every change to an existing shared class under it (section 3.1).
- **Deleting an old branch:** each old branch, and the `WPCPM_UI::on()` test around it, is deleted in the
  release that moves the last surface or tab using that renderer (section 4 says which, per renderer). A
  renderer a Report Card or a wp-admin tab left off also prints keeps its old branch, and the rules that
  branch needs, until that surface has a build of its own (section 6).
- **The suites** set the surface with `WPCPM_UI::enter()` and assert both branches while both exist.

## 4. The markup changes, renderer by renderer

"M" numbers are the prototype's markup changes (M1 to M22), each one PHP change here. Lines are at 1.123.89
(unchanged at 1.123.90). Every change below is the parts' branch of its renderer (section 3.5); where a
renderer is shared, the table says which surfaces still take its old branch and the release that deletes it.
Every block on a moved screen is listed (controller's ruling K); a block not listed here is not on a moved
screen.

### 4.1 Release 1: the parts, and the Administrator Dashboard

| File and function | What changes | Part |
|---|---|---|
| `includes/class-wpcpm-dashboard-card.php`, `open()` 461-552 | Prints the head through `fold_head()`: lead (title, phrase; the count only without a phrase), end (age tag, cue "Review" alone, help slot), chevron. Shared with the Sponsor Dashboard: old branch until release 3. M2, M2b | Card fold |
| same, `close()` 573-602 | The help link is printed into the head's slot, and a second copy as the first line of the body for the phone (one shown at each width); the card's action after the fold is not printed on the Overview (the theme hides it there today, `dashboard.css:5693-5695`); on Watch and The program it stays where it is drawn today, under the box in the flow, as the quiet button (builder's call 21). Old branch until release 3. M2 | Card fold |
| `includes/modules/class-wpcpm-administrators-dashboard.php`, `render_waiting()` 1045-1116 (the row at 1080-1090) | The Mentor applications row becomes the `--row` fold: the age tag already set for its anchor (`waiting()`, 580), `WPCPM_Guide_Jobs::link( 'mentor-application' )`, the Review link, the external-link icon. Not shared: no old branch after release 1. M3 | Row that does not open |
| `includes/modules/class-wpcpm-administrators-cards.php`, `render_application()` 2249-2303 | Item header (the state fact becomes a neutral tag, M7); "Read the application" (2288) an inner fold (M4). | Item header, inner fold |
| `includes/modules/class-wpcpm-institutions.php`, `render_open_decisions()` 4138-4224, `render_decision_form()` 4284-4340 | One Decide area through `decide()`: Send the confirmation link again (secondary, first, while the address is unconfirmed), Approve (primary), Ask a question (secondary, panel), Reject (danger, panel), Reject as spam (danger). The note boxes become `field()` with label and hint. Shared with wp-admin: old branch until release 2. M1, M5, M6 | Decide, field |
| same, `render_application_answers()` 3552-3590 | Key and value table; the Airtable column name (`<code class="wpcpm-inst-record">`, 3582) is not printed in the parts' branch (controller's ruling F). Shared with wp-admin: the old branch, which still prints the names there, goes in release 2, so from then they are printed nowhere. M8, M16 | Table |
| `class-wpcpm-administrators-cards.php`, `render_agreements()` 2313-2375, `render_agreement_list()` 2385-2413, `render_reinstate_form()` 2422-2434 | "Awaiting review" (2335) becomes the item's tag; item headers. M15 | Item header |
| `includes/modules/class-wpcpm-institution-panel.php`, `render_review()` 481-549, `render_accept_form()` 697-731, `render_return_form()` 747-780 | Decide: Accept it (primary, at once); Return with a note (secondary, panel; the label at 766 split into label and hint). The two filled `wpcpm-button`s (713, 775) become one primary and one confirming secondary. The review's facts (`render_review()` with no decisions) are shared with wp-admin (Institutions, Waiting for review, `class-wpcpm-institutions.php:3433`): that old branch until release 2. The accept and return forms are drawn only on the Administrator Dashboard: no old branch after release 1. M5, M6 | Decide |
| `class-wpcpm-administrators-cards.php`, `render_requests()` 2575-2655 | Item header; the kind (2608) becomes a fact. M15 | Item header |
| `includes/modules/class-wpcpm-institution-request.php`, `render_decisions()` 1772-1862 | Decide with two openers, Mark as handled and Decline, sharing one panel: the visible label (`note_label()`, today screen-reader text at 1835-1839), Optional, the hint, the two confirming buttons (one shown per opener) and Cancel. The form stays one form posting `state`. Called only from the Administrator Dashboard (`class-wpcpm-administrators-cards.php:2622`): no old branch after release 1. M5, M6 | Decide |
| `class-wpcpm-administrators-cards.php`, `render_sponsor_posts()` 675-727 | Item header; "Preview the post" a link in the facts. | Item header |
| `includes/modules/class-wpcpm-sponsor-posts.php`, `render_decision()` 1827-1882 | Decide: Publish (primary), Return with a note (opener, not a `summary.button`, 1854-1855; panel open when a refused note came back). Preview (1836) leaves the row. Called only from the Administrator Dashboard (`:718`): no old branch after release 1. | Decide |
| `class-wpcpm-administrators-cards.php`, `render_reports()` 2446-2563 | Tables through `table_open()`; the row's Review the quiet row action; age and count cells marked. M8 | Table, row action |
| `class-wpcpm-administrators-cards.php`, the other cards: `render_sponsor_agreements()` 574, `render_sponsor_offers()` 740, `render_sponsor_events()` 785, `render_sponsor_applications()` 831 (its "Read the application" fold, 916), `render_sponsor_reports()` 1281, `render_details_request()` 2695; the Watch and The program cards (`render_offers_low()` 941, `render_duplicates()` 1000, `render_interests()` 1075, `render_sponsors_strip()` 1126, `render_tiers_ending()` 1213, `render_programs()` 2792, `render_quiet_students()` 2926, `render_mentor_engagement()` 3158, `render_health()` 3211) | Item headers, inner folds, tables, in-card headings. These print only on the Administrator Dashboard: no old branch after release 1. | All |
| `includes/modules/class-wpcpm-sponsor-offer-review.php`, `render_decision()` 377 (return fold 405-406); `class-wpcpm-sponsor-agreement.php`, `render_decision()` 526 (return fold 574-575); `class-wpcpm-sponsor-application.php`, `render_decision()` 5215 (called at `class-wpcpm-administrators-cards.php:921`); `class-wpcpm-sponsor-event-queue.php` (folds 566, 583; return fold 804) | Decide areas and inner folds, the same rules. The offers', agreements' and events' decisions are called only from the Administrator Dashboard (`class-wpcpm-administrators-cards.php:657`, `765`, `806`): no old branch after release 1. The sponsor application's decision form is shared with wp-admin through `WPCPM_Sponsor_Application::render_open()` (`class-wpcpm-sponsors.php:2983`): that old branch until release 2. | Decide, inner fold |
| `includes/modules/class-wpcpm-cohort-timeline-view.php`, `render_card()` 87-130 (the card that leads Watch, `class-wpcpm-administrators-dashboard.php:867`) and `render_view()` 178-234 (the dashboard's fourth view, `:343-344`, `:367`) | The card's head through the card shell; the view's tables, headings and buttons on the parts; its quiet line (`.wpcpm-administrator__quiet`, 731) takes the parts' note style (builder's call 25). Not shared: no old branch after release 1. | Card fold, table |
| `includes/modules/class-wpcpm-sponsor-tools.php`, `render()` 400-472 ("Tools from our sponsors" on The program, `class-wpcpm-administrators-dashboard.php:1164`) | Its rows, chips and buttons on the parts. Shared with both Report Cards (`class-wpcpm-mentors-dashboard.php:741`, `class-wpcpm-students-dashboard.php:627`): the old branch stays for `report-cards`. | List row, button |
| `includes/modules/class-wpcpm-sponsor-event-list.php`, `render_actions()` 250-293 (inside the event queue, `class-wpcpm-sponsor-event-queue.php:619`) | Its buttons on the parts. Shared with the Report Cards' calendars and the Institution Dashboard: the old branch stays for `report-cards`; `institution` takes the parts in release 4. | Button |

In release 1 every wp-admin tab still prints the old markup, those that share these renderers included.

### 4.2 Release 2: the plugin's wp-admin screens

Release 2 turns on exactly the tabs below, each whole (controller's ruling M): Sponsors: Waiting for review,
Agreements. Mentors: Waiting for review. Students: Interests. Institutions: Waiting for review, Pipeline,
Accounts (both of its states), Agreements. Tools, Emails: Log. Every other wp-admin tab stays off and prints
exactly what it prints today. The old branches of 4.1's truly shared renderers are deleted here, unless a tab
left off still prints them.

The Waiting for review rows that today link to the Administrator Dashboard to decide (sponsor posts, offers,
events and agreements; institution requests) keep that link: release 2 gives them the parts' item header,
tags and link style, and adds no Decide area in wp-admin (controller's ruling L; section 9).

| File and function | What changes | Part |
|---|---|---|
| `includes/modules/class-wpcpm-institutions.php`, Waiting for review: `render_tab_queue()` 2848-2850, `render_queue()` 3218-3308, `render_queue_row()` 3331-3437, `render_application_actions()` 3995-4020, `render_application_here()` 4052-4126 | The whole tab on the parts: item headers and tags on every row; an application past the card's window keeps deciding here, through 4.1's Decide area and answers table (`render_application_here()`, called at 3527-3529); the agreements in review show the review's facts (`render_review( id, false )`, 3433), no decisions; the requests keep their link to the dashboard (3423, 3426), in the parts' link style. Deletes the old branches of `render_open_decisions()`, `render_decision_form()`, `render_application_answers()` and the review's facts. | Item header, Decide, table |
| `includes/modules/class-wpcpm-sponsors.php`, Waiting for review: `render_tab_queue()` 2979-3002, `render_queue_posts()` 3373-3431, `render_queue_offers()` 3442-3492, `render_queue_events()` 3510-3567, `render_queue_agreements()` 3580-3612, and `WPCPM_Sponsor_Application::render_open()` 4799 and `::render_queue()` 4532 | The whole tab on the parts: item headers and tags on every row; the sponsor applications keep deciding here through their decision form (`render_open()`, 2983), now a Decide area; the posts, offers, events and agreements keep their link to the dashboard (`WPCPM_Return::render_dashboard_link()`, 3425, 3486 and the events rows), in the parts' link style, with no Decide area. Deletes the old branch of the sponsor application's decision form. | Item header, Decide |
| `class-wpcpm-sponsors.php`, Agreements: `render_agreements()` 4857-4887, `render_agreement_state()` 4990-5058, `render_agreement_form()` 5081-5142, `render_reinstate_here()` 3717-3762 | The list with a state: the in-place filter (All, Nothing on file, In force, Revoked, with counts), "Find a company", one row per Approved sponsor (name, tag, "Since <date>" or "Revoked <date>", "Signed copy" link), one quiet action: Record a signed copy (row form: Drive link, date signed at 200px, "Record it as on file", Cancel), Revoke (quiet red; row form: the note, "Revoke the agreement", Cancel), Reinstate (at once, its question). A revoked row reinstated on the Administrator Dashboard keeps today's sentence and link instead of a button. M12 | List row |
| `includes/modules/class-wpcpm-mentor-application-queue.php`, the view links (`subsubsub`, 1263), `render_find()` 1349-1388, `render_row()` 1448-1474, `render_decisions()` 2048-2217, `render_form()` 2230-2281 | The filter with counts; "Find by reference" as the search on the filter's row with "Find"; the item header (the age line split into facts, the reference into line 1); one Decide area: Select (primary, panel), Ask for more information (secondary, panel), Second opinion (secondary, panel), Decline (danger, panel), Mark as spam (danger, at once). The other states' decisions follow the same rules (builder's call 8). M6, M15, M18 | Filter, search, item header, Decide |
| `includes/modules/class-wpcpm-student-interests-tab.php`, the view links (250), `render_row()` 337-367, `render_state()` 434-465, `render_consent()` 528-551, `render_presses()` 574-607, `render_form()` 619 | Filter with counts; item header (the state word a tag, M7; the email joins the facts; the reference in line 1); Consent an inner fold (531, M4); Decide: Mark contacted (primary), Close the interest (danger), Mark as spam (danger), each at once. | Filter, item header, inner fold, Decide |
| `class-wpcpm-institutions.php`, Accounts, its list state: `render_tab_accounts()` 2885-2897, the locked accounts, the institution accounts list table (`class-wpcpm-institutions-table.php`), and the invitations card (`WPCPM_Mail::render_invite_card()`, called at 2996) | The whole state on the parts: the list table takes the table part's look as the Log's does (builder's call 26), the test institution's mark and other marks become status tags, the invitations card its rows and buttons. `render_invite_card()` is shared with the Accounts tabs of Mentors (`class-wpcpm-mentors.php:826`), Sponsors (`class-wpcpm-sponsors.php:3300`) and Students (`class-wpcpm-students.php:474`), which stay off and keep its old branch. | Table, tag, list row |
| `includes/modules/class-wpcpm-institution-people.php`, Accounts, its Manage members state: `render_manager()` 575-663, `render_member()` 245-275, `render_remove_form()` 345-371, `render_add_form()` 758-807 | People rows (M11; Remove quiet red); "Add an account" its own card with its fields and one primary, "Name" Optional, the three paragraphs under the button its intro (M10, M20, M21). `render_member()` is shared with the Institution Dashboard (177): old branch until release 4. | List row, field, action row |
| `includes/modules/class-wpcpm-institution-panel.php`, `render_manager_upload()` 1214, `render_upload_form()` 1103 | "Upload a signed agreement on the institution's behalf" its own card; the agreement choices a choice group; the file box. `render_upload_form()` is shared with the Institution Dashboard (865, 878, 945): old branch until release 4. M19, M21 | Choices, file |
| `includes/modules/class-wpcpm-institutions.php`, Pipeline: `render_pipeline()` 4391-4537 (the folded stages, 4525), `render_pipeline_table()` 4546-4565, `render_pipeline_find()` 4585-4646, `render_pipeline_row()` 4686-4805 | One table family with one `colgroup` for all nine stage tables (M9); every stage an inner fold, the counted stages drawn open and the four set-aside stages folded as today, and at 782px or less the script closes all but the first (controller's rulings H and Q); the combobox 320px with "Open its page"; dates in the site's format; the record ID (4753) not shown but kept reachable: `data-record` on the row and the name link's `title`, "Airtable record: rec..." (controller's ruling F); the intro one paragraph style and one note style; "no email" the red tag. | Table, inner fold, search |
| `class-wpcpm-institutions.php`, Agreements: `render_tab_agreements()` 3027-3033, `render_agreements_on_file()` 4952-5021, `render_agreements_in_force()` 5038-5117, `render_agreements_revoked()` 5137-5259, `render_revoke_form()` 5275-5319, and the discrepancies and template cards | The whole tab on the parts, its five cards kept as they are (controller's ruling E): rows as list rows with tags and quiet actions; Revoke (quiet red) opens the row's form under the row (the note field, "Revoke this agreement" as danger, Cancel), replacing the fold "Revoke this agreement"; Reinstate (quiet, at once, its question); the template card's fields and buttons. | List row, field |
| `includes/tools/class-wpcpm-emails.php`, `render_log_tab()` 1005-1120 | The filters and the table in the card every tab uses, "Log" its heading; the filters as fields on a grid of three equal columns (the search spans two, the Email combobox the third), From and To 200px, "Filter" the secondary button in its action row; references never broken. M17 | Field, table |

The Institution Dashboard keeps its old member rows and upload form until release 4.

### 4.3 Release 3: the mentor cards and the rest of the Sponsor Dashboard

| File and function | What changes | Part |
|---|---|---|
| `includes/modules/class-wpcpm-sponsor-mentors.php`, `render_mentor_card()` 457-534 | All six lines always printed (an empty one with no words); initials when there is no photo; every tag printed in a block marked `data-wpcpm-fit` with the "+N" words as data attributes, the script fitting them (controller's ruling G); the button "Sponsor this mentor" (529) with the mentor's name in its accessible name. M13 | Mentor card |
| `includes/modules/class-wpcpm-sponsor-tier-card.php`, `render_sponsorship()` 344-411, `render_term()` 476 | The note after the form (397, 407) printed inside the form as the button's consequence (M14); the tier line that repeats the head's phrase (`render_term()`, 486-514) not printed. | Action row |
| `includes/class-wpcpm-dashboards.php`, the switcher's "Show" (345) | The secondary button on the Sponsor Dashboard. Shared with the Institution Dashboard (release 4) and the Report Cards, which keep the old branch. | Button |
| `class-wpcpm-dashboard-card.php`, `open()` and `close()` | The Sponsor Dashboard on the parts; the old branch is deleted, no surface left on it. | Card fold |
| The Sponsor Dashboard's cards: `class-wpcpm-sponsor-offers.php`, `-sponsor-profile.php`, `-sponsor-logo.php`, `-sponsor-agreement-card.php`, `-sponsor-report-card.php`, `-sponsor-badge.php`, `-sponsor-usage.php`, `-sponsor-posts.php`, `-sponsor-tier-card.php` (events) | Buttons, fields, inner folds (the offers' rows, Edit this offer, New offer stay their own forms behind a button), tables. The cards keep what they hold and every form they post. | All |

### 4.4 Release 4: the Institution Dashboard on the parts

On the existing page, outside the frame, as the approved picture shows it (controller's ruling B). Its move
into the dashboard frame is not in this build.

| File and function | What changes | Part |
|---|---|---|
| `includes/modules/class-wpcpm-institutions-dashboard.php`, `render()` 314, `render_module()` 678-702, `render_identity()` 1112, `render_switcher()` 1322 | `WPCPM_UI::enter( 'institution' )`; section titles one level above a fold's title, the fold that repeats its section's title printed with no head (M22); the movers as bordered arrow squares under their own class; the cards' heads saying their count in words. | Section title, fold |
| `includes/modules/class-wpcpm-institution-roster-view.php`, `render_filters()` 374, `render_strip()` 558, `render_student()` 796-807 | Filters as fields in one row; the summary without its colored edge; each student an inner fold. | Field, inner fold |
| `includes/modules/class-wpcpm-institution-student-view.php`, `class-wpcpm-institution-details-card.php` (Correct these, 457), `class-wpcpm-institution-import-form.php`, `class-wpcpm-institution-history.php` | Key and value tables (one label color, one label column), inner folds, fields and choice groups. M16 | Table, fold, field |
| `includes/modules/class-wpcpm-institution-invite.php`, `render_form()` 286-373 (the invite form), `render_invite()` 425-461 | Invite a colleague as a field stack and a choice group, one primary, the paragraph under "Send invitation" its intro; invitation rows with Resend (quiet) and "Cancel invitation" (quiet red). M10, M11 | Field, list row |
| `includes/modules/class-wpcpm-institution-tutors.php`, `render_add_form()` 469-488 | Add a tutor: Name and Email fields and one action row. M10 | Field |
| `includes/modules/class-wpcpm-institution-people.php`, `render_member()`; `class-wpcpm-institution-panel.php`, `render_upload_form()` | The member rows (Leave quiet red on the reader's own row) and the upload form on the parts; their old branches are deleted, no surface left on them. | List row, file |
| `includes/class-wpcpm-dashboards.php`, the switcher's "Show" | Secondary on the Institution Dashboard; the old branch stays for the Report Cards. | Button |
| `includes/modules/class-wpcpm-semester-report-screen.php`, `render()` 211-296 (the Semester report module) | Its fields, buttons, tables and folds on the parts; the report's text boxes no longer monospace. The wp-admin Semester reports tab, which stays off, keeps the old markup of anything it shares with it. | Field, table, fold |
| `includes/modules/class-wpcpm-institution-panel.php`, `WPCPM_Institution_Agreement_Card::render()` 1628 (the class from 1613) | The agreement card on the parts: its state as a status tag, its forms (the upload form above) as fields and action rows. | Tag, field |
| `includes/modules/class-wpcpm-sponsor-event-list.php`, `render()` 142-195 (the sponsor events list, `class-wpcpm-institutions-dashboard.php:502`) and `render_actions()` 250-293 | The events as list rows with their buttons. Shared with the Report Cards' calendars (`class-wpcpm-call-calendar.php:274`, `801`): the old branch stays for `report-cards`. | List row, button |
| `class-wpcpm-institutions-dashboard.php`, `render_help()` 1022-1028 | The help block's links and buttons on the parts. | Button |

## 5. Words that change

New strings are marked **new** (they need translating); the rest reuse a string the plugin already has.
Every string follows the rules: "Administrators" is the one name for the role, never "program manager"; full
product names; never "out of force" in a new string (the agreement words are Revoke and Reinstate); plain
hyphens only.

| Where | Old | New |
|---|---|---|
| Every Decide area | (none) | "Decide" **new** |
| Fields | "optional" inside a label sentence | "Optional" **new**, printed by the field part; "Required" as today |
| Institution application | the box's label "Ask the applicant something. It is sent as it is written, with your address to reply to." | label "Ask the applicant something" **new**, hint "It is sent as it is written, with your address to reply to." **new** |
| | opener (none; the form showed "Send this question") | "Ask a question" **new**; the panel keeps "Send this question" |
| | "Why, for the next Administrator who reads this. It is never sent to the applicant." | label "Why, for the next Administrator who reads this" **new**, hint "It is never sent to the applicant." **new** |
| | "Reject" (the form's button) | opener "Reject"; panel "Reject the application" **new** |
| Collaboration agreement | heading "Awaiting review" | the same words as the item's tag |
| | "What has to change, in your own words. This is emailed to everybody at the institution exactly as you write it, with your address to reply to." | label "What has to change" **new**, hint "In your own words. It is emailed to everybody at the institution exactly as you write it, with your address to reply to." **new** |
| | opener (none) | "Return with a note" (exists); the panel keeps "Return it with this note" |
| School's request | placeholder "What you did about it, for the log. Optional, and not sent to the institution." | visible label "What you did about it" (exists, was for screen readers only), "Optional", hint "For the log. It is not sent to the institution." **new** |
| | "Mark as handled", "Decline" (the buttons) | openers as before; panel "Mark the request as handled" **new**, "Decline the request" **new** |
| Sponsor post | "Preview" button | "Preview the post" **new**, a link in the facts |
| | `summary` "Return with a note"; the box labelled for screen readers "A note for the author" | opener "Return with a note"; visible label "A note for the author" (exists); hint: the sentence "The post goes back to the sponsor's account as a draft, and your note is sent to its author by email." (exists, moves); panel keeps "Return it with this note" |
| Tables | (none) | "Action", for screen readers only (exists) |
| Mentors queue | "A note for the next Administrator, optional. It is never sent." | label "A note for the next Administrator" **new**, "Optional", hint "It is never sent." **new** |
| | "What you are asking about, for the next Administrator, optional. The email is the template's; this is never sent." | label "What you are asking about, for the next Administrator" **new**, "Optional", hint "The email is the template's; this is never sent." **new** |
| | "What a colleague should look at. Needed; nothing is sent to the applicant." | label "What a colleague should look at" **new**, "Required", hint "Nothing is sent to the applicant." **new** |
| | "Why, for the next Administrator, optional. It is never sent." | label "Why, for the next Administrator" **new**, "Optional", hint "It is never sent." (as above) |
| | the forms' buttons "Select", "Ask for more information", "Second opinion", "Decline" | openers as before; panels "Select the applicant" **new**, "Send the request for more information" **new**, "Ask for a second opinion" **new**, "Decline the application" **new** |
| | "Withdraw", "The course is completed" (selected applicants) | openers as before; panels "Withdraw the application" **new**, "Record the course as completed" **new** (builder's call 9) |
| Interests | "Close" | "Close the interest" **new** |
| Sponsors, Agreements | "This site: <state>." over each form | the state as a tag: "Nothing on file" (exists), "In force" **new**, "Revoked" (exists); facts "Since %s" **new**, "Revoked %s" **new**; the link "Signed copy" **new** |
| | (none) | filter "All" (exists), "Find a company" **new**, "No company matches." **new** |
| | (none) | openers "Record a signed copy" **new**, "Revoke" **new**, "Reinstate" (exists) |
| | "Take it out of force" | "Revoke the agreement" **new** |
| | "Why it is out of force, in your own words. This is emailed to everybody at the company exactly as you write it. Nothing about their dashboard changes." | label "Why it is revoked" **new**, hint "In your own words. It is emailed to everybody at the company exactly as you write it. Nothing about their dashboard changes." **new** |
| Institutions, Agreements | the fold "Revoke this agreement" | row action "Revoke" (as on Sponsors); the row form keeps "Revoke this agreement" and its label and hint (exist) |
| Institutions, Pipeline | the record ID printed under each name | the name link's `title` "Airtable record: %s" **new** |
| File boxes | the browser's "Choose File" | "Choose a file" **new**, "No file chosen" **new** |
| Emails, Log | (no heading) | "Log" (exists as the tab's name) |
| Mentor card | "I would like to sponsor this mentor" | "Sponsor this mentor" **new**; accessible name "Sponsor this mentor: %s" **new** |
| | (none) | "+%s" **new**; its accessible name "%1$s more: %2$s" **new** (printed as data attributes for the script) |
| Institution Dashboard heads | a count chip alone | "%s student" / "%s students" **new** (`_n`), "%s person" / "%s people" **new** (`_n`) |
| Invitation row | "Cancel" | "Cancel invitation" **new** (the question "Cancel the invitation to %s? ..." unchanged) |

Strings that stop being printed (the old label sentences, "Take it out of force", the request's placeholder,
"Preview", "Close" on an interest, "I would like to sponsor this mentor") leave the template in the release
that drops them; the `.pot` is regenerated with `bin/make-pot.sh`.

## 6. What is deleted

A rule is deleted in the release that moves the last screen using it, never before; an old PHP branch and its
switch, in the release that moves the last surface using that renderer (section 3.5). Lines are the rule's
first line at 1.123.89 (the plugin's sheets did not change between 1.123.88 and 1.123.90) and theme 1.32.27.
A rule still used by a screen this build does not touch stays, and says so. The old branches kept for the
Report Cards (`report-cards`, off for this whole build) and for the wp-admin tabs left off, and every rule those
branches need, stay until those screens have a build of their own (controller's ruling K). `administrator.css` loads only on
the front-end dashboards (the Administrator Dashboard, and the Sponsor Dashboard, whose sheet depends on it),
never in wp-admin, so its rules go with the Administrator Dashboard in release 1, except a selector the Sponsor
Dashboard's own renderers still print, which goes in release 3 (the builder greps them before deleting).

### 6.1 Plugin

| Rule | What it is | Goes in |
|---|---|---|
| `administrator.css:7-127` | The card and its absolutely placed action and help link | Release 1 |
| `administrator.css:304-306` | The fact separator that loses its space | Release 1 |
| `administrator.css:373-378` | Decisions as inline blocks | Release 1 |
| `administrator.css:383-489` | Subtitle, empty and quiet lines, `.wpcpm-admin-table`, the scroll box | Release 1 |
| `administrator.css:530-544` | The Mentor applications row | Release 1 |
| `dashboard-frame.css:14-39` | The tokens (moved into `parts.css`) | Release 1 |
| `dashboard-frame.css:723-787` | `.wpcpm-btn`, `.wpcpm-status` (moved into `parts.css`) | Release 1 |
| `dashboard-frame.css:232` | The phone menu button's own size (a button of the set, 40px) | Release 1 |
| `admin.css:122-139` | `.wpcpm-table` | Stays: the Sponsors list, Offers and codes and the Usage card still use it |
| `admin.css:542` | `.wpcpm-admin-picker` | Stays: the Semester reports tab (`class-wpcpm-semester-report-screen.php:733`) and the Sync and storage tab's link form (`class-wpcpm-institutions.php:6009`) print it |
| `admin.css:633-642` | `.wpcpm-inst-mark`, the 11px amber mark | Stays (controller's ruling N): the institution accounts table's test mark and the sponsor applications' marks are on tabs that move, but other Institutions and Sponsors tabs left off still print it |
| `admin.css:653` | `.wpcpm-inst-table td` | Release 2 (search: printed only by the Pipeline, `class-wpcpm-institutions.php:4547`) |
| `admin.css:698-712` | `.wpcpm-inst-folded` | Release 2 (search: printed only by the Pipeline, 4525, and the Agreements tab, 5279, both on) |
| `admin.css:782` | `.wpcpm-people--admin .wpcpm-people__member` | Release 2 (search: printed only by Manage members, `class-wpcpm-institution-people.php:604`, `686`) |
| `admin.css:814`, `888-902` | `.wpcpm-review`, `.wpcpm-review__note` | Release 2 (search: printed by `class-wpcpm-institution-panel.php:523`, `766`, `class-wpcpm-sponsors.php:4908`, `5100-5118` and `class-wpcpm-institutions.php:5294`, all on tabs or a dashboard that move by then) |
| `admin.css:1039-1066` | `.wpcpm-app-action` and its note | Stays: Students, Registrations, left off, prints `.wpcpm-app-action` (`class-wpcpm-student-registrations-tab.php:710`) |
| `admin.css:6-9` | The 70em cap on intros (`.wpcpm-lede`) | Stays (controller's ruling N): Settings, the Overview, Tools and every module screen print `.wpcpm-lede`; on a tab that is on, the intro takes the parts' intro class instead |
| `emails.css:7-15` | The Log's filter grid | Release 2 (search: printed only by the Log, `includes/tools/class-wpcpm-emails.php:1031-1100`) |
| `sponsor.css:130-145` | The mentor tiles (controller's ruling R) | Release 3 (replaced in `sponsor.css` by section 2.12). `.wpcpm-sponsor__people` (126-128), the people and mentor list rows, is not part of it and stays until a search shows no surface left prints it |
| `institution.css:322`, `408`, `515`, `544`, `577-590`, `619-622`, `914`, `971-1000`, `1345` | File box, member row, invite box, roster filters, the colored edge, the import fold's chevron, the import and details fields, the details actions | Release 4 |
| `report-print.css:151-156` | The report's text boxes (monospace) | Release 4, the on-screen rule only; the print rules stay |
| `dashboard.css:355-410`, `430-480`, `579-625` | The group fold, the student row, the mentee table | Stay: the Mentor and Student Report Cards draw them (not in this build). The moved screens print the parts' classes instead |

### 6.2 Theme (`assets/css/dashboard.css`)

| Rule | What it is | Goes in |
|---|---|---|
| `1701-1730` (decision buttons and boxes), `1764-1800` (return folds), `1921`, `1953-1962` (the review and its inline forms) | `.wpc-administrator-page` decisions | Theme 1.32.28, after plugin release 1 |
| `5594-5611`, `5622-5661`, `5677-5689`, `5693-5705` | The framed Administrator Dashboard's card heads, gaps, cue and row action | Theme 1.32.28, after plugin release 1 (the `.wpc-sponsor-page` lines stay until release 3) |
| `2095-2200` | The row action and help link placement, for both pages | Its `.wpc-administrator-page` selectors in 1.32.28; the rest in 1.32.29, after plugin release 3 |
| `5768-5775` | The Sponsor Dashboard's chevron | 1.32.29, after plugin release 3 |
| `447-468` | `.wpc-dashboard-page .wpcpm-button` at 8px | Stays: the Report Cards print `.wpcpm-button` |
| `858-873` | The section movers | 1.32.30, after plugin release 4 (the movers print their own class from then) |
| `1109-1170`, `1315-1385`, `1542-1575`, `3243-3251`, `3385-3445`, `3577-3645`, `3677-3700` | Tables, student actions and 56px resource buttons, the group fold and the student row outside the frame | Stay while the Report Cards use them; each selector that only the Institution Dashboard matched goes in 1.32.30 |
| `5023-5037` | Choice rows and their boxes by element (`label:has( > input[type="checkbox"] )`, `input[type="checkbox"]`) | Stays for the Report Cards; theme 1.32.28, the first theme release of this build, adds `.wpcpm-ui-choice` to the rules' existing `:not()` lists, so they never reach the part (controller's ruling S: they outrank a one-class part rule) |

### 6.3 The test for deleting, and the order

**The test (controller's ruling N):** a rule in a wp-admin sheet, or any rule a surface left off could reach, is
deleted only when a search of `includes/` proves that no tab or surface still off prints a class the rule
matches. The plan records the search for each deleted rule (the classes searched, the files and lines found);
the "search:" notes in 6.1 are the first record and are run again on the release's code.

**The order:** 
plugin first, theme after, in every release that has a theme release: a theme rule deleted while a live
plugin still prints its class would break that screen, while a plugin on the parts under a theme that still
holds the old rules shows at most a gap off by a few pixels until the theme follows (the moved markup no
longer carries the classes those rules match). WordPress core's `.striped` and `.widefat` classes are simply
no longer printed on the moved tables.

## 7. The releases

The plan, not a promise: every release reads the live plugin and theme versions first and takes the next
free numbers then. Today the next free plugin number is 1.123.92 (1.123.91 is reserved) and the theme's
1.32.28. Each release bumps the plugin's version and changelog, rebuilds the zip, updates the Administrator
guide for any screen whose words change, turns on its surfaces or tabs (section 3.5), and photographs before
and after (section 8). Screens nobody photographed on the parts (Watch, The program, the queue cards empty in
the approved pictures, the Sponsor Dashboard's other views) are shown to the owner at their release with
those before and after pictures; there is no separate approval round (controller's ruling C).

1. **Plugin 1.123.92, then theme 1.32.28: the parts, and the Administrator Dashboard on them.**
   - Ships `parts.css`, `parts.js`, `WPCPM_UI` and the surface switch, with `administrator` on.
   - Screens: every view of the Administrator Dashboard (Overview, Watch, The program, the cohort timeline)
     and every card and block it can draw, including the queues empty in the approved pictures, "Tools from
     our sponsors" and the event queue's actions.
   - Markup: section 4.1. Old rules: section 6, release 1.
   - The theme follows the same day, after the plugin is live, deleting the framed Administrator Dashboard's
     rules.
   - The theme release also ships the choice rows' `:not()` exception (section 6.2).
   - Untouched: every wp-admin tab (the renderers they share with the Overview print their old branch),
     the Sponsor Dashboard, the Institution Dashboard, the Report Cards. The frame's button and tag change
     their sheet, not their look: their new looks apply only under `wpcpm-ui-on` (section 3.1).
2. **Plugin 1.123.93: the plugin's wp-admin lists and queues.**
   - Turns on exactly these wp-admin tabs, each whole (controller's rulings M and T): Sponsors: Waiting for
     review, Agreements. Mentors: Waiting for review. Students: Interests. Institutions: Waiting for review,
     Pipeline, Accounts (both of its states: the list with the locked accounts, the accounts table and the
     invitations card; and Manage members), Agreements. Tools, Emails: Log.
   - Markup: section 4.2; the old branches of 4.1's truly shared renderers are deleted. Old rules: section 6,
     release 2, each after its search. No theme release: wp-admin has no theme rules.
   - Untouched: every other wp-admin tab (section 10 lists them), not a pixel; the Institution Dashboard (its
     member rows and upload form keep their old branch).
3. **Plugin 1.123.94, then theme 1.32.29: the mentor cards and the rest of the Sponsor Dashboard.**
   - Turns `sponsor` on.
   - Screens: every view of the Sponsor Dashboard.
   - Markup: section 4.3. Old rules: section 6, release 3; `WPCPM_Dashboard_Card`'s old branch goes.
   - The theme follows, deleting the Sponsor Dashboard's card rules.
   - Untouched: the Institution Dashboard, the Report Cards.
4. **Plugin 1.123.95, then theme 1.32.30: the Institution Dashboard on the parts.**
   - Turns `institution` on.
   - Screens: the Institution Dashboard for a representative, a tutor and an Administrator viewing a school,
     outside the frame, as today's page, every block of it: the Semester report module, the agreement card,
     the sponsor events list and the help block included.
   - Markup: section 4.4. Old rules: section 6, release 4.
   - The theme release follows only to delete the rules only this page matched (section 6.2: the movers,
     the outside-frame selectors the Report Cards do not use); if a re-check at release finds none to delete,
     there is no theme release.
   - Untouched: the Mentor and Student Report Cards.

## 8. Acceptance, for every release

1. **Pictures:** full-page pictures at 1280 and 390 wide of every open state of every screen the release
   touches (each card open, each Decide panel open, each row form open, each filter), and of the screens it
   does not touch (the other dashboards and their views, the Report Cards, the plugin's other wp-admin tabs),
   before and after, with invented data (example names, `example.com` and `example.org` addresses), the
   account menu closed and nothing hovered. Each is read against `DESIGN.md` and this document; "after"
   matches the approved pictures for every screen they show.
   - **Which side of each breakpoint (controller's ruling Q):** 1280 is above every breakpoint (the frame's
     1,100px and 782px, wp-admin's 782px, the front end's 600px); 390 is below all of them. Release 2 adds
     pictures at 768 of its tabs, the one width between the two sides of wp-admin's 782px that a phone at
     390 does not show (the Pipeline's stages closed, the tablet layout of the bar and the rows); the
     dashboards' 601px to 1,100px range keeps the frame's own rules and is not photographed.
2. **Each screen is all old or all new:** on every picture, a screen the release touches shows only the
   parts and a screen it does not touch only the old markup; no tab and no block mixes them. On every wp-admin
   tab that is off, and every surface that is off, the before and after pictures are identical, not a pixel
   changed (controller's ruling M).
3. **The card head matches the approved pictures** at 1280 and at 390, the help link included (the condition
   of builder's call 4, controller's ruling D).
4. **Measures from computed styles** (as the prototype's measuring script did), per screen and in total:
   - front end: four button looks plus the pressed states, one button height (40px); card heads 56px open and
     closed, inner heads 44px; one field height (40px), text boxes at least 96px; one in-card heading style;
     one kind each of filter, search field, item header; one label color in key and value tables;
   - wp-admin: WordPress's own button metrics, every variant 500 13px; the same fold and field rules;
   - every gap on the rhythm: label 6, hint 6, field 14, last field to action row 16, Decide row to panel 16,
     heading 24 above and 8 below (12 under "Decide"), folds 10 apart; zero gaps off it;
   - one border per row action: a quiet action measures border 0, a transparent fill, 40px, no side padding at
     rest.
5. **Keyboard:** every fold opens with Enter and Space; the focus ring shows on every control; an opener moves
   focus into its panel and Cancel (or Escape) puts it back on the opener; a row form likewise; nothing is
   reachable that is hidden.
6. **Phone:** at 390 wide no page scrolls sideways and nothing is cut; the Pipeline opens with only its first
   stage open at 390 and at 768.
7. **No script:** with the script off, every panel and row form is on the page and every decision posts; the
   mentor tags clip at their height with no "+N"; the Pipeline's stages are open.
8. **Refusals still lead to the refused field:** for each form that can be refused, a refused press comes
   back with its sentence on the refused item's own box, and the outcome or summary that takes the focus
   leads to that box's new id.
9. **Forms post the same fields as before:** for each form a release touches, the names and values a press
   posts are compared before and after (the hidden inputs, the nonce's action, the return field, `state` on
   a request); only the label text around them changes.
10. **The suites pass:** `bin/test-*.php`, the `bin/js/` tests, `bin/check-standards.sh`, the spelling and
   reference checks. Assertions on markup the release changes (for example `bin/test-dashboard-card.php`,
   `bin/test-administrators-dashboard.php`, `bin/test-mentor-application-queue.php`,
   `bin/test-sponsors-screen.php`, `bin/test-sponsor-posts.php`) are rewritten to the new markup in the same
   release, and a shared renderer is asserted on both branches while both exist; assertions on what is
   posted, refused or kept are not weakened.

## 9. What does not change

- Behavior, permissions, emails and data: every handler, capability check, nonce, redirect, refusal, kept
  typing, browser question and email is as today. A panel that held a refused note opens on load as today's
  fold did.
- Anchors and ids that links, mails, the guides or scripts point at stay where they are, so mails, redirects,
  `WPCPM_Return` and the guides' links land where they land today. Field `name` attributes stay exactly as
  they are.
- Field ids that today repeat on one page (every application's boxes on the Overview share `wpcpm_question`
  and `wpcpm_reason`, `render_decision_form()`, `class-wpcpm-institutions.php:4319-4329`) become unique per
  item (the field's name and the item's id, `wpcpm_question-123`), since the hint, the panel and the focus
  need unique ids (controller's ruling P). A refusal still leads to the field of the item that was refused:
  the renderer already knows which item was refused (`box_problem()`, 4355-4369, by the application the press
  was made for), and passes that item's new field id to `WPCPM_Form_Errors::attrs()` and `::problem()`, which
  tie the sentence to the box (`problem_id()`); where a form lists its refusals in `WPCPM_Form_Errors::summary()`,
  its field map gives the new id, so the summary's link goes to the refused item's box.
- wp-admin's Waiting for review rows that link to the Administrator Dashboard to decide keep that link; no
  Decide area is added in wp-admin (controller's ruling L).
- The two behavior changes that ARE in:
  1. A decision with a note opens its panel on demand, instead of every form showing at once.
  2. On Requests from institutions, Mark as handled opens the optional note first (with Decline sharing it),
     instead of acting from the row. The owner left this as pictured: it is not to be changed back.
- The waiting tags stay red for every wait, as today; the owner left this as pictured.

## 10. Not in this build

- One verb per decision across the queues (Approve and Select, Reject and Decline stay as they are).
- Red waiting tags only for old waits (every waiting tag stays red).
- The Mentor Report Card and the Student Report Card: their surface, `report-cards`, stays off, and the blocks
  they share keep their old branch for them.
- The remaining wp-admin tabs, a fifth step after this build, scoped with pictures after release 2 so that
  wp-admin ends in one look (controller's ruling M): the Overview screen; Institutions: Semester reports, Sync
  and storage; Sponsors: Sponsors, Accounts, Offers and codes, Interests, Reports, Handbook listing; Mentors:
  Accounts, Engagement, Sync; Students: Accounts, Registrations, Sync; Administrators: Accounts, Capabilities;
  the Tools screen and its tools: Header notices, Emails (Templates, Signature and footer), Need help?,
  Mentor Status Checker, Student Duplicate Finder, Track Builder; Settings (Connection, Students and mentors,
  Institutions, Sponsors, Security, Mail, Advanced).
- The Institution Dashboard's move into the dashboard frame: it needs its own concept and the owner's look
  first, in its own design (controller's ruling B).
- Merging the Institutions Agreements tab's five cards into one filtered list.
- Decide areas in wp-admin for what is decided on the Administrator Dashboard today.
- Card in card on the Sponsor Dashboard's Offers view (its structure stays; only its parts change).
- One date format across the Overview and the Log.

## 11. Builder's calls

Each is mine, not the sources'; each says what it costs if wrong. Where a ruling of the controller settled a
call, the call says so.

1. **Class names:** the button and the tag keep the frame's `wpcpm-btn` and `wpcpm-status`; every other part
   is new under `wpcpm-ui-`, so no legacy rule matches a moved screen and no rule has to be fought. If wrong:
   a rename across `parts.css` and `WPCPM_UI` before release 1; after it, the suites too.
2. **One sheet loaded everywhere a dashboard loads**, as a dependency of `dashboard.css`, and in wp-admin as a
   dependency of `admin.css`, styling only its own classes. If wrong (a page that should not carry it): one
   more conditional enqueue; the cost meanwhile is one cached file.
3. **The frame's tokens move into `parts.css`** on `:where( .wpcpm-frame, .wpcpm-dashboard, .wpcpm-wrap )`,
   and the frame's sheet depends on it. If wrong: the frame's sheet declares them again, a copy of 25 lines.
4. **The help link stays out of the summary** (accepted by the controller, ruling D, on condition that the
   head matches the approved pictures at 1280 and 390: section 8, item 3). A link inside a `summary` is a
   control inside a control, which the card shell's own docblock rules out and screen readers read badly. It
   is printed beside the disclosure and laid into the head's reserved 104px slot by the card's grid (normal
   flow, not absolute), with a second copy as the body's first line for the phone, one hidden at each width.
5. **Progressive enhancement:** panels, row forms and Cancel are printed for a page without the script; the
   script folds them. If wrong: a moment of open panels on a slow page before the script runs; the fix is a
   class on `html` set before paint, as the frame's folded menu does.
6. **Escape closes an open panel or row form**, as Cancel does. Not in the sources. If wrong: one listener
   removed.
7. **Reinstate acts at once** with its question, since it takes no field, on both Agreements tabs; Record and
   Revoke open row forms. If wrong: Reinstate gets an empty row form with its button and Cancel.
8. **Decisions the pictures did not show follow the rules:** no note, at once (Send the confirmation link
   again, Send the acknowledgement, Put back in the queue, Write the Airtable row again, Delete for good,
   Mark as spam); a note, a panel (The course is completed, Withdraw). Kinds: the forward act primary,
   Delete for good and Withdraw danger, the rest secondary. If wrong: a kind or a panel per decision, one
   line each.
9. **Two confirming labels the sources do not give:** "Withdraw the application" and "Record the course as
   completed". If wrong: two strings.
10. **The label splits** in section 5 follow the prototype's rule (first clause the label, the rest the
    hint), and "Needed;" is dropped from the Second opinion hint because the field now says Required. If
    wrong: string edits.
11. **Release 1 covers every view, card and block of the Administrator Dashboard** (controller's rulings A, C
    and K: the cohort timeline, "Tools from our sponsors" and the event queue's actions included, shown to the
    owner at the release). Mine: the five queues empty in the approved pictures are seeded for the pictures.
    If wrong: more to photograph in release 1, nothing more to build.
12. **How the surface is known** (the mechanism of rulings A and M): every screen names itself when its render
    begins, `WPCPM_UI::enter()` with the dashboard's name, or in wp-admin with `<page slug>/<tab>` once the
    module screen has resolved its tab; a page that names none is never on. If wrong (a render path that misses
    `enter()`): that block prints the old branch; the "all old or all new" check on the pictures catches it.
13. **The Waiting for review tabs of Institutions and Sponsors** move whole in release 2 (controller's rulings
    L and M) and keep their links to the dashboard; mine: those links take the quiet look of a row action,
    since each is its row's one action. If wrong: a style per link.
14. **Students, Registrations** is off in this build (controller's ruling M). Its "Answers and consent" fold
    shares a legacy class with the Interests tab's Consent fold; mine: Interests prints the parts' inner fold
    class instead, so the legacy class keeps its look on Registrations. If wrong: none.
15. **Every Pipeline stage is an inner fold** (the counted stages drawn open, the four set-aside stages folded
    as today), so ruling H's fold at 782px or less (ruling Q) has a fold to close. If wrong: only the set-aside stages fold, and the
    phone ruling closes nothing on the counted ones.
16. **Dates on the Pipeline and Agreements** are formatted in PHP in the site's date format
    (`wp_date( get_option( 'date_format' ) )`). If wrong: none expected; it is the site's own setting.
17. **One-screen rules live with their screen:** the mentor card's in `sponsor.css`, the movers' in
    `institution.css` under a new class (`wpcpm-institution__mover`), so the theme's mover rule stops
    matching and can go. `parts.css` holds what more than one screen uses. If wrong: a move between sheets.
18. **The theme always follows the plugin**, the same day, and only deletes rules the plugin no longer
    matches. Where a theme rule must stay for the Report Cards but reaches a part by element, the theme adds
    the part's class to that rule's `:not()` list, in the first theme release of this build (controller's
    ruling S, for the choice rows). If wrong: a few pixels off for the minutes between the two deploys.
19. **The Log's pager count** ("57 items") is hidden in `emails.css`, since `WP_List_Table` prints it; the
    sentence "57 emails match." stays. If wrong: one rule.
20. **The helpers return strings** and pick the front-end or wp-admin markup by `is_admin()`, with a `side`
    argument for the suites. If wrong: a print variant per helper.
21. **A card's action on Watch and The program** ("Open the Institutions screen" and the like, passed to
    `WPCPM_Dashboard_Card::open()` as `action`) stays under the card's box, in the flow, 8px under it, drawn
    as the quiet button; the approved pictures show only the Overview, where it is not drawn. It stands, and
    is judged on release 1's pictures (controller's ruling C). If wrong: it moves into the head's slot beside
    the help link.
22. **The "+N" words reach the script as data attributes** printed by PHP, so they are translated with the
    plugin's strings rather than by a script translation file. If wrong: `wp_set_script_translations()`.
23. **On the Institutions Agreements tab the confirming button keeps "Revoke this agreement"**, its own
    label, under the rule that a form with its own label keeps it; the opener is "Revoke", as on Sponsors.
    If wrong: one string.

24. **The root class is `wpcpm-ui-on`** (ruling O asked for a name), printed by the root a surface already
    has: `.wpcpm-dashboard` and `.wpcpm-frame` on the front end, the tab's `.wpcpm-wrap` in wp-admin. If wrong:
    a rename.
25. **The cohort timeline's quiet line** (`.wpcpm-administrator__quiet`, `class-wpcpm-cohort-timeline-view.php:731`)
    is a sentence with no control in it, so it takes the parts' muted note style, not a button; any link or
    button in the view takes the quiet button where it is a row's action. If wrong: one class.
26. **The institution accounts list table** (a `WP_List_Table`) takes the table part's look in its card, as the
    Log's list table does, with WordPress's own pager and row actions kept. If wrong: a rule set for that one
    table.
27. **A unique field id is the field's name and the item's id** (`wpcpm_question-123`), the pattern the
    mentor queue's boxes already use (`$args['action'] . '-' . $post->ID`,
    `class-wpcpm-mentor-application-queue.php:2245`). If wrong: a different suffix, nothing else.

Replaced by the controller's rulings and so no longer calls: the mentor tags' count cap (ruling G), the
Pipeline's phone folds left out (ruling H), the record ID dropped from the page (ruling F), and the shared
renderers moving surfaces early (ruling A), one switch for all of wp-admin (ruling M), and the deletions of
`.wpcpm-inst-mark` and the `.wpcpm-lede` cap (ruling N).

## 12. What must stay true

1. Every button on a screen moved to the parts is one of four variants, 40px high, and in wp-admin is
   WordPress's own `.button` or `.button-primary` at 500 13px.
2. Nothing that removes, rejects, declines, revokes, withdraws, leaves, ends, deletes, cancels an invitation
   or marks as spam is drawn as a primary button.
3. One primary button per card or form.
4. A card fold's head is 56px open and closed, an inner fold's 44px; each has one chevron, at the far right.
5. No piece of a fold head is placed absolutely, and nothing of a card hangs under its box.
6. A head says its count once, in words; "Close" is never a cue.
7. Every label sits over its box (except the "Viewing as" toolbar), every hint is tied to its box with
   `aria-describedby`, and "Required" or "Optional" is in words.
8. No paragraph sits under a button except that button's own consequence, 6px under it.
9. No note form is open before it is asked for (with the script running), only one panel is open at a time,
   and focus goes into an opened panel and back to its opener on Cancel.
10. With the script off, every form is on the page and posts.
11. Every form posts the same fields and values as before the release.
12. Every gap on a moved screen is on the rhythm (6, 14, 16, 24, 22, 10) and every focus ring is a 2px
    Blueberry outline 2px off the control.
13. At 390 wide no page scrolls sideways.
14. `parts.css` styles only the parts' own classes and the frame's button and tag it took over unchanged; the
    Report Cards look exactly as before every release.
15. Each screen is all old or all new on the live site: no wp-admin tab and no dashboard block shows the parts
    beside the old markup. Which markup a shared renderer prints is decided by the one switch of the surface or
    wp-admin tab drawing it (`WPCPM_UI::LIVE`), never by an argument passed through renderers.
16. On every wp-admin tab that is off, and on the Report Cards (`report-cards`, off for this whole build),
    nothing changes, not a pixel.
17. A change to how an existing shared class looks (`.wpcpm-btn`, `.wpcpm-status`) applies only under
    `wpcpm-ui-on`.
18. A legacy rule is deleted only in the release that moves the last screen using it and only after a recorded
    search shows no screen still off prints a class it matches; an old PHP branch only in the release that
    moves the last surface or tab using it; and the plugin is live before the theme release that deletes a
    rule.
19. No new string says "program manager" or "out of force"; "Administrators" is the role's one name; no
    string, comment or document holds an em or en dash.
20. Every anchor and id a mail, redirect, link, guide or script points at is printed where it was; every field
    keeps its `name`; a field id that repeated on one page may change, and a refusal still leads to the
    refused item's field.
21. The Pipeline's record ID is never shown on the page and is always on its row (`data-record`) and in its
    name link's `title`; the Airtable column names under the answers are printed nowhere on the parts.

## Open questions for the controller

1. **The cohort timeline's "quiet" (ruling K).** The ruling says its `.wpcpm-administrator__quiet` "becomes the
   quiet button", but at `class-wpcpm-cohort-timeline-view.php:731` it is a sentence with no control ("No
   students start in the next twelve months yet."). Builder's call 25 draws it as the parts' muted note. Is
   that what was meant, or is there a control in the view the ruling had in mind?

## 13. Old classes kept on the Administrator Dashboard after release 1

Every class an Administrator Dashboard parts branch prints that is not a part's (`wpcpm-ui-*`), the frame's, the
shell's root (`wpcpm-dashboard`), a button or tag of the set, an icon, `screen-reader-text`, the card's wrapper, or an
item's type class or anchor, is named here. The suites hold the same list (`bin/stubs/parts-inventory.php`, one slice per
suite that draws the real renderer, `parts_inventory_pinned()`), so a class that appears later fails its suite. The
Program updates view and the guide view print a reading layout by hand and are not converted in this release, so
they are not in this list.

Settled by Task 17b: a note a person typed is the note part (`WPCPM_UI::note()`, `.wpcpm-ui-note`, one block with
`.wpcpm-ui-list__note`); Recently closed and an event's Who joined are list rows; the Syncs and health lines are one
intro line each, their link after their words; the Semester reports tables and a details request's changes table
take the row header; Students gone quiet's form and the deciding review's download line print no class. Its fix round:
an item's article prints `wpcpm-ui-item`, its type class and its anchor, and no longer `wpcpm-administrator__item` or
an overdue modifier, so the part's line between two items (the soft hairline, 24px above and below) is the only one;
the closed folds (rejected and spam applications, returned and revoked agreements, Recently closed) are inner folds
under today's summary; a request's two sentences are intro lines, the second muted; the agreement's review prints its
anchor and no `wpcpm-review*` class, its sentences intro lines and its checklist a plain list printed by
`WPCPM_UI::checklist()` (`wpcpm-ui-checklist`, 14px around it, its words 20px in with the browser's disc markers; each line
`wpcpm-ui-checklist__item`, 6px between two lines; Task 18a). Task V1 (ruling 8): a sponsor offer's details are the key
and value table part, each code whole on its own line (`.wpcpm-ui-code`), so `wpcpm-offer__details`, `__detail`,
`wpcpm-sponsor-offer__codes` and `__codes-added` are no longer printed there.

| Class or family | What it is | What styles it | Why it stays |
|---|---|---|---|
| `wpcpm-administrator`, `wpcpm-administrator--{view}`, `wpcpm-administrator__group`, `__group--{name}`, `wpcpm-overview`, `--wait`, `--watch`, `wpcpm-reference` | The shell: the dashboard's root beside `wpcpm-dashboard`, the groups of cards, each view's wrapper | `administrator.css` `.wpcpm-frame .wpcpm-administrator`, `.wpcpm-overview`; theme `.wpc-administrator-page .wpc-main--app .wpcpm-administrator__group`, the `:is( .wpcpm-administrator, .wpcpm-sponsor )` rules | The page's own frame and names, drawn on and off |
| `wpcpm-head*`, `wpcpm-figures*`, `wpcpm-figure*`, `is-current` | Each view's head and the Overview's figures; the menu's current item | `dashboard-frame.css` `.wpcpm-head*`, `.wpcpm-figure*`, `.wpcpm-frame__item.is-current` | The frame's own blocks |
| `wpcpm-job-link` | Every card's help link, in the fold's help slot | `administrator.css` `.wpcpm-job-link` (outside every range release 1 deletes) | Printed by `WPCPM_Guide_Jobs::link()` for every dashboard |
| `wpcpm-programs__tiles`, `__tile`, `__name`, `__n`, `__l` | The tiles of Mentor engagement, the Sponsors strip and Programs running | `administrator.css` `.wpcpm-programs__*`; theme `.wpc-administrator-page .wpc-main--app .wpcpm-programs__tile` | No part draws a tile (ruling 6) |
| `wpcpm-timeline*`, `wpcpm-timeline-stats*`, `wpcpm-timeline-intakes*`, `wpcpm-timeline-icon`, `is-unknown`, `is-zero` | The Upcoming cohorts card's figures and chart, the cohort view's chart and intakes | `cohort-timeline.css` | No part draws a chart (ruling 6) |
| `wpcpm-timeline-view*`, `wpcpm-timeline-notes` | The cohort view's own frame, sections and notes | `cohort-timeline.css` `.wpcpm-timeline-view`, `.wpcpm-timeline-notes` | The view's own layout (ruling 6) |
| `wpcpm-tools`, `wpcpm-tools__*`, `wpcpm-student__note` | The Tools section's anchor and today's grid of offers, drawn as today under the view's group title since task V1, ruling 5 (no frame or heading of its own; its buttons the button part); a claimed code | `dashboard.css` `.wpcpm-tools__*`; `administrator.css` `.wpcpm-student__note`; theme `.wpc-dashboard-page .wpcpm-student__note`, `.wpc-dashboard-page .wpcpm-tools__*`, `.wpc-administrator-page .wpc-main--app :is( .wpcpm-tools__offer, .wpcpm-tools__logo, ... )`; `assets/js/forms.js` reads `wpcpm-tools` | The offers as today, by the controller's ruling (task V1, ruling 5); the section the Report Cards draw too (ruling 6) |
| `wpcpm-dashboard__message`, `--success`, `--error`, `is-success`, `is-error` | The held message | `administrator.css` `.wpcpm-dashboard__message`; theme `.wpc-administrator-page .wpcpm-dashboard__message--success` and `--error` (`dashboard.css:1898`, `:1902`) | The shell's one message channel (ruling 6) |
| `wpcpm-badge wpcpm-badge--test` | The test mark beside a test institution's name, on every card | `dashboard.css` `.wpcpm-badge`, `.wpcpm-badge--test`; theme `.wpcpm-badge` | The one test mark the dashboards print (`WPCPM_Test_Institutions::MARK_DASHBOARD`) |
| `wpcpm-sevent__message` | The manager's own Join or Leave outcome | theme `.wpc-dashboard-page .wpcpm-sevent__message`, `.is-success`, `.is-error` (the body class every dashboard page carries) | Kept (controller's ruling): the event list's own outcome, drawn on the Report Cards too |
| `wpcpm-field__problem` | A field's refusal sentence, printed through `WPCPM_UI::field()` inside `.wpcpm-ui-field` | `parts.css` `.wpcpm-ui-field .wpcpm-field__problem`; `dashboard.css` `.wpcpm-field__problem` | Kept (controller's ruling): a part's own, printed by `WPCPM_Form_Errors` on every surface |
