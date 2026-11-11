# The Sponsor Dashboard in the frame (design, 11 November 2026)

Plugin: WPCredits Program Manager 1.123.78, with WPCredits theme 1.32.27.

## 1. Why

The product owner asked for the version 3 concepts to be built one dashboard at a time: the Administrator
Dashboard first (live since 1.123.69 to 1.123.71), then the Sponsor Dashboard, the Institution Dashboard, and the
Mentor and Student Report Cards. On 11 November 2026 the owner asked for the Sponsor Dashboard next.

The concept's reading of today's page: the New offer form is always open and takes a third of the page; the
offers are triangle folds with a "(primary)" label; "Mentors looking for a sponsor" is the page's one invitation,
hidden in a fold; nothing on the page says what the company's support did. The concept leads with that
difference, draws the offers as the homepage's program cards, puts the New offer form behind a button, and gives
each part of the page its own view in the frame.

## 2. Decisions the owner already made, kept here

From the frame's design (10 and 11 November 2026, `2026-11-10-dashboard-frame-design.md`):

1. Full width with a side menu, on the left in a left-to-right language and on the right in a right-to-left one.
2. The concepts as drawn, not a restyle of the old cards.
3. The Ask box goes down the side menu, under Help.
4. What waits is a status tag in words on a plain card, never a colored edge. The concept's red edge on the offer
   that waits is drawn as that tag.
5. One name per page: the H1 is the one place the dashboard's name shows; the menu does not repeat it and no view
   has a back link.
6. Each menu item is its own view with its own address, and every link to a card goes to the view that holds it.
7. The dashboard's own guide moves into its frame, its sections listed on the right; the program's updates are a
   menu item, each update drawn in the frame with the others listed beside it.
8. Release one dashboard at a time.

From the owner's answers of 29 September 2026: the two-tile strip stayed tile-sized until the tiers launched. The
tiers launched on 21 October 2026, so the figures band replaces the strip now.

## 3. The views

By `?wpcpm_view=`, as the Administrator Dashboard's. The address of the page with no argument is the Overview, so
every link to the page that exists today still lands.

| Menu item | View | What it holds |
|---|---|---|
| Overview | (none) | The head, the figures, what waits, the active offers as cards, the mentors door |
| Your sponsorship | `sponsorship` | The tier and what it includes, the events, the reports, the badge. Only while the tiers are launched (`WPCPM_Sponsor_Tiers::live()`) |
| Offers | `offers` | New offer behind a button, every offer as a card that opens to its details and forms, the ended offers, the company's posts |
| Students reached | `reached` | The claims: how many people each offer reached, this month and in all. Numbers only, never names |
| Mentors to sponsor | `mentors` | The mentors the company sponsors and the ones looking for a sponsor |
| Your company | `company` | The profile and logo, the people who act for the company |
| Agreement | `agreement` | The Collaboration Agreement |

**Builder's call:** Your sponsorship is in the menu although the concept leaves it out. The concept was drawn as if
the tiers had not launched (its table puts the figures band "after the tiers launch"); they have, and the section
is on the page today.

**Builder's call:** the four sections stop being movable. With a view per menu item there is no long page to
arrange; the menu's order is the concept's. A company's saved order is left in its option, unused.

## 4. The head

- The H1 is the dashboard's name, "Sponsor Dashboard", as on the Administrator Dashboard, with "sponsor since
  <month year>" after it when the record knows the date (its `created` day).
- On the Overview the title is the company's name, an h2 for the heading order, beside its logo (or its
  initials), and the lede thanks the company with what its support did: "Thank you. This year your offers reached
  37 people. Your company sponsors 2 mentors." Each sentence only when its number is above zero; with both at
  zero the lede says what the page is for. **Builder's call:** "people", not "students", since an offer may be
  open to mentors and the program team too; and two sentences, one plural each, for the translators.
- **Builder's call:** the logo's box is 88px high and as wide as a wordmark needs, up to 220px, since most of the
  companies' logos are wide; on a narrow main area it stands over the head's copy.
- New offer, a secondary button, opens the Offers view with the form open.
- On every other view the title is the view's name and the lede one sentence on what it holds.
- An Administrator's "Viewing as sponsor" control stays above the head, on every view, and keeps the sponsor when
  the reader moves between views.

## 5. The Overview

- **Figures:** the homepage's band, three cells: people reached this year (each person who claimed one of the
  company's offers this year, once), codes claimed of the codes loaded, with the codes left in the live offer
  closest to running out, and the mentors the company sponsors, with how many have students now. One line under
  the band says when the numbers were read. A cell with nothing to count is left out rather than drawn as a zero.
  **Builder's call:** "with students now" in place of the concept's "active in the last 30 days": the mentor
  records say who mentors whom, and reading every mentor's sign-ins for one sponsor's page would cost the whole
  engagement report.
- **What waits:** each thing the At a glance strip counted today (an offer returned with a note, running low, not
  switched on; a post returned; an event returned; the agreement returned or revoked; profile details missing; a
  term in its last 30 days or ended) is a plain row with its status tag in words, the sentence that says why, and
  one action that opens the view and card that hold it. Nothing waiting is one quiet line.
- **Your offers:** each offer not ended as a program card: its state tag (Live in green, Not switched on yet and
  Waiting for review in amber, the rest plain), its title, what you get, its facts (claimed of the codes loaded,
  codes ready, or people who took a shared code; the last day; who it is open to; Running low, or the warning
  mark while it is not live) and the claims a month, small bars for the last six months from the claims' own
  monthly series, while any of them had a claim. Then "Change the offer" (the Offers view, that offer open) and,
  once anybody claimed it, "See the claims" (Students reached). **Builder's call:** "See the claims" in place of
  the concept's "Who claimed it", since the Usage card names nobody.
- **The mentors door:** "38 mentors are looking for a sponsor" with "See who", while any are.

## 6. The other views

The cards keep what they hold and every form they post. Inside the frame they take the Administrator Dashboard's
card look: one 1px box per row with 2px corners, Inter, the row's state on the right, buttons and fields in Inter
with 2px corners.

- **Offers:** New offer is a button; the form opens in place under it and stays open after a refused save so the
  typing is kept. Each offer's folded row is its program card (status tag, title, facts); open, it holds today's
  details, Edit this offer, the state buttons and the codes. "(primary)" is no longer printed.
- **Every card open (1.123.80):** the owner, "by default the tabs with information should be open, not closed". Every card on a view is drawn open (`WPCPM_Dashboard_Card::unfold()`); the disclosures inside a card, the New offer and Edit this offer forms and each offer's row, keep their own state.
- **Students reached:** the usage card, by offer.
- The rest as today, one view each.

## 7. Help

Under Help: the Sponsor guide, drawn in the frame with its sections on the right; Write to the program, the
company's contact at the program (a mail link), when one is assigned; and the Ask box. Program updates is a menu
item while there is an update a sponsor may read, each update drawn in the frame with the others beside it. The
Resources block under the strip goes: its links are the menu's.

### 7.1 An Administrator reading a sponsors' update (1.123.79)

The owner found the sponsors' welcome post outside the dashboard: in 1.123.78 only a sponsor's own people read
an update for sponsors in the frame, and an Administrator, the sponsor menu's Program updates included, got the
post as the theme draws any post. Now an Administrator reads in the Sponsor Dashboard's frame an update the
Sponsor Dashboard lists when the Administrator Dashboard does not list it, or when the address names a sponsor
(`wpcpm_sponsor_view`); an update both list, opened with no sponsor named, stays in the Administrator
Dashboard's frame. Program updates in the sponsor menu and every link in the list beside an update keep the
sponsor an Administrator is viewing, as the Sponsor guide's link does. A member's links name no sponsor.

## 8. Links to a card

Every link to a Sponsor Dashboard card goes through one routine that knows which view holds the card, as
`WPCPM_Administrators_Dashboard::card_url()` does. A card's handler returns to that view with its outcome in the
card. Old links to the page with only an anchor still land on the Overview.

## 9. Not in this release

The Institution Dashboard and the two Report Cards; a sponsor arranging the menu; new screenshots for the Sponsor
guide (the three of the old page are taken out of it).
