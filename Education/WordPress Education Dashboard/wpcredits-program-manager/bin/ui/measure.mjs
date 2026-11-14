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
import { fileName, fill, prepare, scenes, sizeOf, stage, still, takenAt } from './scenes.mjs';
import { lookups } from './seed.mjs';

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

// ------------------------------------------------------------------ what is read in the page
function collect(W) {
  W = Number(W) || innerWidth; // the emulated window's width: under phone emulation innerWidth grows with a page that is too wide
  const R = document.body.classList.contains('wp-admin') ? document.querySelector('#wpbody-content .wpcpm-wrap, #wpbody-content') : document.querySelector('.wpcpm-dashboard, .wpcpm-frame') ? document.querySelector('main') || document.body : document.body;
  const vis = e => { const r = e.getBoundingClientRect(); const c = getComputedStyle(e); return r.width > 0 && r.height > 0 && c.visibility !== 'hidden' && c.display !== 'none' && !e.closest('[hidden], .screen-reader-text, details:not([open]) > :not(summary)'); };
  const skip = e => e.closest('.wpcpm-frame__nav, .wpcpm-frame__menu, .wpcpm-frame__top, .wpcpm-frame__side, .tablenav, .subsubsub, .nav-tab-wrapper, #adminmenuwrap, #wpadminbar, .global-header, .global-footer, .wpcpm-dashboard__switcher');
  const px = v => Math.round(parseFloat(v) * 10) / 10;
  const box = e => { const r = e.getBoundingClientRect(); return { top: r.top + scrollY, bottom: r.bottom + scrollY, left: r.left, right: r.right, h: Math.round(r.height * 10) / 10 }; };
  const desc = e => (e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).slice(0, 3).join('.') : '') + ' "' + (e.innerText || e.value || '').replace(/\s+/g, ' ').trim().slice(0, 40) + '"');
  const all = sel => (R ? [...R.querySelectorAll(sel)] : []).filter(e => vis(e) && !skip(e));
  const sib = (e, dir) => { let n = dir < 0 ? e.previousElementSibling : e.nextElementSibling; while (n && !vis(n)) n = dir < 0 ? n.previousElementSibling : n.nextElementSibling; return n; };
  const out = { buttons: [], fields: [], heads: [], tags: [], headings: [], gaps: [], rowActions: [], overflow: null };

  // Buttons: the old classes and the parts' .wpcpm-btn.
  for (const b of all('button, a.button, a.wpcpm-button, a.wpcpm-btn, .wpcpm-btn, .wpcpm-card__cue, summary.button, input[type="submit"], input[type="button"]')) {
    if (b.matches('.wpcpm-module__move, .wpcpm-p-list__filters button, .wpcpm-mentee__toggle, [role="option"]')) continue;
    const c = getComputedStyle(b);
    out.buttons.push({ el: desc(b), h: box(b).h, font: `${px(c.fontSize)}/${c.fontWeight}`, radius: px(c.borderTopLeftRadius), bg: c.backgroundColor, fg: c.color, border: c.borderTopColor, pad: `${px(c.paddingTop)} ${px(c.paddingRight)}`, wraps: box(b).h > 44 });
  }
  // Fields: every box, the parts' .wpcpm-ui-field__control among them.
  for (const f of all('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="file"]), select, textarea, .wpcpm-ui-field__control')) {
    if (f.matches('.wpcpm-ui-field__control') && !f.matches('input, select, textarea')) continue;
    const c = getComputedStyle(f);
    out.fields.push({ el: desc(f), tag: f.tagName.toLowerCase(), h: box(f).h, font: `${px(c.fontSize)} ${c.fontFamily.split(',')[0].replace(/"/g, '')}`, radius: px(c.borderTopLeftRadius), border: c.borderTopColor, w: Math.round(f.getBoundingClientRect().width) });
  }
  // Fold heads: a card's head or an inner fold's head, open or closed; the parts' .wpcpm-ui-fold__head too.
  for (const s of all('summary, .wpcpm-p-head, .wpcpm-overview__row-in, .wpcpm-ui-fold__head')) {
    if (s.matches('summary') && s.closest('.wpcpm-ui-fold__head') && s !== s.closest('.wpcpm-ui-fold__head')) continue;
    const det = s.closest('details');
    const card = s.matches('.wpcpm-group__summary, .wpcpm-p-head, .wpcpm-overview__row-in') || !!s.closest('.wpcpm-ui-fold-card > .wpcpm-ui-fold > .wpcpm-ui-fold__head, .wpcpm-ui-fold-card > details > summary');
    out.heads.push({ el: desc(s), kind: card ? 'card' : 'inner', open: det ? det.open : null, h: box(s).h });
  }
  for (const t of all('.wpcpm-status, .wpcpm-ui-chip, .wpcpm-p-tag, .wpcpm-inst-mark, .wpcpm-people__mark, .wpcpm-sreg-outcome strong, .wpcpm-card__age')) {
    const c = getComputedStyle(t);
    out.tags.push({ el: desc(t), look: `${px(c.fontSize)}/${c.fontWeight} ${c.textTransform} r${px(c.borderTopLeftRadius)} h${box(t).h}` });
  }
  // In-card headings, the parts' .wpcpm-ui-h among them, with the distance actually drawn above and below.
  // An item's title (design 2.10, its facts 4px under it) and a sponsor offer's title in Tools from our sponsors
  // (today's offer grid) are not in-card headings, and are not counted as headings.
  for (const h of all('h3, h4, .wpcpm-p-h, .wpcpm-ui-h')) {
    if (h.closest('summary, .wpcpm-p-head, .wpcpm-overview__row-in, .wpcpm-ui-fold__head, table, .wpcpm-mentor-tile, .wpcpm-p-list__line') || h.matches('.wpcpm-group__title, .wpcpm-queue-title, .wpcpm-administrator__item-title, .wpcpm-roster__strip-title, .wpcpm-student__heading, .wpcpm-head__eyebrow, .wpcpm-ui-item__title, .wpcpm-tools__title')) continue;
    const c = getComputedStyle(h);
    const hb = box(h); const p = sib(h, -1), n = sib(h, 1);
    const decide = /^decide$/i.test((h.innerText || '').trim()) || !!h.closest('.wpcpm-ui-decide');
    // On a phone "How to do this" is the open card's first line, 16px over the next block (design 2.3).
    const afterHelp = !!(p && p.matches('.wpcpm-ui-fold__help--phone'));
    out.headings.push({ el: desc(h), look: `${px(c.fontSize)}/${c.fontWeight} ${c.textTransform}`, above: p ? Math.round(hb.top - box(p).bottom) : null, below: n ? Math.round(box(n).top - hb.bottom) : null, decide, afterHelp });
  }
  // Gaps: label to its box, box to its hint, field to field, last field to the action row.
  const controls = all('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]), select, textarea');
  const byForm = new Map();
  for (const c of controls) {
    const lab = c.id ? document.querySelector(`label[for="${CSS.escape(c.id)}"]`) : null;
    const labVisible = lab && vis(lab);
    let hint = (c.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean).map(i => document.getElementById(i)).find(h => h && vis(h) && !h.matches('[role="alert"], .wpcpm-form-error'));
    if (!hint) { const n = c.nextElementSibling; if (n && vis(n) && n.matches('.description, .wpcpm-field__hint, .wpcpm-p-hint, .wpcpm-ui-field__hint')) hint = n; }
    const cb = box(c.matches('.wpcpm-p-filein') ? c.parentElement : c);
    let wrap = c; while (wrap.parentElement && !(lab && wrap.parentElement.contains(lab)) && wrap.parentElement !== c.form) wrap = wrap.parentElement;
    if (lab && wrap.parentElement && wrap.parentElement.contains(lab) && wrap.parentElement !== c.form) wrap = wrap.parentElement;
    const field = c.closest('.wpcpm-ui-field');
    if (field) wrap = field;
    const f = { el: desc(c), wrap, top: labVisible ? box(lab).top : cb.top, bottom: Math.max(hint ? box(hint).bottom : cb.bottom, wrap !== c ? box(wrap).bottom : 0) };
    if (labVisible) {
      const lb = box(lab);
      if (lb.bottom <= cb.top + 1) out.gaps.push({ kind: 'label-field', px: Math.round(cb.top - lb.bottom), el: desc(c) });
      else out.gaps.push({ kind: 'label-beside-field', px: Math.round(cb.left - lb.right), el: desc(c) });
    }
    if (hint) out.gaps.push({ kind: 'field-hint', px: Math.round(box(hint).top - cb.bottom), el: desc(c) });
    const form = c.form || c.closest('form');
    if (!form) continue;
    if (!byForm.has(form)) byForm.set(form, []);
    byForm.get(form).push(f);
  }
  const nextVisible = e => { let n = e.nextElementSibling; while (n && (!vis(n) || n.matches('input[type="hidden"]'))) n = n.nextElementSibling; return n; };
  for (const [form, list] of byForm) {
    if (form.matches('.wpcpm-emails__filters, .wpcpm-roster__filters')) continue; // a filter bar is a grid, not a column
    // De-duplicate the fields of one wrapper (a combobox's own input and its list).
    const seen = new Set();
    const fields = list.filter(f => (seen.has(f.wrap) ? false : seen.add(f.wrap))).sort((a, b) => a.top - b.top);
    for (let i = 1; i < fields.length; i++) if (fields[i].top >= fields[i - 1].bottom - 1 && nextVisible(fields[i - 1].wrap) === fields[i].wrap) out.gaps.push({ kind: 'field-field', px: Math.round(fields[i].top - fields[i - 1].bottom), el: fields[i].el });
    const last = fields[fields.length - 1];
    const after = last && nextVisible(last.wrap);
    if (after && (after.matches('button, input[type="submit"], .wpcpm-ui-actions') || after.querySelector('button[type="submit"], button:not([type]), input[type="submit"]'))) {
      const b = after.matches('button, input[type="submit"]') ? after : after.querySelector('button, input[type="submit"]');
      if (b) {
        const gap = Math.round(box(b).top - last.bottom);
        out.gaps.push({ kind: gap < 0 ? 'picker-row' : 'field-actions', px: gap, el: last.el });
      }
    }
  }
  // A Decide row to its open panel, the parts' and the old.
  for (const p of all('.wpcpm-ui-panel:not([hidden]), .wpcpm-p-panel:not([hidden])')) {
    const row = p.closest('.wpcpm-ui-decide, .wpcpm-p-decide');
    const prev = row ? row.querySelector(':scope > .wpcpm-ui-actions, :scope > .wpcpm-p-actions') : p.previousElementSibling;
    if (prev && prev !== p) out.gaps.push({ kind: 'decide-panel', px: Math.round(box(p).top - box(prev).bottom), el: desc(p) });
  }
  // Headings: 24 above and 8 below, 12 under Decide.
  for (const h of out.headings) {
    if (h.above !== null) out.gaps.push({ kind: h.afterHelp ? 'help-heading' : 'heading-above', px: h.above, el: h.el });
    if (h.below !== null) out.gaps.push({ kind: h.decide ? 'heading-below-decide' : 'heading-below', px: h.below, el: h.el });
  }
  // Folds 10 apart: a fold (or a card) to the next one beside it.
  for (const f of all('.wpcpm-ui-fold-card, .wpcpm-ui-fold:not(.wpcpm-ui-fold-card .wpcpm-ui-fold--row), details.wpcpm-group__disclosure')) {
    const n = sib(f, 1);
    // A run of inner folds shares its hairlines, 0 apart (design 2.3): a kind of its own.
    const run = f.matches('.wpcpm-ui-fold--inner') && n && n.matches('.wpcpm-ui-fold--inner');
    if (n && n.matches('.wpcpm-ui-fold-card, .wpcpm-ui-fold, details.wpcpm-group__disclosure')) out.gaps.push({ kind: run ? 'inner-fold-run' : 'fold-fold', px: Math.round(box(n).top - box(f).bottom), el: desc(f) });
  }
  // Row actions: one border per row action; a quiet one measures border 0, a transparent fill, no side padding.
  for (const b of all('.wpcpm-ui-list__act button, .wpcpm-ui-list__act a, .wpcpm-p-list__act button, .wpcpm-p-list__act a')) {
    const c = getComputedStyle(b);
    const border = px(c.borderTopWidth) + px(c.borderRightWidth) + px(c.borderBottomWidth) + px(c.borderLeftWidth);
    out.rowActions.push({ el: desc(b), border, bg: c.backgroundColor, h: box(b).h, padX: px(c.paddingLeft) + px(c.paddingRight), quiet: b.matches('.wpcpm-btn--quiet') });
  }
  out.filters = all('.subsubsub, .wpcpm-p-list__filters, .wpcpm-ui-filter').map(f => (f.matches('.subsubsub') ? 'wordpress-subsubsub' : 'chips'));
  out.searches = all('.wpcpm-p-list__search, .wpcpm-ui-search, form.wpcpm-mapp-find, .wpcpm-inst-find .wpcpm-admin-combo, .wpcpm-emails__search').map(s => { const i = s.querySelector('input:not([type="hidden"])'); const l = s.querySelector('label'); if (!i || !l) return 'no-label'; const ib = box(i), lb = box(l); return `${lb.bottom <= ib.top + 1 ? 'label-above' : 'label-beside'} ${Math.round(ib.right - ib.left)}px`; });
  out.itemHeads = all('.wpcpm-administrator__item-title, .wpcpm-queue-title, .wpcpm-ui-item__title').map(h => { const c = getComputedStyle(h); return `${px(c.fontSize)}/${c.fontWeight}`; });
  out.kvLabelColors = all('.wpcpm-app-answers tbody th, .wpcpm-mentee__table tbody th, .wpcpm-details__row > dt, .wpcpm-ui-table--keyvalue tbody th').map(e => getComputedStyle(e).color);
  out.overflow = { scrollWidth: document.documentElement.scrollWidth, viewport: W, cut: [...document.querySelectorAll('main *, #wpbody-content *')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.right > W + 1 && !e.closest('.screen-reader-text, [hidden]') && getComputedStyle(e).position !== 'fixed'; }).slice(0, 12).map(desc) };
  return out;
}

// ------------------------------------------------------------------ the summary
const RHYTHM = { 'label-field': [6], 'field-hint': [6], 'field-field': [14], 'field-actions': [16], 'decide-panel': [16], 'heading-above': [24], 'heading-below': [8], 'heading-below-decide': [12], 'fold-fold': [10], 'label-beside-field': [], 'help-heading': [16], 'inner-fold-run': [0] };
const uniq = a => [...new Set(a)];
const nums = a => uniq(a).sort((x, y) => x - y);
function summarize(r) {
  const off = r.gaps.filter(g => g.kind !== 'picker-row' && !(RHYTHM[g.kind] || []).includes(g.px));
  return {
    buttonLooks: uniq(r.buttons.map(b => `${b.h}px ${b.font} r${b.radius} bg ${b.bg} fg ${b.fg} bd ${b.border}`)).length,
    buttonHeights: nums(r.buttons.map(b => b.h)),
    wrappedButtons: r.buttons.filter(b => b.wraps).length,
    cardHeadHeights: { open: nums(r.heads.filter(h => h.kind === 'card' && h.open === true).map(h => h.h)), closed: nums(r.heads.filter(h => h.kind === 'card' && h.open === false).map(h => h.h)), row: nums(r.heads.filter(h => h.kind === 'card' && h.open === null).map(h => h.h)) },
    innerHeadHeights: { open: nums(r.heads.filter(h => h.kind === 'inner' && h.open === true).map(h => h.h)), closed: nums(r.heads.filter(h => h.kind === 'inner' && h.open === false).map(h => h.h)) },
    fieldHeights: nums(r.fields.filter(f => f.tag !== 'textarea').map(f => f.h)),
    textBoxMinimum: r.fields.some(f => f.tag === 'textarea') ? Math.min(...r.fields.filter(f => f.tag === 'textarea').map(f => f.h)) : null,
    fieldLooks: uniq(r.fields.map(f => `${f.font} r${f.radius} bd ${f.border}`)).length,
    tagLooks: uniq(r.tags.map(t => t.look)).length,
    headingStyles: uniq(r.headings.map(h => h.look)).length,
    gapValues: Object.fromEntries(Object.keys(RHYTHM).map(k => [k, nums(r.gaps.filter(g => g.kind === k).map(g => g.px))])),
    offRhythm: off.length,
    offRhythmList: off.slice(0, 30).map(g => `${g.kind} ${g.px}px ${g.el}`),
    rowActionsWithBorder: r.rowActions.filter(a => a.border > 0).length,
    quietRowActionsOff: r.rowActions.filter(a => a.quiet && (a.border > 0 || !/rgba\(0, 0, 0, 0\)|transparent/.test(a.bg) || a.padX > 0)).length,
    filterKinds: uniq(r.filters || []),
    searchKinds: uniq(r.searches || []),
    itemHeadKinds: uniq(r.itemHeads || []),
    kvLabelColors: uniq(r.kvLabelColors || []),
    horizontalScroll: r.overflow.scrollWidth > r.overflow.viewport,
    cut: r.overflow.cut,
  };
}
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
      const context = await browser.newContext({ viewport: { width: size.width, height: size.height }, deviceScaleFactor: 1, isMobile: size.width < 600, hasTouch: size.width < 600, locale: 'en-US', timezoneId: 'UTC' });
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

const screens = Object.fromEntries(Object.entries(raw).map(([k, r]) => [k, summarize(r)]));
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
}
mkdirSync(dirname(OUT), { recursive: true });
writeFileSync(OUT, JSON.stringify({ taken: new Date().toISOString(), summary: whole, screens, failed, raw }, null, 1));

for (const [k, s] of Object.entries(screens)) console.log(line(k, s));
console.log('\nWHOLE SET');
for (const [k, s] of Object.entries(whole)) { console.log(line(k, s)); console.log('   gaps', JSON.stringify(s.gapValues)); }
if (Object.keys(failed).length) {
  console.log('\nCould not measure');
  for (const [k, why] of Object.entries(failed)) console.log(`- ${k}: ${why}`);
}
