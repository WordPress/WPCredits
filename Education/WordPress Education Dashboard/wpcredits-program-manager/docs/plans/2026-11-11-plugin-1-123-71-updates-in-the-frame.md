# Program updates in the frame (1.123.71): implementation plan

**Goal:** Program updates in the Administrator Dashboard's menu, each update in the frame with the others
listed beside it, and no Resources column on the Reference view.

**Spec:** `docs/specs/2026-11-11-program-updates-in-the-frame-design.md`. It binds.

## Tasks

- [x] **1. The menu and the Reference view.** `frame_args()` adds Program updates; `menu_args( $view )`
  replaces `guide_frame_args()`; `render_help()` and its stylesheet rule go; the lede and the screen
  reader's heading change.
- [x] **2. The updates.** `WPCPM_Updates::lists()` and `passes()`, shared with `posts()`;
  `includes/modules/class-wpcpm-administrator-updates-view.php`; the guide's script folds a list of other
  pages; `.wpcpm-guide__date` and `.wpcpm-guide__link--post`.
- [x] **3. The theme, 1.32.24.** `templates/single-administrator-update.html`, the theme support naming
  `administrator-updates`, `wpcredits_update_frame_template()` on `single_template_hierarchy`,
  `wpcredits_is_frame_page()` for an update in the frame.
- [x] **4. Tests.** `bin/test-administrator-updates.php` (new), `bin/test-updates.php`,
  `bin/test-administrators-dashboard.php`, `bin/test-sponsor-tools.php`, `bin/test-administrator-guide.php`;
  the theme's `bin/test-guide-frame.php`.
- [x] **5. Release.** The Administrator guide (`docs/sections/34`, rebuilt), the changelog, the version in
  every place, the .pot.

## Checks run

On the Docker copy, with two invented private updates, at 1440 and 375px: the update in the frame, one H1,
the list with the one being read marked, the folded list on a phone, no sideways scroll; the Reference view
with no Resources column.
