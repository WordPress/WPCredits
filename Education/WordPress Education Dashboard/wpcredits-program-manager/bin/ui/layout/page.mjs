// The layout harness's page: every case at every width, in the dashboards' own frame and sheets.
//
//   page(cases, widths, { pluginDir, themeDir, off, surface })
//
// cases is a list of { name, html, surface } (or an object { name: html }). Each case at each width is drawn in a
// main area of exactly that width, as a container of the frame (container:wpcpm-main/inline-size), so the
// sheets answer to the area's width and not the window's. `off` leaves out wpcpm-ui-on, for an old branch.
//
// Each case is drawn on its surface (SURFACES below: the dashboard's frame, root and sheets), and with a theme the
// page is that dashboard's page as the site prints it: the body carries the page's classes, the cases sit in the template's wrappers (.wp-site-blocks, the main group .wpc-main--app and the
// post content .wpc-app__content), and WordPress's own sheets are in their place: the block library's
// (block-library.css) and the global styles WordPress prints from the theme's theme.json (global-styles.css),
// both written by refresh-global-styles.mjs and checked here against the theme.json they were made from.
import { createHash } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '../../..');
// The surfaces a case is drawn on: the three dashboards in the plugin's frame. Each as its page prints it:
//
// - the frame, as `WPCPM_Dashboard_Frame::open()` prints it: `.wpcpm-frame.wpcpm-frame--<page>` (with the switch's
//   class while the surface is live, `WPCPM_UI::LIVE`: the Administrator and Institution Dashboards, not the
//   Sponsor Dashboard), then `.wpcpm-frame__body`, then `.wpcpm-frame__main`, whose container `wpcpm-main` the
//   sheets' container queries name (dashboard-frame.css sets it; the harness sets it on the element too). The top
//   bar and the menu are not drawn: the bar sits above the body, the menu beside the main area, and no rule of the
//   main area keys on either;
// - the page's root inside the main area, as the dashboard's `render()` prints it: `.wpcpm-dashboard.wpcpm-institution`,
//   `.wpcpm-dashboard.wpcpm-administrator.wpcpm-administrator--<view>`, `.wpcpm-dashboard.wpcpm-sponsor.wpcpm-sponsor--<view>`
//   (the view `overview` unless the surface names another: `administrator/watch`). A case whose markup starts with
//   a dashboard's root (the suites' `main_html()`) is that root, and must be the surface's own;
// - the body classes and the template's wrappers of templates/page-<page>-dashboard.html.
//
// The sheets, in the order each page prints them. The theme is a block theme, so the page's template, and with it
// the dashboard's render callback, runs before wp_head: what the render enqueues is first in the queue, each sheet
// after its dependencies (parts.css, then the mentor dashboard's dashboard.css, which every dashboard's sheet names;
// core's dashicons.css before them is not drawn). Then WordPress's own sheets, where the site prints them (read from
// a page of the copy, 16 Nov 2026): the styles of the core blocks the template renders (the main group, the post
// content), enqueued as each block finishes, after the plugin's block inside them, then the block library's shared
// sheet, then the global styles block. Then wp_head runs the theme's `wpcredits_assets()` (style.css, after the
// global styles) and `wpcredits_dashboard_assets()` (at 20): the dashboard sheets the plugin registers on init and
// the page has not printed yet, in the order the theme names them (student.css, institution.css, administrator.css,
// sponsor.css), then the theme's assets/css/dashboard.css, which depends on them all, then hub.css, which a page in
// the frame loads for its faces (Inter and EB Garamond) and whose other rules are scoped to the hub's classes. (The
// call calendar's and the triage's sheets print there too; each is scoped to markup these cards do not print, and is
// left out.)
//
// - Institution Dashboard: render() enqueues institution.css, open() dashboard-frame.css, the Semester reports card
//   report-print.css.
// - Administrator Dashboard: render() enqueues administrator.css and the cohort timeline's cohort-timeline.css
//   (which depends on it), then open() dashboard-frame.css; institution.css prints later, among the theme's.
// - Sponsor Dashboard: render() enqueues sponsor.css, which depends on administrator.css, then open()
//   dashboard-frame.css; institution.css prints among the theme's.
// The Student and Mentor Report Cards are not drawn in the frame (their render calls no `open()`, and their templates
// are the theme's .wpc-main--dashboard card): the harness, whose widths are the frame's main area's, does not draw them.
const WORDPRESS = [['fixture', 'block-library.css'], ['fixture', 'global-styles.css'], ['theme', 'style.css']];
const THEME_TAIL = [['theme', 'assets/css/dashboard.css'], ['theme', 'assets/css/hub.css']];
const plugin = (...f) => f.map(x => ['plugin', x + '.css']);
export const SURFACES = {
	institution: {
		label: 'Institution Dashboard', page: 'institution', live: true, view: '',
		sheets: [...plugin('parts', 'dashboard', 'institution', 'dashboard-frame', 'report-print'), ...WORDPRESS, ...plugin('student', 'administrator', 'sponsor'), ...THEME_TAIL],
	},
	administrator: {
		label: 'Administrator Dashboard', page: 'administrator', live: true, view: 'overview',
		sheets: [...plugin('parts', 'dashboard', 'administrator', 'cohort-timeline', 'dashboard-frame'), ...WORDPRESS, ...plugin('student', 'institution', 'sponsor'), ...THEME_TAIL],
	},
	sponsor: {
		label: 'Sponsor Dashboard', page: 'sponsor', live: false, view: 'overview',
		sheets: [...plugin('parts', 'dashboard', 'administrator', 'sponsor', 'dashboard-frame'), ...WORDPRESS, ...plugin('student', 'institution'), ...THEME_TAIL],
	},
};
// What a case drawn without a surface is drawn on, when it is handed to page() directly. dump.php names a surface
// for every case it keeps (bin/ui/layout/dump.php), so a suite's case is never drawn on this by default.
export const DEFAULT_SURFACE = 'institution';
const SHEETS = SURFACES.institution.sheets;
const ALL = sheets => sheets.filter(([from]) => 'plugin' !== from);
const PLUGIN_SHEETS = [...new Set(Object.values(SURFACES).flatMap(x => x.sheets).filter(([from]) => 'plugin' === from).map(([, f]) => f))];
const THEME_SHEETS = ALL(SHEETS).filter(([from]) => 'theme' === from).map(([, f]) => f);
const FIXTURE_SHEETS = ALL(SHEETS).filter(([from]) => 'fixture' === from).map(([, f]) => f);

/**
 * A surface's name as a case gives it (`administrator`, `administrator/watch`), as { key, view, spec }: throws on
 * a surface the harness does not draw.
 */
export function surfaceOf(name) {
	const [key, view] = String(name || DEFAULT_SURFACE).split('/');
	if (/^(student|mentor)/.test(key)) throw new Error(`surface "${name}": the Report Cards are not drawn in the frame, and the harness does not draw them`);
	const spec = SURFACES[key];
	if (!spec) throw new Error(`surface "${name}" is not one the harness draws (${Object.keys(SURFACES).join(', ')})`);
	if (view && !spec.view) throw new Error(`surface "${name}": the ${spec.label}'s root names no view`);
	return { key, view: view || spec.view, spec };
}
const HERE = dirname(fileURLToPath(import.meta.url));
export const REFRESH = 'WPCPM_THEME_DIR=<theme folder> node bin/ui/layout/refresh-global-styles.mjs';

/**
 * What the global styles fixture was made from (global-styles.json), checked against the theme folder's
 * theme.json: throws, naming the refresh command, when the fixture is missing or was made from another
 * theme.json.
 */
export function globalStylesFor(themeDir, fixtureDir = HERE) {
	const themeJson = join(themeDir, 'theme.json');
	const meta = join(fixtureDir, 'global-styles.json');
	if (!existsSync(themeJson)) throw new Error('the theme folder has no theme.json: ' + themeDir);
	if (!existsSync(meta) || FIXTURE_SHEETS.some(f => !existsSync(join(fixtureDir, f)))) throw new Error('the global styles fixture is missing; make it with: ' + REFRESH);
	const source = JSON.parse(readFileSync(meta, 'utf8'));
	const sha = createHash('sha256').update(readFileSync(themeJson)).digest('hex');
	if (source.theme_json_sha256 !== sha) {
		throw new Error(`the global styles fixture is stale: it was made from a theme.json with sha256 ${String(source.theme_json_sha256).slice(0, 12)} (theme ${source.theme_version}), and ${themeJson.replace(themeDir, '<theme>')} is ${sha.slice(0, 12)}; refresh it with: ${REFRESH}`);
	}
	return source;
}

/** The theme's slug, as the body class wp-theme-<slug> names it: its Text Domain. */
function themeSlug(themeDir) {
	const m = /^\s*Text Domain:\s*(\S+)/m.exec(readFileSync(join(themeDir, 'style.css'), 'utf8'));
	return m ? m[1] : 'wpcredits-theme';
}

const esc = s => String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

/**
 * The sheets a page is told to load, as file paths, in the surface's order. A plugin sheet that is missing stops the
 * run; so does a theme folder that has style.css but not its dashboard sheet. A theme folder with no style.css at
 * all is skipped (the caller prints `no theme`).
 */
export function sheetsFor({ pluginDir = REPO, themeDir = process.env.WPCPM_THEME_DIR || join(homedir(), 'GitHub/wpcredits-theme'), fixtureDir = HERE, surface = DEFAULT_SURFACE } = {}) {
	const { spec } = surfaceOf(surface);
	const plugin = PLUGIN_SHEETS.map(f => join(pluginDir, 'assets/css', f));
	const missing = plugin.filter(f => !existsSync(f));
	const haveTheme = existsSync(join(themeDir, 'style.css'));
	const theme = haveTheme ? THEME_SHEETS.map(f => join(themeDir, f)) : [];
	missing.push(...theme.filter(f => !existsSync(f)));
	if (missing.length) throw new Error('style sheet not found: ' + missing.map(f => f.replace(pluginDir, '<plugin>').replace(themeDir, '<theme>')).join(', '));
	// WordPress's sheets come with the theme: the global styles are its theme.json's, so a stale fixture stops the run.
	const globalStyles = haveTheme ? globalStylesFor(themeDir, fixtureDir) : null;
	const fixture = haveTheme ? FIXTURE_SHEETS.map(f => join(fixtureDir, f)) : [];
	// Every sheet in the surface's own order, the theme's and WordPress's left out when there is no theme.
	const at = { plugin: f => join(pluginDir, 'assets/css', f), theme: f => join(themeDir, f), fixture: f => join(fixtureDir, f) };
	const ordered = spec.sheets.filter(([from]) => haveTheme || 'plugin' === from).map(([from, f]) => at[from](f));
	return { plugin, theme, fixture, ordered, haveTheme, globalStyles, slug: haveTheme ? themeSlug(themeDir) : '' };
}

/**
 * The body classes a dashboard's page carries for a signed-in reader (WordPress's for a page on the
 * page-<page>-dashboard template, then the theme's from wpcredits_body_class()), without the toolbar's admin-bar,
 * since the toolbar is not drawn.
 */
export function bodyClasses(slug, surface = DEFAULT_SURFACE) {
	const { spec } = surfaceOf(surface);
	return [`page-template-page-${spec.page}-dashboard`, 'page', 'wp-singular', 'logged-in', 'wp-embed-responsive', 'wp-theme-' + slug, 'wpc-dashboard-page', `wpc-${spec.page}-page`, 'wpc-signed-in'].join(' ');
}

/**
 * The frame and the root around one case, as { open, close }: the main area of exactly `w` (its content box), in
 * the main grid track whatever the menu's track is (the menu is not drawn). A case that starts with a dashboard's
 * root is used as its own root.
 */
export function frameFor(c, w, surface = DEFAULT_SURFACE, off = false) {
	const { key, view, spec } = surfaceOf(surface);
	const on = !off && spec.live ? ' wpcpm-ui-on' : '';
	const root = /^\s*<div class="wpcpm-dashboard wpcpm-([a-z]+)\b/.exec(c.html);
	if (root && root[1] !== key) throw new Error(`case "${c.name}" is the ${root[1]} dashboard's root, drawn on the ${spec.label}: name its surface`);
	const rootOpen = root ? '' : `<div class="wpcpm-dashboard wpcpm-${key}${view ? ` wpcpm-${key}--${view}` : ''}${on}">`;
	return {
		open: `<div class="wpcpm-frame wpcpm-frame--${spec.page}${on}" data-wpcpm-frame><div class="wpcpm-frame__body"><div class="wpcpm-frame__main" style="box-sizing:content-box;width:${w}px;grid-column:-2/-1;container:wpcpm-main/inline-size">${rootOpen}`,
		close: `${root ? '' : '</div>'}</div></div></div>`,
	};
}

/**
 * The page for cases of one surface (each case's `surface`, or the `surface` option, or DEFAULT_SURFACE): throws
 * when the cases name more than one, since a page has one body.
 */
export function page(cases, widths, { pluginDir = REPO, themeDir = process.env.WPCPM_THEME_DIR || join(homedir(), 'GitHub/wpcredits-theme'), fixtureDir = HERE, off = false, extraCss = '', surface = DEFAULT_SURFACE } = {}) {
	const list = Array.isArray(cases) ? cases : Object.entries(cases).map(([name, html]) => ({ name, html }));
	const named = [...new Set(list.map(c => c.surface || surface))];
	if (named.length > 1) throw new Error('one page draws one surface; these cases name ' + named.join(', '));
	const on = named[0] || surface;
	const { spec } = surfaceOf(on);
	const link = f => `<link rel="stylesheet" href="${pathToFileURL(f).href}">`;
	const sh = sheetsFor({ pluginDir, themeDir, fixtureDir, surface: on });
	const sheets = sh.ordered.map(link).join('');
	let body = '';
	for (const w of widths) {
		for (const c of list) {
			const f = frameFor(c, w, on, off);
			body += `<div class="h-case" data-name="${esc(c.name)}" data-w="${w}" data-surface="${esc(on)}">${f.open}${c.html}${f.close}</div>`;
		}
	}
	// The template's wrappers around the post content, as the page prints them (templates/page-<page>-dashboard.html);
	// the WordPress.org header and footer parts are not drawn.
	const open = sh.haveTheme
		? `<div class="wp-site-blocks"><main id="wp--skip-link--target" class="wp-block-group wpc-main wpc-main--app wpc-main--${spec.page} is-layout-flow wp-block-group-is-layout-flow"><div class="entry-content wp-block-post-content wpc-app__content is-layout-flow wp-block-post-content-is-layout-flow">`
		: '';
	const close = sh.haveTheme ? '</div></main></div>' : '';
	const classes = sh.haveTheme ? bodyClasses(sh.slug, on) : `wpc-dashboard-page wpc-${spec.page}-page`;
	return `<!doctype html><html lang="en-US"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">${sheets}<style>body{margin:0}.screen-reader-text{border:0;clip-path:inset(50%);height:1px;margin:-1px;overflow:hidden;padding:0;position:absolute!important;width:1px;word-wrap:normal!important}.h-case{margin:0 0 40px}${extraCss}</style></head><body class="${classes}">${open}${body}${close}</body></html>`;
}
