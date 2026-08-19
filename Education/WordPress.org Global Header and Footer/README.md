# WordPress.org Global Header and Footer

A single plugin, shared by all three education sites: it puts the **official wordpress.org header
and footer** — the black bar with the W mark, and the dark footer that ends every wordpress.org
page — on a site that is not part of the wordpress.org network.

| | |
| --- | --- |
| Plugin | `wporg-global-header-footer` |
| Version | 1.0.0 |
| Requires | WordPress 6.6, PHP 7.4 |
| Used by | [Education Dashboard](../WordPress%20Education%20Dashboard), [Education Initiatives](../WordPress%20Education%20Initiatives), [Community CRM](../WordPress%20Community%20CRM) |

## Where the code comes from

`vendor/global-header-footer/` is the WordPress.org project's own code, **copied unmodified** from
`mu-plugins/blocks/global-header-footer` in
[WordPress/wporg-mu-plugins](https://github.com/WordPress/wporg-mu-plugins). The commit it was taken
from is recorded in `vendor/UPSTREAM_SHA`, and `bin/update-vendor.sh` refreshes it.

Two things are worth knowing before relying on it:

- That repository is the network's **internal** code. Its own README warns that changes there "must
  be tested on all sites" — it carries no compatibility promise to anyone outside the network, so
  re-vendoring is something to do deliberately and then check, not on a schedule.
- The `build/` directory is **not in the repository** — it is a build artifact. `update-vendor.sh`
  fetches the compiled `style.css` and the two `block.json`/`index.js` pairs from `s.w.org`, which
  is where wordpress.org itself serves them from. Without them the blocks do not register at all,
  because `register_block_types()` reads `build/header/block.json`.

Nothing in `vendor/` is edited, so an update is a copy rather than a merge.

## What the plugin file adds

Only what the copy needs in order to run off-network — which is less than it looks, because the
Rosetta paths all sit behind `is_rosetta_site()` and are simply unreachable here, the global menu is
a list of absolute URLs written into the source, and the logos are local files.

- **`fill_server_globals()`** — the vendored code reads `SERVER_NAME`, `HTTP_HOST` and `REQUEST_URI`
  without checking they exist. On a web request they are all set. Under WP-CLI none of them are, and
  the undefined-key warnings land in the middle of whatever a cron job was printing.
- **`classic_theme_assets()`** — a block theme renders its template before `wp_head()`, so the
  stylesheet enqueued during render still makes it into `<head>`. A Classic theme calls
  `get_header()` *after* `wp_head()` has run, so the same enqueue would be printed in the footer and
  the page would load unstyled and then snap into place. Classic themes get the style up front.
- **A double-registration guard** — a site actually on the wordpress.org network already has these
  blocks from the real mu-plugin, and registering them twice is a `_doing_it_wrong` on every request.

## Putting them on a site

**A block theme** places them as template parts. Add `parts/wporg-header.html` containing
`<!-- wp:wporg/global-header /-->` and `parts/wporg-footer.html` containing
`<!-- wp:wporg/global-footer /-->`, register both in `theme.json`, then reference them at the top and
bottom of every template:

```html
<!-- wp:template-part {"slug":"wporg-header","className":"has-display-contents","theme":"your-theme"} /-->
…
<!-- wp:template-part {"slug":"wporg-footer","className":"has-display-contents","theme":"your-theme"} /-->
```

`has-display-contents` is the class wordpress.org's own pages carry on these wrappers. WordPress
ships the class name but not the rule, so the theme needs one line of CSS for it — without it the
wrapper is a box in the flow and takes a `.wp-site-blocks` gap, which prints as a stray band above
the header:

```css
.wp-block-template-part.has-display-contents { display: contents; }
```

**A Classic theme** calls the two template tags instead — `wporg_global_header()` in `header.php`
after `wp_body_open()`, and `wporg_global_footer()` in `footer.php` before `wp_footer()`. Guard both
with `function_exists()` so the theme still renders if the plugin is deactivated.

## Notes

- The header's search field posts to `https://wordpress.org/search/do-search.php`, hard-coded in the
  vendored template. It searches wordpress.org, not the site it appears on — which is what the
  official header does everywhere.
- A **database-customised template** does not pick up an edit to the theme's template file. If a
  site's front page was ever edited in the Site Editor, WordPress serves that saved copy instead and
  the header has to be added to it as well.
