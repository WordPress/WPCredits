=== WordPress Community CRM ===

Contributors: gomp
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
Tags: full-site-editing, block-patterns, one-column, two-columns, three-columns, custom-colors, custom-logo, custom-menu, block-styles, editor-style, wide-blocks, translation-ready

A block theme for the WordPress Community CRM, the internal tool organizers use
to keep track of the people, organizations and sponsors around meetups and
WordCamps. The front page doubles as the sign-in screen.

== Description ==

The theme renders the Community CRM homepage design: a single card holding an
illustrated hero, the WordPress.org sign-in form and a request-access panel,
framed by a thin header bar, under the official wordpress.org global header, with
the wordpress.org global footer closing the page.
— centred, in monospace.

Everything is built on WordPress Design System (WPDS) tokens — brand blue
#3858e9, 13px system type, a 4px spacing base and 2px control radius — mapped
into theme.json so the whole design is editable in the Site Editor without
touching CSS.

= What it gives you =

* A front page that works as the login page, with a real WordPress login form.
* Failed sign-ins that stay on the homepage instead of bouncing to wp-login.php.
* A matching skin for wp-login.php, so both routes look like the same product.
* Templates for pages, posts, archives, search and 404, all in the same card idiom.
* Patterns for the hero and the sign-in card.
* No build step. No bundled JavaScript framework. One 74 KB WebP illustration.

== Installation ==

1. Copy the `community-crm` folder into `wp-content/themes/`, or upload a zip
   of it via Appearance -> Themes -> Add New -> Upload Theme.
2. Activate it under Appearance -> Themes.
3. Under Settings -> Reading, leave "Your homepage displays" on either setting.
   The `front-page.html` template is used for the homepage either way.

That is the whole setup. The sign-in form appears on the homepage immediately.

== The sign-in block ==

The form is a block: **CRM sign-in** (`community-crm/login-form`), in the Theme
category of the inserter. It is registered in PHP with a render callback, so
there is nothing to compile; the editor previews the real PHP output.

Block settings:

* **Heading** — defaults to "Sign in".
* **Hint text** — optional line under the heading. Empty by default, and the
  panel renders no hint at all unless you set one.
* **Show the request-access panel** — the panel on the right.
* **Request access heading / intro / button URL** — where the button points.
  Leave the URL empty to link to the panel's own anchor (`#access`).

A `[community_crm_login]` shortcode renders the same panel in classic content:

    [community_crm_login heading="Sign in" show_access="no"]

Someone who is already signed in sees their name, an "Open the CRM" button and
a "Sign out" link instead of the form.

== Behaviour and filters ==

Post-sign-in destination. Defaults to the Jetpack CRM dashboard when that
plugin is active (`ZBS_ROOTFILE` is defined), otherwise wp-admin:

    add_filter( 'ccrm_login_redirect_url', function () {
        return home_url( '/contacts/' );
    } );

The page hosting the form. Defaults to the front page; change it if you move
the form onto a dedicated page:

    add_filter( 'ccrm_login_page_url', function () {
        return home_url( '/sign-in/' );
    } );

Where "Request access" points:

    add_filter( 'ccrm_request_access_url', function () {
        return 'https://make.wordpress.org/community/';
    } );

Send wp-login.php to the themed homepage. Off by default on purpose:
wp-login.php stays reachable as the safety net if the front-page form is ever
edited away, and password resets and interim logins keep working either way.
Turn it on once you are happy with the homepage:

    add_filter( 'ccrm_redirect_wp_login', '__return_true' );

Single sign-on. The theme renders the standard WordPress login form; it does
not itself authenticate against WordPress.org. If you add an SSO plugin, print
its button into the panel and the theme adds an "or" divider above the
username and password fields:

    add_action( 'ccrm_login_form_sso', function () {
        echo do_shortcode( '[wporg_sso_button]' );
    } );

There is also a `ccrm_login_form` action inside the form element, mirroring
core's `login_form`, for two-factor and similar plugins.

Failed attempts are reported generically ("That username and password
combination is not correct.") so the form never confirms whether an account
exists. `redirect_to` values are always passed through
`wp_validate_redirect()`.

== Templates ==

* **front-page** — the sign-in screen: illustrated hero and the sign-in form.
* **index** — the post list.
* **archive**, **search**, **single**, **page**, **404**.
* **Page — no title** (`page-plain`) — full-bleed page content, no card.
* **Page — centered card** (`page-centered`) — a 560px card centred vertically
  in the viewport. Good for short notices. (The sign-in screen itself is
  top-aligned; only this template and 404 centre.)

== Patterns ==

* **Sign-in card (hero + form)** — the whole front-page card.
* **Illustrated hero** — the hero panel on its own.
* **Sign-in panel** — the form and access panel in a standalone card.

== Customising ==

The palette, type scale and spacing scale live in theme.json and are editable
under Appearance -> Editor -> Styles. The component CSS — the shell layout,
the hero scrim, the form controls — is in style.css, loaded on the front end
and passed to `add_editor_style()` so the editor canvas matches.

To swap the hero illustration, replace
`assets/images/hero-network.webp` (2048x768) or override `.ccrm-hero`'s
`background-image` in a child theme.

The brand mark in the header is an inline SVG in `parts/header.html`. The
theme also declares `custom-logo` support, so you can add a Site Logo block
and delete the SVG if you would rather upload a mark.

== Accessibility notes ==

* The hero's white scrim keeps the headline and lede over the opaque part of
  the gradient; the copy is capped at the widths the design specifies rather
  than running under the illustration.
* Form fields use a #8d8d8d stroke (3.0:1 against white) with a 2px brand
  focus ring, per WPDS.
* A skip link is printed at `wp_body_open` and targets the `<main>` element in
  every template.
* Error notices carry `role="alert"`; status notices carry `role="status"`.

== Notes ==

The theme name matches the internal product name. If this is ever published on
WordPress.org, the name will need changing — theme names there cannot include
"WordPress".

== Changelog ==

= 1.2.1 =
* The header bar's contents now line up with the content column below it, instead of sitting in the window's own gutter.

= 1.2.0 =
* Adds the official wordpress.org global header and footer, from the WordPress.org Global Header and Footer plugin, so the site is framed the way every WordPress.org property is.
* Removes the theme's own footer bar. Its "Code is poetry." line is in the global footer already, and with the bar gone the sign-in screen no longer reserves height for it.

= 1.1.5 =
* Security: sign-in failures no longer reveal whether an account exists. The raw
  WordPress error code was passed through in the redirect URL, so `invalid_username`
  and `incorrect_password` were distinguishable there even though both render the
  same generic message on the page. Authentication failures now all report as
  `failed`; only codes that disclose nothing — the empty-field, expired and denied
  cases — are still passed through.

= 1.1.4 =
* The sign-in screen's footer bar is now fixed to the bottom edge of the browser
  window, so it tracks the window as it is resized and stays visible while the
  page scrolls. Being out of flow, it can no longer be pushed down the page by
  the card above it, and no page background is left below it.
* Reserved the footer's height at the foot of the shell so the card can never
  end up hidden behind it on a short window — verified down to 400px tall, and
  on phones where the stacked layout scrolls.
* Other templates keep the footer in normal flow, where a permanently fixed bar
  would get in the way of long content.

= 1.1.3 =
* Moved the footer up on the sign-in screen. Its <main> no longer stretches to
  fill the viewport, so the footer bar follows the card directly — a constant
  16px gap (24px on tall viewports), matching the gap above the card. Stretching
  had left 115px between card and footer at 900px tall, and 599px at 1440px.
* Leftover viewport space now sits below the footer as page background instead
  of between the card and the footer.
* Other templates keep the stretching middle, so their footer still sits at the
  bottom of the window on short pages.

= 1.1.2 =
* Tightened the space around the sign-in card. The card is no longer vertically
  centred: centring split all the leftover viewport space evenly above and
  below it, so the gap under the header grew with the window — 66px at 900px
  tall, 132px at 1080px, 312px at 1440px. It is top-aligned now, so that gap is
  a constant 24px (16px on short viewports) on every screen, and the slack
  collects above the footer bar instead.
* Reduced the padding around the card from 32px to 24px, and from 24px to 16px
  on viewports under 900px tall.
* The 404 and "centered card" page templates still centre their content, which
  suits their short content.

= 1.1.1 =
* Fixed the scrollbar on the sign-in screen. The composition was 967px tall,
  so it overflowed most laptop viewports. With the hero buttons gone the hero
  was also badly over-padded — 238px of copy inside 144px of padding and a
  420px floor. Trimmed the hero, main and sign-in paddings so the whole screen
  now fits any viewport 820px tall or more.
* Moved the hero and main vertical padding out of the templates' inline styles
  and into style.css, so it can respond to viewport height. This also removed
  the !important the mobile hero rule previously needed.
* Added a short-viewport step at max-height 900px that tightens the three
  vertical paddings.
* Switched the page shell to 100dvh (with a 100vh fallback) so mobile browsers
  no longer reserve height for a collapsing URL bar.
* Renamed spacing preset 9 from "Hero" to "5X large" — the hero no longer uses
  it now that its padding lives in CSS.

= 1.1.0 =
* Removed the "Sign in" and "Request access" buttons from the hero. The sign-in
  form sits directly below in the same card, so the hero is copy only.
* Removed the three-up People / Organizations / Handover row and its pattern.
* Removed the footer line "Sign in with your WordPress.org account." from both
  the front page and the wp-login.php skin. "Code is poetry." is now centred.
* Removed the sign-in panel's default hint text. The block's Hint field is
  still there; the panel renders a hint only when one is set.
* Reworded the hero lede: organizers keep track of people, organizations and
  sponsors.

= 1.0.0 =
* Initial release. Front page as sign-in screen, matching wp-login.php skin,
  CRM sign-in block and shortcode, WPDS tokens in theme.json, templates for
  pages, posts, archives, search and 404, and four patterns.
