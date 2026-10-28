# Plugin 1.123.44: the Sponsor guide as a page on the site

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** The Sponsor Dashboard's guide button opens this site's own Sponsor guide, a page at `sponsor-guide` that each release republishes from the plugin's built guide (`docs/build/sponsors.html`), as the Institution guide has been since 1.123.36; until that page is published, the button keeps the handbook's sponsor guide, so installing the release changes nothing a sponsor sees.

**Architecture:** `WPCPM_Handbook_Assistant` already finds the Institution guide's page by its path and falls back to the handbook while it is not published (`institution_guide_url()`). The Sponsor guide's entry asks the same lookup with its own path and handbook address, through one helper both use. The page itself is made on the site by the release, not by the plugin.

**Tech Stack:** PHP 7.4+, WordPress 7.1; the plugin's `bin/test-*.php` suites.

**Spec:** WordPress/WPCredits#270, option 2 ("Publish the Sponsor guide as a page on the Education Directory, as the Institution guide has been since 1.123.36, so each release republishes it, and point the dashboard's button there"), chosen by the program's coordinator on 26 October 2026 and confirmed by the product owner on 28 October 2026.

## Global Constraints

- Version: plugin 1.123.44 (live is 1.123.43).
- No em or en dash anywhere; US English; "Administrators"; full product names (the Sponsor Dashboard); no person's name in code, tests, docs or commit messages.
- The Institution guide's button behaves exactly as before.
- Every suite stays green: 164 suites.

## Review Focus

1. No page at `sponsor-guide`, or a draft there, or one in the trash: the button keeps the handbook's sponsor guide.
2. A published page at `sponsor-guide`: the button opens that page's permalink, wherever permalinks point.
3. The Institution guide's button: unchanged in each of those states of its own page, and not confused by the sponsor page (one helper, two paths).
4. A filter on `wpcpm_handbook_guides` still sees both entries' final addresses.
5. The guides' README and the Administrator guide say where the Sponsor guide is published, as they say it for the Institution guide.

---

### Task 1: The Sponsor Dashboard's guide button opens the site's page

**Files:**
- Modify: `includes/tools/class-wpcpm-handbook-assistant.php` (a `SPONSOR_GUIDE_PATH` constant beside `INSTITUTION_GUIDE_PATH`; `institution_guide_url()` and the sponsor entry in `guides()` asking one shared helper)
- Modify: `docs/README.md`, and any line of `docs/sections/` that says where the Sponsor guide lives (find them with `grep -rn "Sponsor guide\|sponsor-guide\|institution-guide" docs/sections docs/README.md`), then `php bin/build-docs.php`
- Test: the suite that pins the Institution guide's button (find it with `grep -ln "INSTITUTION_GUIDE_PATH\|institution-guide" bin/test-*.php`)

**Interfaces:**
- Produces: `WPCPM_Handbook_Assistant::SPONSOR_GUIDE_PATH = 'sponsor-guide'`; the sponsor entry's `url` is that page's permalink while it is published, else `https://make.wordpress.org/community/handbook/education/credits/sponsor-guide/`.

- [ ] **Step 1: Write the failing tests:** each state of Review Focus 1 and 2 for the sponsor entry, and the same states for the institution entry with the sponsor page present and absent (**Review Focus 3**); the filter sees both final addresses (**Review Focus 4**).
- [ ] **Step 2: Run them red.**
- [ ] **Step 3: Implement.** One private helper, `published_guide_url( string $path, string $handbook ): string`, holding the lookup `institution_guide_url()` makes today and its docblock's reasons; `institution_guide_url()` goes or becomes a call to it, whichever reads better beside the rest of the file. The sponsor entry's comment says what the institution entry's says. The README and the guide lines say the Sponsor guide is this site's page at `sponsor-guide`, republished by each release, read by sponsors and Administrators (**Review Focus 5**).
- [ ] **Step 4: Run every suite, rebuild the guides, `php bin/check-spelling.php`.**
- [ ] **Step 5: Commit.** "Sponsor Dashboard: the guide button opens this site's Sponsor guide once its page is published"

### Task 2: Version, page, trial, release (the coordinator)

- [ ] 1.123.44: header, `WPCPM_VERSION`, readme Stable tag, changelog, `.pot`.
- [ ] Docker copy: the page made as the release makes it on live (title "Sponsor guide", path `sponsor-guide`, the `page-documentation` template, access level `wpcpm_sponsor`, content from the built guide); the button as a sponsor member before and after the page is published; the page refused to a visitor and to a student, shown to a sponsor member and to an Administrator.
- [ ] `release.py`: the page added to the guide pages in its config, so each deploy republishes it; check, build, scan; on the product owner's yes, the page made on live as a draft, the deploy (which writes its content), then published; then close #270.
