=== Contributor Team Matcher ===
Contributors: gomp, francescodicandia, celigaroe, peiraisotta
Tags: contributor, quiz, make wordpress, onboarding, community
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 1.0.10
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

An interactive quiz that helps new contributors find the right WordPress contribution team for their interests and skills.

== Description ==

Contributor Team Matcher presents contributors with a short quiz and uses a tag-based scoring algorithm to recommend the Make WordPress contribution team that best fits their profile. The top match is displayed as a prominent card; four runner-up teams are shown below it.

The plugin is fully configurable from the WordPress admin — questions, answers, teams, and tag weights can all be edited, added, or removed without touching code.

**How the matching algorithm works**

1. The contributor answers all quiz questions.
2. Each selected answer contributes a list of tags to a shared pool.
3. Tags are tallied (duplicates increase the count).
4. Every team is scored: `score += tag_weight x tag_count` for each matching tag.
5. Teams are sorted by score. The highest scorer is the primary recommendation; the next four are shown as secondary suggestions.

**Default teams**

The plugin ships with 23 Make WordPress contribution teams: Core, Design, Mobile, Accessibility, Polyglots, Support, Documentation, Themes, Plugins, Community, Meta, Training, Test, TV, Marketing, CLI, Hosting, Tide, Openverse, Photos, Performance, Sustainability, and Security.

== Installation ==

1. Upload the `find-your-team` folder to `/wp-content/plugins/`, or install the plugin through the **Plugins → Add New Plugin** screen in your WordPress admin.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Embed the quiz on any page or post using the `[conttema_quiz]` shortcode, or insert the "Contributor Team Matcher" block in the Block Editor.
4. Configure questions and teams under the **Contributor Team Matcher** top-level menu in the WordPress admin.

== Frequently Asked Questions ==

= How do I display the quiz? =

Add the shortcode `[conttema_quiz]` to any page, post, or widget, or insert the Contributor Team Matcher block in the Block Editor. The block is server-side rendered and shows a styled placeholder in the editor.

= Can I change the questions and teams? =

Yes. Everything is editable from the **Contributor Team Matcher** admin menu, which has three tabs: Questions, Teams, and Usage. No code changes are required. You can also reset questions and teams back to their defaults.

= Are single- and multi-select questions supported? =

Yes. Each question can be set independently to a single answer (radio) or multiple answers (checkbox, up to 3 selections).

= Does the plugin require jQuery? =

No. The frontend is written in vanilla JavaScript with no jQuery dependency.

== Changelog ==

= 1.0.9 =
* Removed the Mobile team from the default contribution teams.

= 1.0.8 =
* Fix: Corrected the text domain passed to `__()` in the Block Editor script (`blocks/contributor-team-matcher-quiz/index.js`) from `contributor-team-matcher` to `find-your-team`, so the block's editor strings match the domain declared in `block.json` and load translations correctly.

= 1.0.7 =
* Standardised the plugin text domain to `find-your-team` to match the WordPress.org slug.
* Renamed internal identifiers from the `CTM_` / `ctm_` prefix to `CONTTEMA_` / `conttema_` — constants, class names, include filenames, nonces, and AJAX actions on the PHP side, and the localized data object (`ctmData` to `conttemaData`) on the JavaScript side.
* No functional or behavioural changes; this is a naming/consistency release.

= 1.0.6 =
* Fix: The admin tab links (Questions / Teams / Usage) now point at the top-level `admin.php?page=contributor-team-matcher` panel instead of `options-general.php`, so navigating between tabs no longer bounces to the Settings screen.
* Docs: README updated to reference the top-level menu rather than "Settings → Contributor Team Matcher".

= 1.0.5 =
* Feature: Gutenberg block `contributor-team-matcher/quiz` registered via `block.json`. Editors can insert the quiz from the block inserter without typing the shortcode. The block is server-side rendered and shows a styled placeholder in the editor.
* Bump: Version synced across plugin header, version constant, and readme.

= 1.0.4 =
* Fix: Moved hidden quiz inputs inside their `<label>` elements so custom radio/checkbox indicators render correctly in all browsers.
* Fix: Extended CSS to hide native `input[type="radio"]` elements and style them with the custom dot indicator (circular for radio, square for checkbox).
* Fix: Switched CSS selectors to `:has()` now that inputs are descendants of labels rather than preceding siblings.
* Code quality: Full pass against WordPress Coding Standards (WordPress-Extra + WordPressVIPMinimum) — 0 errors, 0 warnings.
* Bump: Plugin header version synced with the version constant.

= 1.0.3 =
* Feature: Per-question Answer Type setting in the admin. Each question can be independently set to Single answer (radio) or Multiple answers (checkbox, up to 3).
* Admin UI: Answer Type toggle added to each question accordion item, with a "Single" / "Multi" badge in the accordion header.
* Frontend: Question rendering now branches on question type — radio questions render circular indicators with `role="radiogroup"`; checkbox questions keep the existing multi-select logic.

= 1.0.2 =
* Feature: Changed quiz from single-select radio buttons to multi-select checkboxes allowing up to 3 selections per question.
* All five default questions updated to support multiple selections.

= 1.0.1 =
* Fix: Escaped team icons before `innerHTML` insertion to prevent potential stored XSS.
* Fix: Synced the version constant with the plugin header version string.

= 1.0.0 =
* Initial release.
* Five-question quiz covering activity preference, skills, experience, passion, and working style.
* Tag-based scoring algorithm ranking 23 default Make WordPress contribution teams.
* Top match displayed as primary card; four runner-up teams shown in a grid.
* Full admin settings page (top-level "Contributor Team Matcher" menu) with Questions and Teams tabs.
* Accordion UI with add / edit / remove support for questions, answers, teams, and tag weights.
* Reset to defaults for both questions and teams.
* Usage tab with shortcode reference and copy button.
* Vanilla JavaScript frontend — no jQuery dependency.
* Accessible: ARIA labels, keyboard navigation, focus management, `role="radiogroup"` / `role="group"` on answer lists.

== Upgrade Notice ==

= 1.0.9 =
Removes the Mobile team from the default contribution teams.

= 1.0.8 =
Fixes the Block Editor text domain so block editor strings are translatable.

= 1.0.7 =
Standardises the plugin text domain and WordPress.org slug to find-your-team.
