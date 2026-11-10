# The Administrator guide in the frame, one name per page (1.123.70): implementation plan

**Goal:** the Administrator Dashboard names itself once, and the Administrator guide opens in the dashboard's
frame with a list of its sections beside it.

**Spec:** `docs/specs/2026-11-11-administrator-guide-frame-design.md`. It binds.

## Tasks

- [x] **1. One name.** `WPCPM_Administrators_Dashboard::frame_args()` passes no `context`; `page_name()`
  returns its markup; `WPCPM_Cohort_Timeline_View::render_view( $report, $eyebrow )` opens with the frame's
  head and has no link back; the timeline's own head rules leave `cohort-timeline.css`.
- [x] **2. The guide's view.** `includes/modules/class-wpcpm-administrator-guide-view.php`
  (`WPCPM_Administrator_Guide_View`, started with the Administrators module), `assets/css/administrator-guide.css`,
  `assets/js/administrator-guide.js`; `WPCPM_Administrators_Dashboard::guide_frame_args()`.
- [x] **3. The guides' brackets.** `bin/build-docs.php` writes `&#91;`; the guides rebuilt.
- [x] **4. The theme, 1.32.23.** `templates/page-administrator-guide.html`, the theme support naming
  `administrator-guide`, `wpcredits_guide_frame_template()` on `page_template_hierarchy`,
  `wpcredits_is_frame_page()` for the guide, the dashboard sheet and faces loaded there, `guide.css` not.
- [x] **5. Tests.** `bin/test-administrator-guide.php` (new), `bin/test-administrators-dashboard.php`,
  `bin/test-cohort-timeline.php`, `bin/test-administrators-screen.php`; the theme's `bin/test-guide-frame.php`.
- [x] **6. Release.** The Administrator guide (`docs/sections/34`, rebuilt), the readme's changelog, the
  version in every place, the .pot.

## Checks run

On the Docker copy at 1440, 1024 and 375px and right to left: the guide in the frame, one H1, the list marking
the part being read and opening its section, the folded list on a narrow main area, no sideways scroll, no
text under 12px. The axe scan of the guide, the Overview and the cohort timeline at 1280 and 375px finds
nothing at or above serious; what it finds is the WordPress.org bar, footer and admin toolbar, as at 1.123.69.
