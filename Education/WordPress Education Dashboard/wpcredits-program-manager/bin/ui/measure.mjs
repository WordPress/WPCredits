#!/usr/bin/env node
// Measures every scene of scenes.mjs on the local copy from computed styles and bounding boxes, never by eye:
// the counts the design's section 8 item 4 names, per screen and for the whole set.
//
//   node bin/ui/measure.mjs --out <file.json> [--only <comma list>] [--sizes 1280,390]
//
// Per screen: how many button looks and which button heights, the fold head heights (card and inner, open and
// closed), the field heights and text box minimums, the heading styles, every gap off the rhythm (label 6,
// hint 6, field 14, last field to the action row 16, Decide row to its panel 16, heading 24 above and 8 below,
// 12 under Decide, folds 10 apart) and the row actions drawn with a border. Writes the whole to --out (a file
// outside this repository or under .superpowers/) and prints one line per screen. The copy must be prepared
// (bin/ui/copy.sh prepare, then snap.mjs once, which makes the snapshot ui-ready this restores).
//
// Playwright is WPCPM_PLAYWRIGHT, or the release script's copy under the home folder.
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { ADMIN_LOGIN, BASE, DOCKER, cookiesFor, frozenNow, refreshRig, refuseUnlessCopy, rigLoaded, waitForCopy } from './copy.mjs';
import { fileName, fill, gateMeasures, prepare, scenes, sizeOf, stage, still, takenAt } from './scenes.mjs';
import { lookups } from './seed.mjs';
import { collect, gateLine, summarize } from './collect.mjs';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const PLAYWRIGHT = process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs');

const argv = process.argv.slice(2);
const opt = n => { const i = argv.indexOf('--' + n); return i >= 0 ? (argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : true) : null; };
const OUT = typeof opt('out') === 'string' ? resolve(opt('out')) : '';
if (!OUT) { console.error('usage: node bin/ui/measure.mjs --out <file.json> [--only <comma list>] [--sizes 1280,390]'); process.exit(2); }
if (!relative(REPO, OUT).startsWith('..') && !OUT.startsWith(join(REPO, '.superpowers') + sep)) throw new Error('refusing: --out is inside the repository but not under .superpowers/ (measures are never committed)');
const only = typeof opt('only') === 'string' ? opt('only').split(',').filter(Boolean) : null;
const widths = String(typeof opt('sizes') === 'string' ? opt('sizes') : '1280,390').split(',').map(Number);
const sizes = widths.map(sizeOf); // any widths; a scene's own extra widths are pictures only

refuseUnlessCopy(BASE);
if (!rigLoaded()) throw new Error('refusing: the rig\'s must-use plugin is not loaded (bin/ui/copy.sh prepare)');
refreshRig();
execFileSync(join(DOCKER, 'bin/restore'), ['ui-ready'], { cwd: DOCKER, stdio: 'ignore' });
await waitForCopy(); // a restore restarts WordPress: its proxy is restarted when it answers 502
const values = lookups();
const NOW = frozenNow();

const line = (k, s) => `${k.padEnd(72)} btn ${String(s.buttonLooks).padStart(2)} (${s.buttonHeights.join('/')})  head card ${JSON.stringify(s.cardHeadHeights)} inner ${JSON.stringify(s.innerHeadHeights)}  field ${s.fieldHeights.join('/')} tbox ${s.textBoxMinimum === null ? '-' : s.textBoxMinimum}  hd ${s.headingStyles}  tags ${s.tagLooks}  off ${s.offRhythm}  rowbd ${s.rowActionsWithBorder}${s.horizontalScroll ? '  H-SCROLL' : ''}`;

// ------------------------------------------------------------------ run
const { chromium } = await import(pathToFileURL(PLAYWRIGHT).href);
const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--hide-scrollbars'] });
const raw = {};
const failed = {};
try {
  for (const scene of scenes()) {
    if (scene.viewportOnly || scene.section) continue; // the guide's parts: pictures only
    for (const size of sizes) {
      if (!takenAt(scene, size, widths)) continue;
      const key = fileName(scene, size);
      if (only && !only.some(o => key.includes(o))) continue;
      const filled = fill(scene, values);
      if (!filled) { failed[key] = 'an invented item it needs is not on the copy'; continue; }
      const context = await browser.newContext({ viewport: { width: size.width, height: size.height }, deviceScaleFactor: 1, isMobile: size.width < 600, hasTouch: size.width < 600, locale: 'en-US', timezoneId: 'UTC', javaScriptEnabled: !scene.noScript });
      await context.route('**/*', r => { const u = new URL(r.request().url()); if (u.origin === BASE || u.protocol === 'data:') return r.continue(); if (r.request().resourceType() === 'image') return r.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64') }); return r.abort(); });
      await context.addCookies(cookiesFor(filled.login || ADMIN_LOGIN));
      await still(context, NOW);
      await prepare(context, filled);
      const page = await context.newPage();
      try {
        await stage(page, filled, BASE);
        raw[key] = await page.evaluate(collect, size.width);
        process.stdout.write('.');
      } catch (e) {
        failed[key] = String(e.message || e).split('\n')[0];
        process.stdout.write('x');
      } finally {
        await context.close();
      }
    }
  }
} finally {
  await browser.close();
}
console.log();

const screens = Object.fromEntries(Object.entries(raw).map(([k, r]) => [k, { ...summarize(r), gateB: gateMeasures(r) }]));
const merge = keys => summarize(keys.reduce((a, k) => {
  for (const f of ['buttons', 'fields', 'heads', 'tags', 'headings', 'gaps', 'rowActions', 'filters', 'searches', 'itemHeads', 'kvLabelColors']) a[f] = a[f].concat(raw[k][f] || []);
  return a;
}, { buttons: [], fields: [], heads: [], tags: [], headings: [], gaps: [], rowActions: [], filters: [], searches: [], itemHeads: [], kvLabelColors: [], overflow: { scrollWidth: 0, viewport: 1, cut: [] } }));
const at = w => Object.keys(raw).filter(k => k.endsWith('-' + w));
const whole = {};
for (const s of sizes) {
  whole[`front-end-${s.width}`] = merge(at(s.width).filter(k => !k.startsWith('wpadmin-')));
  whole[`wp-admin-${s.width}`] = merge(at(s.width).filter(k => k.startsWith('wpadmin-')));
  whole[`admin-dashboard-${s.width}`] = merge(at(s.width).filter(k => k.startsWith('admin-dashboard-')));
  const inst = at(s.width).filter(k => k.startsWith('institution-dashboard-'));
  whole[`institution-dashboard-${s.width}`] = { ...merge(inst), gateB: gateMeasures(inst.reduce((a, k) => { for (const f of ['buttons', 'fields', 'heads', 'rowActions']) a[f] = a[f].concat(raw[k][f] || []); return a; }, { buttons: [], fields: [], heads: [], rowActions: [] })) };
}
mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, JSON.stringify({ taken: new Date().toISOString(), summary: whole, screens, failed, raw }, null, 1));

for (const [k, s] of Object.entries(screens)) console.log(line(k, s));
console.log('\nWHOLE SET');
for (const [k, s] of Object.entries(whole)) { console.log(line(k, s)); console.log('   gaps', JSON.stringify(s.gapValues)); }
console.log('\nGATE B (the Institution Dashboard on the parts)');
for (const [k, s] of Object.entries(screens)) if (k.startsWith('institution-dashboard-')) console.log(gateLine(k, s.gateB));
for (const [k, s] of Object.entries(whole)) if (s.gateB) console.log(gateLine(k, s.gateB));
if (Object.keys(failed).length) {
  console.log('\nCould not measure');
  for (const [k, why] of Object.entries(failed)) console.log(`- ${k}: ${why}`);
}
