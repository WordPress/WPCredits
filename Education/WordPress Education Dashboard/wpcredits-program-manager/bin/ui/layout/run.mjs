#!/usr/bin/env node
// The layout harness: measures the markup a suite kept (bin/ui/layout/dump.php) in headless Chrome, at the
// main-area widths where the frame's layout changes, and prints what no screenshot of 1280 and 390 shows.
//
//   node bin/ui/layout/run.mjs --in <cases.json> [--out <result.json>] [--census <census.json>] [--widths 358,569,...] [--only <substring>] [--off] [--surface <surface>] [--ignore <css selector>]...
//
// Each case is drawn on the surface dump.php kept with it (page.mjs SURFACES: institution, administrator,
// administrator/<view>, sponsor, sponsor/<view>); --surface names one for a file that holds none.
//
// Prints `ok   <case> <width>` or `FAULT <case> <width> <what>` per case and width, then `<N> faults`; exits 1
// on any fault. The faults: OVERFLOW, CELL and H-SCROLL (measure.js), and from the census (measure.js,
// wpcpmCensus) GAP and EDGE (spacingFaults, below).
//
// --census <file> also writes the census: for every case and window, every gap between two neighbouring blocks
// and every edge off its container's, as JSON rows (censusRows, below). It changes nothing in what the run prints
// or fails on. No site is touched: the page is a file under the temporary folder. Playwright is
// WPCPM_PLAYWRIGHT, or the release script's copy under the home folder.
import { mkdtempSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import { homedir, tmpdir } from 'node:os';
import { basename, dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { DEFAULT_SURFACE, page, sheetsFor, surfaceOf } from './page.mjs';

export const WIDTHS = [358, 569, 736, 799, 800, 880, 936, 960];

/**
 * The spacing rhythm (parts design 2.3 to 2.11 and 8.4; DESIGN.md): label 6, hint 6, field 14, blocks of a card
 * 16, last field to action row 16, a heading 24 above and 8 below, 12 under "Decide", folds 10 apart, 24 between
 * two blocks that are not each other's, with the parts' small steps (0, 2, 4) and 20, 22 and 28. A gap within
 * 0.5px of one of these is on the rhythm; 28 only between a view root's rows, where the frame gives it (under the view's
 * head and between the Overview's blocks; Task 28 fix round 2, X14), so a 28px inside a card is reported.
 */
export const RHYTHM = [0, 2, 4, 6, 8, 10, 12, 14, 16, 20, 22, 24, 28];
/**
 * Blocks of words; the controls and buttons a block of words must not touch (a gap under 6px); and the controls
 * that begin with their own words (a field's label, a group's legend, a tick's words), which a button or a box
 * must not touch either.
 */
export const WORDS = ['paragraph', 'muted line', 'hint', 'label', 'legend', 'lede', 'message', 'field error', 'heading', 'in-card heading', 'tag line', 'facts line', 'line of text', 'option words', 'note', 'quote', 'view title', 'eyebrow', 'item title', 'more line'];
export const CONTROLS = ['box', 'button', 'action row', 'field', 'file box', 'file row', 'choice group', 'tick', 'option', 'options'];
export const LABELLED = ['field', 'file box', 'choice group', 'field set', 'tick'];
/**
 * The surfaces each new fault fails on. GAP: the Institution Dashboard only for now (the Administrator and Sponsor
 * Dashboards are live: their findings are listed by the census, not failed, until the owner has seen them). EDGE:
 * a legend off its fields' edge.
 */
export const GAP_SURFACES = ['institution'];
export const EDGE_SURFACES = ['institution', 'administrator', 'sponsor'];

const onRhythm = gap => RHYTHM.some(v => Math.abs(gap - v) <= 0.5);

/**
 * Whether one census gap is off: under 6px between a block of words (or a control that begins with its words) and
 * a control or a button, either above, or off the rhythm.
 */
export function gapIsOff(g) {
	// Gate B round 3, B14 (ruled: stays): a view head's facts line over its button row stands 18px, the frame head's own
	// value (the copy's 10px gap and the row's 8px), the same on the live Sponsor Dashboard.
	if ('view head' === g.c && 'facts line' === g.u && 'action row' === g.l && Math.abs(g.gap - 18) <= 0.5) return false;
	if (Math.abs(g.gap - 28) <= 0.5 && 'view root' !== g.c) return true;
	const worded = k => WORDS.includes(k) || LABELLED.includes(k);
	const touches = g.gap < 5.5 && ((worded(g.u) && CONTROLS.includes(g.l)) || (CONTROLS.includes(g.u) && worded(g.l)));
	return touches || !onRhythm(g.gap);
}

/**
 * The GAP and EDGE faults of one case's census on its surface (`administrator/watch` is the administrator's):
 *   `GAP <upper kind> to <lower kind> <px>: "<upper words>" over "<lower words>"`
 *   `EDGE legend <px>: "<legend words>"`
 */
export function spacingFaults(census, surface) {
	const key = String(surface || DEFAULT_SURFACE).split('/')[0];
	const out = [];
	if (GAP_SURFACES.includes(key)) {
		for (const g of census.gaps) if (gapIsOff(g)) out.push(`GAP ${g.u} to ${g.l} ${g.gap}: "${g.a}" over "${g.b}"`);
	}
	if (EDGE_SURFACES.includes(key)) {
		for (const e of census.edges) if ('legend' === e.k && 'content edge' !== e.of) out.push(`EDGE legend ${e.at}: "${e.words}"`);
	}
	return [...new Set(out)];
}

/** The census as rows: one per gap, one per edge off, and one count of the edges on, each with its case and window. */
export function censusRows(results) {
	const rows = [];
	for (const r of results) {
		if (!r.census) continue;
		const base = { case: r.name, surface: r.surface, w: r.w, win: r.win };
		for (const g of r.census.gaps) rows.push({ ...base, type: 'gap', ...g });
		for (const e of r.census.edges) rows.push({ ...base, type: 'edge', ...e });
		rows.push({ ...base, type: 'zero edges', count: r.census.zeroEdges });
	}
	return rows;
}

const HERE = dirname(fileURLToPath(import.meta.url));

/**
 * The windows a main-area width is drawn in, as { label, win }. Under 800 one window: the main width plus the
 * main area's own padding while the menu is a drawer (16px a side, up to a 782px window); above that window the
 * menu's 64px rail stands beside the main area and the padding is 32px a side, so 799 needs 927. From 800 up the
 * two a reader can have: menu folded (window = main + 128) and menu open (window = main + 320).
 */
export function windowsFor(w) {
	if (w >= 800) return [{ label: 'folded', win: w + 128 }, { label: 'open', win: w + 320 }];
	return [{ label: '', win: w + 32 <= 782 ? w + 32 : w + 128 }];
}

async function launch() {
	const PLAYWRIGHT = process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs');
	const { chromium } = await import(pathToFileURL(PLAYWRIGHT).href);
	return chromium.launch({ channel: 'chrome', headless: true, args: ['--allow-file-access-from-files'] });
}

/**
 * Measures cases ({ name, html, surface }) at each width: one page per surface and width (a page has one body), and
 * one window per width. Throws when a sheet is missing, when there is no case, or when a case's container did not
 * get its width. Answers { results, notes }.
 */
export async function measureCases(cases, widths, { off = false, extraCss = '', ignore = [], pluginDir, themeDir, fixtureDir } = {}) {
	if (!cases.length) throw new Error('no case to measure');
	const opts = { off, extraCss, ...(pluginDir ? { pluginDir } : {}), ...(themeDir ? { themeDir } : {}), ...(fixtureDir ? { fixtureDir } : {}) };
	const groups = new Map();
	for (const c of cases) {
		const key = c.surface || DEFAULT_SURFACE;
		surfaceOf(key);
		if (!groups.has(key)) groups.set(key, []);
		groups.get(key).push({ ...c, surface: key });
	}
	const notes = ["the frame's side menu (.wpcpm-frame__menu) is left out: only the main area is measured"];
	if (ignore.length) notes.push('ignoring: ' + ignore.join(', '));
	for (const [key, list] of groups) {
		const sh = sheetsFor({ ...opts, surface: key });
		notes.push(`${surfaceOf(key).spec.label} (${key}, ${list.length} case${1 === list.length ? '' : 's'}): ` + (sh.haveTheme ? 'sheets, in the page\'s order: ' : 'no theme; sheets, in the page\'s order: ') + sh.ordered.map(f => (sh.theme.includes(f) ? 'theme ' : sh.fixture.includes(f) ? 'wordpress ' : '') + basename(f)).join(', '));
		if (sh.globalStyles && !notes.some(n => n.startsWith('global styles:'))) notes.push(`global styles: WordPress ${sh.globalStyles.wordpress}, Gutenberg ${sh.globalStyles.gutenberg || 'none'}, from theme ${sh.globalStyles.theme_version}'s theme.json (${sh.globalStyles.theme_json_sha256.slice(0, 12)}), made ${sh.globalStyles.made}`);
	}
	const haveTheme = sheetsFor(opts).haveTheme;
	const dir = mkdtempSync(join(tmpdir(), 'wpcpm-layout-'));
	const browser = await launch();
	const results = [];
	try {
		for (const w of widths) {
			for (const [key, list] of groups) {
				const file = join(dir, `page-${key.replace(/[^a-z-]/g, '-')}-${w}.html`);
				writeFileSync(file, page(list, [w], { ...opts, surface: key }));
				for (const { label, win } of windowsFor(w)) {
					const tab = await browser.newPage({ viewport: { width: win, height: 1000 } });
					await tab.goto(pathToFileURL(file).href, { waitUntil: 'load' });
					await tab.evaluate(() => document.fonts.ready);
					if (!results.length) {
						const face = await platformFont(tab);
						notes.push('body text face: ' + face + (haveTheme && !/Inter|EB Garamond/i.test(face) ? ' (not Inter or EB Garamond, the theme\'s faces: a fallback)' : ''));
					}
					await tab.addScriptTag({ content: readFileSync(join(HERE, 'measure.js'), 'utf8') });
					const tag = (r, extra) => ({ ...r, surface: key, win, w: [String(w), label, extra].filter(Boolean).join(' ') });
					const first = await tab.evaluate(ig => window.wpcpmMeasure(false, ig), ignore);
					if (first.length !== list.length) throw new Error(`width ${w}, ${key}: ${first.length} of ${list.length} cases were drawn`);
					const census1 = await tab.evaluate(ig => window.wpcpmCensus(false, ig), ignore);
					const done = new Map();
					for (const r of first) {
						if (r.badWidth !== undefined) throw new Error(`case "${r.name}" at ${w}: its container did not get its width (${r.badWidth})`);
						const census = census1.find(x => x.name === r.name);
						const row = tag({ ...r, faults: [...r.faults, ...spacingFaults(census, key)], census }, '');
						done.set(r.name, row);
						results.push(row);
					}
					if (await tab.evaluate(() => window.wpcpmOpenPanels())) {
						const second = await tab.evaluate(ig => window.wpcpmMeasure(true, ig), ignore);
						const census2 = await tab.evaluate(ig => window.wpcpmCensus(true, ig), ignore);
						for (const r of second) {
							const was = done.get(r.name);
							const before = new Set(was.faults);
							// The census of what opening the panels added: a gap or an edge the first pass did not have.
							const seen = new Set([...was.census.gaps, ...was.census.edges].map(x => JSON.stringify(x)));
							const all = census2.find(x => x.name === r.name);
							const census = { ...all, gaps: all.gaps.filter(x => !seen.has(JSON.stringify(x))), edges: all.edges.filter(x => !seen.has(JSON.stringify(x))), zeroEdges: Math.max(0, all.zeroEdges - was.census.zeroEdges) };
							results.push(tag({ ...r, faults: [...r.faults, ...spacingFaults(census, key)].filter(f => !before.has(f)), census }, 'panels open'));
						}
					}
					await tab.close();
				}
			}
		}
	} finally {
		await browser.close();
		rmSync(dir, { recursive: true, force: true });
	}
	return { results, notes };
}

/** The face Chrome actually drew the first text of the first case in. */
async function platformFont(tab) {
	await tab.evaluate(() => {
		// The first text that is drawn, in any case: a case's first text may sit in a box that is hidden at this
		// width (the menu cases' menu, at 782px and below), whose face is no glyph at all.
		for (const main of document.querySelectorAll('.h-case > .wpcpm-frame > .wpcpm-frame__body > .wpcpm-frame__main')) {
			const w = document.createTreeWalker(main, NodeFilter.SHOW_TEXT);
			let n;
			while ((n = w.nextNode())) {
				if (n.textContent.trim() && n.parentElement.getClientRects().length && !n.parentElement.closest('.wpcpm-frame__menu, .screen-reader-text')) { n.parentElement.setAttribute('data-h-probe', ''); return; }
			}
		}
		document.body.setAttribute('data-h-probe', '');
	});
	const cdp = await tab.context().newCDPSession(tab);
	try {
		await cdp.send('DOM.enable');
		await cdp.send('CSS.enable');
		const { root } = await cdp.send('DOM.getDocument');
		const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: '[data-h-probe]' });
		const { fonts } = await cdp.send('CSS.getPlatformFontsForNode', { nodeId });
		return fonts.length ? fonts.map(f => f.familyName).join(', ') : 'none drawn';
	} catch (e) {
		return 'unknown';
	}
}

async function main() {
	const argv = process.argv.slice(2);
	const opt = n => { const i = argv.indexOf('--' + n); return i >= 0 ? (argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : true) : null; };
	if (typeof opt('in') !== 'string') {
		console.error('usage: node bin/ui/layout/run.mjs --in <cases.json> [--out <result.json>] [--census <census.json>] [--widths 358,569,...] [--only <substring>] [--off] [--surface <surface>] [--ignore <css selector>]...');
		process.exit(2);
	}
	const raw = JSON.parse(readFileSync(resolve(opt('in')), 'utf8'));
	const only = typeof opt('only') === 'string' ? opt('only') : '';
	// dump.php keeps { name: { html, surface } }; a file of { name: html } names no surface, and is drawn only on the
	// surface --surface names.
	const fallback = typeof opt('surface') === 'string' ? opt('surface') : '';
	const cases = Object.entries(raw).filter(([name]) => !only || name.includes(only)).map(([name, v]) => {
		const c = typeof v === 'string' ? { name, html: v, surface: fallback } : { name, html: String(v.html), surface: String(v.surface || '') };
		if (!c.surface) throw new Error(`case "${name}" names no surface: keep it with wpcpm_layout_keep( $name, $html, $surface ), list its suite in dump.php, or pass --surface`);
		return c;
	});
	const ignore = argv.flatMap((a, i) => (a === '--ignore' && argv[i + 1] && !argv[i + 1].startsWith('--') ? [argv[i + 1]] : []));
	const widths = typeof opt('widths') === 'string' ? opt('widths').split(',').map(Number).filter(Boolean) : WIDTHS;

	const { results: result, notes } = await measureCases(cases, widths, { off: opt('off') === true, ignore });
	notes.forEach(n => console.log(n));
	let faults = 0;
	for (const r of result) {
		if (!r.faults.length) console.log(`ok   ${r.name} ${r.w}`);
		for (const f of r.faults) { faults++; console.log(`FAULT ${r.name} ${r.w} ${f}`); }
		if (r.tables.length) console.log(`     ${r.name} ${r.w} tables: ${r.tables.join(', ')}`);
	}
	if (typeof opt('out') === 'string') writeFileSync(resolve(opt('out')), JSON.stringify(result.map(({ census, ...r }) => r), null, 1));
	if (typeof opt('census') === 'string') writeFileSync(resolve(opt('census')), JSON.stringify(censusRows(result)));
	console.log(`${faults} faults`);
	process.exit(faults ? 1 : 0);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	main().catch(e => { console.error(String(e && e.message || e)); process.exit(2); });
}
