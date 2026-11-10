# The dashboards' frame on the Administrator Dashboard (1.123.69): implementation plan

**Goal:** the Administrator Dashboard in the version 3 concepts' frame, full width with a side menu, its
Overview, Watch and Reference, and what waits marked by the homepage's status tag.

**Spec:** `docs/specs/2026-11-10-dashboard-frame-design.md`. It binds.

## Tasks

- [x] **1. The frame.** `includes/class-wpcpm-dashboard-frame.php`, `assets/css/dashboard-frame.css`,
  `assets/js/dashboard-frame.js`; the menu's icons in `WPCPM_Icons`; loaded with the plugin and at uninstall.
- [x] **2. The card shell.** `WPCPM_Dashboard_Card::age()` (the row's age as `wpcpm-status--red`) and `fold()`
  (cards drawn folded unless one holds the outcome), both cleared by `forget()`.
- [x] **3. The page.** `WPCPM_Administrators_Dashboard::render()` in the frame: `view()`, `view_url()`,
  `queues()`, `waiting()`, `since()`, `frame_args()`, the head, the figures, the Waiting rows and the quiet
  sentence, Watch, and the Reference view.
- [x] **4. The cohort timeline.** Its tiles, flags and outlook as status tags in words, no colored edges, no
  text under 12px, room above the tallest month for its flag.
- [x] **5. The theme, 1.32.22.** The bare canvas template, the theme support, the two typefaces on the page,
  and the frame's card rules in `dashboard.css`, all scoped to the frame.
- [x] **6. Tests.** `bin/test-administrators-dashboard.php` on the new page and its views;
  `bin/test-dashboard-card.php` on `fold()` and `age()`.
- [x] **7. Release.** The Administrator guide (`docs/sections/30`, `31`, `34`, rebuilt), the readme's
  changelog, the version in every place, the .pot; DESIGN.md's "What waits" rule.

## Checks run

On the Docker copy at 1920, 1440, 1024 and 375px, the drawer, right to left and every card open: no sideways
scroll, no text under 12px, every text in Inter but the serif head, no colored edge, no corner above 2px but
the floating account menu, no console or PHP error from the plugin. Every plugin and theme suite, the theme's
selector check and the coding standards on the changed files pass.
