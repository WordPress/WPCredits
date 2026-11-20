#!/usr/bin/env node
// Films one how-to clip of the plugin's screens from a shot file, stop-motion, on the local copy only.
//
//   node bin/clips/film.mjs <slug> [--skip-prepare] [--out <dir>] [--names <real-names.json>]
//
// A shot (bin/clips/shots/<slug>.mjs) lists the steps. For each step the rig moves a drawn pointer to the
// control in small steps, taking a frame at each, marks the press, presses, waits for the page to settle,
// and holds the settled frame. Frames and their durations go to ffmpeg. Nothing depends on real time.
// Before any frame is kept, the text visible in the viewport is checked against the real names to refuse.
//
// Refuses any site but http://localhost:8900; every request the browser makes to another host is aborted.
import { homedir } from 'node:os';
// playwright-core as the release tools keep it on this Mac (nothing is installed for the rig).
const { chromium } = await import(homedir() + '/Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs');
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, rmSync, existsSync, statSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { BASE, DOCKER, refuseUnlessCopy, filmingLoaded, cookiesFor, wp, ownerWords } from './copy.mjs';
import { overlayScript } from './overlay.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '../..');
const FFMPEG = existsSync('/opt/homebrew/bin/ffmpeg') ? '/opt/homebrew/bin/ffmpeg' : 'ffmpeg';
const FPS = 25;
// Desktop width: at 1280 CSS pixels the frame's side menu shows its labels (1101 or more) and the guide's
// wpcpm-main container is wide enough (960 or more, content box) for "On this page" to sit in its own column.
// 1280 is the smallest width that does both and is a multiple of 16, so 1280x720 at 1.5 is exactly 1920x1080.
const OUT_SIZE = { width: 1920, height: 1080 };
const VIEW = { width: 1280, height: 720 };
const DSF = OUT_SIZE.width / VIEW.width;
const SCALE = 2 / DSF; // the overlay keeps the pilots' size in output pixels (they were filmed at 2x)
const CAPTION_ONE = Math.round(64 * SCALE); // CSS pixels at the bottom one line of text covers
const CAPTION_TWO = Math.round(98 * SCALE); // and two lines, for a step longer than fits on one
let CAPTION_BAND = CAPTION_ONE;

const argv = process.argv.slice(2);
const slug = argv.find(a => !a.startsWith('--'));
const opt = n => { const i = argv.indexOf('--' + n); return i >= 0 ? (argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : true) : null; };
if (!slug || !/^[a-z0-9-]+$/.test(slug)) { console.error('usage: node bin/clips/film.mjs <slug> [--skip-prepare]'); process.exit(2); }

const OUT = resolve(opt('out') || join(REPO, '.superpowers/guides/clips/out'));
const WORK = join(REPO, '.superpowers/guides/clips/work', slug);
const NAMES = resolve(opt('names') || join(REPO, '.superpowers/guides/clips/work/real-names.json'));

// ---------------------------------------------------------------- the copy, and only the copy
refuseUnlessCopy(BASE);
if (!filmingLoaded()) throw new Error('refusing: the filming plugin is not loaded on the copy (bin/clips/copy.sh prepare)');

const shot = (await import(pathToFileURL(join(HERE, 'shots', slug + '.mjs')).href)).default;
if (shot.slug !== slug) throw new Error('the shot file names another slug');

// ---------------------------------------------------------------- names to refuse
const realNames = existsSync(NAMES) ? JSON.parse(readFileSync(NAMES, 'utf8')) : null;
if (!Array.isArray(realNames) || realNames.length === 0) throw new Error(`refusing: no list of real names at ${NAMES} (made before anything is renamed)`);
const owner = ownerWords();
const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const refuse = [...realNames, ...owner].filter(n => n.trim().length >= 3).map(n => ({
  re: new RegExp('(?<![\\p{L}\\p{N}])' + esc(n.trim()) + '(?![\\p{L}\\p{N}])', n.trim().length > 4 ? 'iu' : 'u'),
  len: n.trim().length,
}));
const allowedMail = /@(example(\.[a-z]*)?|localhost)$/i; // example.org and example.test, also while being typed

function checkText(text, where) {
  for (const [i, r] of refuse.entries()) {
    if (r.re.test(text)) {
      const m = text.match(r.re);
      const ctx = text.slice(Math.max(0, m.index - 40), m.index).replace(/\s+/g, ' ');
      throw new Error(`stopped: a real name (list entry ${i}, ${r.len} characters) is visible at ${where}, after "...${ctx}"`);
    }
  }
  for (const mail of text.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi) || []) {
    if (!allowedMail.test(mail)) throw new Error(`stopped: an address not at example.org or example.test is visible at ${where}`);
  }
}

// ---------------------------------------------------------------- browser
mkdirSync(OUT, { recursive: true });
rmSync(WORK, { recursive: true, force: true });
mkdirSync(join(WORK, 'frames'), { recursive: true });

const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--hide-scrollbars', '--font-render-hinting=none'] });
const refusedHosts = new Set();
async function newContext({ signedIn = true, login = null } = {}) {
  // US English, as every date the site prints reads ("November 13, 2026"). Chrome on a Mac still draws a date box in
  // the Mac's own short date format (System Settings, Date format), which neither this locale, --lang, LANG nor a
  // per-process Cocoa override changes (1.123.104, round 5): a step that types a day asks the box its order first.
  const context = await browser.newContext({ acceptDownloads: true, viewport: VIEW, deviceScaleFactor: DSF, reducedMotion: 'reduce', locale: 'en-US', timezoneId: 'UTC' });
  await context.route('**/*', route => {
    const u = new URL(route.request().url());
    if (u.origin === BASE) return route.continue();
    if (u.protocol === 'data:' || u.protocol === 'blob:') return route.continue();
    refusedHosts.add(u.host);
    // An image from another host (an avatar) is answered here with a transparent pixel, so the page draws
    // as it does with the image missing quietly; nothing is fetched.
    if (route.request().resourceType() === 'image') return route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64') });
    return route.abort();
  });
  // The dashboards' templates carry no sticky site header, yet the theme lands an anchor `--wpc-landing-offset` below
  // the admin bar, room kept for that header (76px, its stand-in height, since header-height.js finds no header part
  // to measure there), and the heading above the card showed half in that room. Told the header has no height, the
  // landing keeps the theme's 28px of air alone, and the first frame after a press is whole (1.123.104, round 5).
  // The root element may not exist yet when the script runs, so it is set as soon as it does, before the page scrolls.
  await context.addInitScript(() => {
    const set = () => { if (document.documentElement) { document.documentElement.style.setProperty('--wpc-header-live', '0px'); return true; } return false; };
    if (!set()) { new MutationObserver((m, o) => { if (set()) o.disconnect(); }).observe(document, { childList: true }); }
  });
  if (signedIn) await context.addCookies(cookiesFor(login || shot.login || 'wpcredits-local'));
  return context;
}

let frames = [];      // { file, seconds }
let cues = [];        // { start, end, text }
let stepTimes = [];   // { n, caption, start }
let clock = 0;
let frameNo = 0;
const pointer = { x: VIEW.width * 0.55, y: VIEW.height * 0.45 };
let caption = '';
let lastChecked = '';
let posterFrame = null;
const stepFrames = []; // { name, file }: one full-size frame for every step (and the drawn question of a confirm)

async function settle(page) {
  try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch { /* a page that keeps a request open still draws */ }
  await page.evaluate(() => document.fonts ? document.fonts.ready : null).catch(() => {});
  await page.waitForTimeout(250);
}

async function drawOverlay(page, extra = {}) {
  // The band is drawn one line high at least and grows with its text; CAPTION_BAND is what the aiming keeps clear.
  await page.evaluate(overlayScript, { x: pointer.x, y: pointer.y, caption, band: CAPTION_ONE, scale: SCALE, ...extra });
}

async function visibleText(page) {
  return page.evaluate(() => {
    const vw = innerWidth, vh = innerHeight; const parts = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let n = walker.nextNode(); n; n = walker.nextNode()) {
      const el = n.parentElement; if (!el || el.closest('#wpcpm-film-overlay')) continue;
      if (!n.textContent.trim()) continue;
      const cs = getComputedStyle(el); if (cs.visibility === 'hidden' || cs.display === 'none' || Number(cs.opacity) === 0) continue;
      const r = document.createRange(); r.selectNodeContents(n);
      const rects = [...r.getClientRects()];
      // WordPress.org's own header and footer link to WordPress.com, a name that is also on the sponsors' list.
      const chrome = el.closest('.global-footer, .global-header');
      if (rects.some(b => b.width > 0 && b.height > 0 && b.bottom > 0 && b.top < vh && b.right > 0 && b.left < vw)) parts.push(chrome ? n.textContent.replace(/WordPress\.com/g, '') : n.textContent);
    }
    for (const el of document.querySelectorAll('input, textarea, select')) {
      const b = el.getBoundingClientRect(); if (!(b.width > 0 && b.bottom > 0 && b.top < vh)) continue;
      if (el.type === 'hidden' || el.type === 'password') continue;
      if (el.tagName === 'SELECT') parts.push(el.selectedOptions[0] ? el.selectedOptions[0].text : ''); else parts.push(el.value || el.placeholder || '');
    }
    parts.push(document.title);
    return parts.join(' \n ');
  });
}

async function frame(page, seconds, { mark = false, dialog = null, keycap = null, keyDown = false } = {}) {
  await drawOverlay(page, { mark, dialog, keycap, keyDown });
  const text = await visibleText(page);
  if (text !== lastChecked) { checkText(text, page.url().replace(BASE, '') || '/'); lastChecked = text; }
  if (dialog) checkText(dialog.message, 'the browser question');
  const file = join(WORK, 'frames', String(++frameNo).padStart(5, '0') + '.png');
  await page.screenshot({ path: file, type: 'png', animations: 'disabled', caret: 'initial' });
  frames.push({ file, seconds });
  clock += seconds;
  return file;
}

async function hold(page, seconds) { return frame(page, seconds); }

function setCaption(text) {
  if (cues.length) cues[cues.length - 1].end = clock;
  caption = text;
  CAPTION_BAND = text && text.length > 100 ? CAPTION_TWO : CAPTION_ONE;
  if (text) cues.push({ start: clock, end: null, text });
}

async function moveTo(page, x, y, ms = 600) {
  const steps = Math.max(6, Math.round(ms / (1000 / FPS)));
  const sx = pointer.x, sy = pointer.y;
  for (let i = 1; i <= steps; i++) {
    const t = i / steps; const e = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
    pointer.x = sx + (x - sx) * e; pointer.y = sy + (y - sy) * e;
    await frame(page, 1 / FPS);
  }
}

/**
 * A place for the pointer to wait: inside the frame, above the line of text, clear of any text, control or
 * image on screen, as near as possible to where it is (or to the given preference).
 */
async function clearSpot(page, prefer = null, near = null) {
  const want = prefer || { x: pointer.x, y: pointer.y };
  return page.evaluate(({ vw, vh, band, w, h, want, near }) => {
    const rects = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
    for (let n = walker.nextNode(); n; n = walker.nextNode()) {
      if (!n.textContent.trim() || (n.parentElement && n.parentElement.closest('#wpcpm-film-overlay'))) continue;
      const r = document.createRange(); r.selectNodeContents(n);
      for (const b of r.getClientRects()) if (b.width && b.bottom > 0 && b.top < vh) rects.push(b);
    }
    for (const el of document.querySelectorAll('a, button, input, select, textarea, summary, img, svg, [role=button]')) {
      if (el.closest('#wpcpm-film-overlay')) continue;
      const b = el.getBoundingClientRect(); if (b.width && b.bottom > 0 && b.top < vh) rects.push(b);
    }
    // near: a small window round the wanted spot, searched finely, with a smaller margin (for pointing at words).
    const m = near ? near.margin : 10, st = near ? 2 : 12; let best = null;
    const y0 = near ? Math.max(40, want.y - near.up) : 60, y1 = near ? want.y + near.down : vh;
    const x0 = near ? want.x - near.left : 300, x1 = near ? want.x + near.right : vw;
    for (let y = y0; y <= y1 && y + h < vh - band - 8; y += st) {
      for (let x = x0; x <= x1 && x + w < vw - 8; x += st) {
        const hit = rects.some(b => x - m < b.right && x + w + m > b.left && y - m < b.bottom && y + h + m > b.top);
        if (hit) continue;
        const d = Math.hypot(x - want.x, y - want.y);
        if (!best || d < best.d) best = { x, y, d };
      }
    }
    if (near) return best ? { x: best.x, y: best.y } : null;
    return best ? { x: best.x, y: best.y } : { x: vw - w - 16, y: Math.round((vh - band) / 2) };
  }, { vw: VIEW.width, vh: VIEW.height, band: CAPTION_BAND, w: 26 * SCALE, h: 34 * SCALE, want, near });
}

/** Find the control by what it says, inside the step's scope; never by a class or an id. */
function locate(page, target) {
  let scope = page;
  if (target.within) scope = page.locator(target.within.css || '*', target.within.hasText ? { hasText: target.within.hasText } : {}).filter({ visible: true }).first();
  let loc;
  if (target.role) loc = scope.getByRole(target.role, { name: target.name, exact: target.exact !== false });
  else if (target.css) loc = scope.locator(target.css, target.hasText ? { hasText: target.hasText } : {});
  else if (target.label) loc = scope.getByLabel(target.label, { exact: target.exact !== false });
  else if (target.text) loc = scope.getByText(target.text, { exact: target.exact !== false });
  else throw new Error('a step names no control');
  return loc.filter({ visible: true }).nth(target.nth || 0);
}

// The WordPress toolbar is 32 CSS pixels high; whatever the rig scrolls into view sits at least 16 below it.
const TOOLBAR = 32;
const CLEAR = TOOLBAR + 16;

/**
 * The scroll position that puts the element `off` pixels from the top (never closer than CLEAR), moved so no card
 * or section around it has its head tucked under the toolbar: a head that would be cut is shown whole instead,
 * its top 8 pixels below CLEAR.
 */
async function scrollTarget(loc, off) {
  const answer = await loc.evaluate((el, { off, clear, toolbar, limit }) => {
    const elH = el.getBoundingClientRect().height;
    let y = scrollY + el.getBoundingClientRect().top - Math.max(off, clear);
    for (let a = el.parentElement; a && a !== document.body; a = a.parentElement) {
      if (!a.matches('section, article, details')) continue;
      const top = scrollY + a.getBoundingClientRect().top - y;
      const first = a.querySelector(':scope > summary, :scope > header, :scope > h2, :scope > h3') || a.firstElementChild;
      const fh = first ? first.getBoundingClientRect().height : 0;
      const headH = fh > 0 && fh < 120 ? fh : 48;
      if (!(top < clear && top + headH + 8 > 0)) continue;
      // Show the head whole if the element still fits above the line of text; otherwise scroll on until the
      // head is wholly behind the toolbar, as a page scrolled past it shows.
      const up = clear + 8 - top;
      const elTop = scrollY + el.getBoundingClientRect().top - y;
      if (elTop + up + elH <= limit) y -= up; else y += top + headH - toolbar + 2;
    }
    // No line cut in half under the toolbar (1.123.104, round 5): the position nearest the one asked for at which the
    // toolbar's edge falls in the space between two blocks, crossing no line of words and no control, with at least 4px
    // of air above the first line under it; the element still clear of the toolbar and above the line of text. Each
    // position is measured with the page really there, since the frame's side menu is sticky and does not move with the
    // page one for one; the page goes back where it was before the answer.
    // A line is a line of words as drawn (each text node's line boxes, not the boxes around them, which touch one another
    // in a list) or a control's own box.
    const from = scrollY;
    const out = n => n.closest && n.closest('#wpadminbar, #wpcpm-film-overlay');
    const texts = [];
    const walk = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, { acceptNode: t => (t.nodeValue.trim() && !out(t.parentElement) ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_REJECT) });
    while (walk.nextNode()) texts.push(walk.currentNode);
    const controls = [...document.querySelectorAll('input:not([type="hidden"]), select, textarea, button, img, svg')].filter(n => !out(n));
    const crossed = r => r.width > 0 && r.height > 0 && r.height < 160 && r.top < toolbar + 4 && r.bottom > toolbar;
    const clean = v => {
      window.scrollTo({ top: v, behavior: 'instant' });
      // At 1.5 device pixels a CSS pixel the page snaps to a third of a pixel; a position past the page's end is not one.
      if (Math.abs(scrollY - v) >= 1) return false;
      const t = el.getBoundingClientRect().top;
      if (t < clear || t + elH > limit) return false;
      if (controls.some(n => crossed(n.getBoundingClientRect()))) return false;
      const range = document.createRange();
      return !texts.some(tn => { range.selectNodeContents(tn); return [...range.getClientRects()].some(crossed); });
    };
    const want = Math.max(0, Math.round(y));
    let found = null;
    for (let d = 0; d <= 240 && null === found; d += 1) {
      for (const v of d ? [want - d, want + d] : [want]) {
        if (v >= 0 && clean(v)) { found = v; break; }
      }
    }
    if (null !== found) y = found;
    window.scrollTo({ top: from, behavior: 'instant' });
    return { y: Math.max(0, y), found: null !== found, want };
  }, { off, clear: CLEAR, toolbar: TOOLBAR, limit: VIEW.height - CAPTION_BAND - 12 });
  // Said when no clean position was near, so a frame with a cut line is known before it is watched.
  if (!answer.found) console.log(`no position without a cut line near ${answer.want}: kept it`);
  return answer.y;
}

/** Whether the element is whole between the toolbar's clearance and the line of text. */
async function inView(loc) {
  const b = await loc.boundingBox();
  return !!b && b.y >= CLEAR && b.y + b.height <= VIEW.height - CAPTION_BAND - 12;
}

/** Bring the control into the part of the viewport the line of text does not cover; answer its centre. */
async function aim(page, loc) {
  await loc.waitFor({ state: 'visible', timeout: 15000 });
  let box = await loc.boundingBox();
  const top = 70, bottom = VIEW.height - CAPTION_BAND - 12;
  if (!box || box.y < top || box.y + box.height > bottom) {
    const y = await scrollTarget(loc, top + (bottom - top) / 2 - (box ? box.height : 0) / 2);
    await page.evaluate(v => window.scrollTo({ top: v, behavior: 'instant' }), y);
    await page.waitForTimeout(150);
    box = await loc.boundingBox();
  }
  if (!box) throw new Error('the control has no box on screen');
  if (box.y + box.height > VIEW.height - CAPTION_BAND) throw new Error('the control sits under the line of text and cannot be scrolled above it');
  return { x: box.x + Math.min(box.width / 2, 40), y: box.y + box.height / 2, box };
}

async function scrollSmooth(page, toY, frames = 14) {
  const from = await page.evaluate(() => scrollY);
  for (let i = 1; i <= frames; i++) {
    const t = i / frames; const e = t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2;
    await page.evaluate(y => window.scrollTo({ top: y, behavior: 'instant' }), from + (toY - from) * e);
    await frame(page, 1 / FPS);
  }
}

// ---------------------------------------------------------------- a day typed into a date box
// The order this browser's date boxes take their parts in, asked once off camera, on a page of its own in the same
// context: a box typed 12312026 that reads 2026-12-31 is month first, one typed 31122026 day first. Any other order
// stops the filming with its reason.
let DATE_ORDER = null;
async function dateOrder(page) {
  if (DATE_ORDER) return DATE_ORDER;
  const probe = await page.context().newPage();
  const read = async digits => {
    await probe.setContent('<input type="date" id="d" style="width:220px;font:16px sans-serif">');
    await probe.locator('#d').click({ position: { x: 12, y: 10 } });
    await probe.keyboard.type(digits);
    return probe.locator('#d').inputValue();
  };
  if ('2026-12-31' === await read('12312026')) DATE_ORDER = 'MDY';
  else if ('2026-12-31' === await read('31122026')) DATE_ORDER = 'DMY';
  await probe.close();
  if (!DATE_ORDER) throw new Error('stopped: this browser\'s date boxes take neither month, day, year nor day, month, year, so a day cannot be typed into one');
  console.log(`date boxes take ${DATE_ORDER === 'MDY' ? 'month, day, year (mm/dd/yyyy)' : 'day, month, year (dd.mm.yyyy, the Mac\'s own date format)'}`);
  return DATE_ORDER;
}

/** The digits a person types for the day `n` days from today (UTC), in the order the box takes. */
async function typedDay(page, n) {
  const [y, m, d] = new Date(Date.now() + n * 86400000).toISOString().slice(0, 10).split('-');
  return 'MDY' === await dateOrder(page) ? m + d + y : d + m + y;
}

// ---------------------------------------------------------------- the steps
async function runStep(page, step, n) {
  if (step.caption !== undefined) { setCaption(step.caption); stepTimes.push({ n, caption: step.caption, start: +clock.toFixed(2) }); }

  if (step.go) {
    const url = new URL(typeof step.go === 'function' ? step.go() : step.go, BASE);
    if (url.origin !== BASE) throw new Error('refusing a step that leaves the copy');
    await page.goto(url.href, { waitUntil: 'domcontentloaded' });
    await settle(page);
    if (step.scrollTo) await page.evaluate(y => window.scrollTo(0, y), step.scrollTo);
  }

  if (step.park) {
    const spot = step.park === 'clear' ? await clearSpot(page) : step.park;
    if (spot.x < 0 || spot.y < 0 || spot.x + 26 * SCALE > VIEW.width || spot.y + 34 * SCALE > VIEW.height - CAPTION_BAND) throw new Error('a park spot puts the pointer outside the frame');
    await moveTo(page, spot.x, spot.y, step.moveMs || 500);
  }

  if (step.scrollToControl) {
    const loc = locate(page, step.scrollToControl);
    // The pointer waits in the right margin while the page scrolls, off the text being read.
    if (!step.park) { const spot = await clearSpot(page); await moveTo(page, spot.x, spot.y, 420); }
    await loc.waitFor({ state: 'visible', timeout: 15000 });
    const y = await scrollTarget(loc, step.offset ?? 90);
    await scrollSmooth(page, y);
    // The text moved under the pointer: move it again to a place clear of what is now on screen.
    if (!step.park) { const spot = await clearSpot(page); if (Math.hypot(spot.x - pointer.x, spot.y - pointer.y) > 4) await moveTo(page, spot.x, spot.y, 300); }
  }

  if (step.press) {
    const loc = locate(page, step.press);
    const at = await aim(page, loc);
    await moveTo(page, at.x, at.y, step.moveMs || 650);
    await frame(page, 0.25);

    const loadedBefore = await page.evaluate(() => performance.timeOrigin);
    let dialogMessage = null;
    if (step.confirm) {
      // A browser question cannot be photographed while it is open: press once and cancel it to read
      // its words, draw them where the browser shows its question, point at OK, then press again and accept.
      page.once('dialog', d => { dialogMessage = d.message(); d.dismiss(); });
      await loc.click();
      await page.waitForTimeout(300);
      if (!dialogMessage) throw new Error('the press asked no question');
      if (step.confirm.expect && !dialogMessage.includes(step.confirm.expect)) throw new Error('the question says something else than the shot expects');
      // Titled as Chrome titles it on the real site.
      const dlg = { message: dialogMessage, title: 'wordpresseducation.org says' };
      await frame(page, 0.4, { dialog: dlg });
      if (step.confirm.caption) { setCaption(step.confirm.caption); stepTimes.push({ n: n + 0.5, caption: step.confirm.caption, start: +clock.toFixed(2) }); }
      stepFrames.push({ name: `${String(n).padStart(2, '0')}-question`, file: await frame(page, step.confirm.read || 3.2, { dialog: dlg }) });
      const ok = await page.evaluate(() => { const b = document.querySelector('#wpcpm-film-overlay .dlg-ok'); if (!b) return null; const r = b.getBoundingClientRect(); return { x: r.x + r.width / 2, y: r.y + r.height / 2 }; });
      const sx = pointer.x, sy = pointer.y, steps = 14;
      for (let i = 1; i <= steps; i++) { const t = i / steps; pointer.x = sx + (ok.x - sx) * t; pointer.y = sy + (ok.y - sy) * t; await frame(page, 1 / FPS, { dialog: dlg }); }
      await frame(page, 0.35, { dialog: { ...dlg, pressed: true }, mark: true });
      page.once('dialog', d => d.accept());
      await Promise.all([page.waitForLoadState('domcontentloaded').catch(() => {}), loc.click({ noWaitAfter: false })]);
    } else {
      stepFrames.push({ name: `${String(n).padStart(2, '0')}-press`, file: await frame(page, 0.3, { mark: true }) });
      await loc.click();
    }
    await settle(page);
    // `land`: a press that loads a page settles at once where the shot names, before any frame of the new page is
    // taken, so no landing is ever seen half under the toolbar (1.123.104, round 5).
    if (step.land) {
      const to = locate(page, step.land);
      await to.waitFor({ state: 'visible', timeout: 15000 });
      const y = await scrollTarget(to, step.land.offset ?? 56);
      await page.evaluate(v => window.scrollTo({ top: v, behavior: 'instant' }), y);
    }
    // The real mouse stays where the press was; after a reload that spot may be another control, drawn in its
    // hover state. Move it to a place clear of every control (the drawn pointer does not move).
    { const spot = await clearSpot(page); await page.mouse.move(spot.x, spot.y); }
    // A press that loaded a new page leaves the drawn pointer over whatever is now there: move it to the nearest
    // clear spot before the result is held.
    if (!step.keepPointer && (await page.evaluate(() => performance.timeOrigin)) !== loadedBefore) {
      const spot = await clearSpot(page); if (Math.hypot(spot.x - pointer.x, spot.y - pointer.y) > 4) await moveTo(page, spot.x, spot.y, 300);
    }
    if (step.press.expectUrl && !new RegExp(step.press.expectUrl).test(page.url())) throw new Error(`the press did not land where the shot expects (${page.url().replace(BASE, '')})`);
  }

  if (step.key) { // a key press, shown as a key cap above the line of text
    await frame(page, 0.5, { keycap: step.key });
    await frame(page, 0.25, { keycap: step.key, keyDown: true });
    await page.keyboard.press(step.key);
    await page.waitForTimeout(300);
    await frame(page, 0.6, { keycap: step.key });
  }

  if (step.type) {
    const loc = locate(page, step.type);
    let at = null;
    if (!step.type.focused) {
      at = await aim(page, loc);
      // A date box is typed from its first part, whatever order the browser draws it in: the pointer and the press go
      // 16 pixels in, on that part.
      if (await loc.evaluate(el => el.type === 'date')) at.x = at.box.x + 16;
      await moveTo(page, at.x, at.y, step.moveMs || 650);
      await frame(page, 0.3, { mark: true });
    } else if (!(await loc.evaluate(el => el === document.activeElement))) throw new Error('the box the shot types into does not have the focus');
    // The press lands where the pointer is drawn, near the box's start: in a date box that is its first part, the
    // month in the en-US browser, so the digits typed fill the day from its start (1.123.104, the talks clips).
    if (!step.type.focused) await loc.click({ position: { x: at.x - at.box.x, y: at.box.height / 2 } });
    // `day: n` types the day n days from today in the box's own order; `value` types the words as given.
    const typed = undefined !== step.type.day ? await typedDay(page, step.type.day) : step.type.value;
    for (const ch of typed) { await page.keyboard.type(ch); await page.waitForTimeout(40); await frame(page, 0.09); }
    await settle(page);
  }

  if (step.waitFor) {
    const w = step.waitFor;
    const loc = w.role ? page.getByRole(w.role, { name: w.name, exact: w.exact !== false }) : page.getByText(w.text, { exact: w.exact === true });
    await loc.filter({ visible: true }).first().waitFor({ state: 'visible', timeout: 20000 });
    // After a reload the page comes back where the site puts it; the rig scrolls only when the thing to read is
    // out of view.
    if (w.scrollIntoView && !(await inView(loc.filter({ visible: true }).first()))) {
      const y = await scrollTarget(loc.filter({ visible: true }).first(), w.offset ?? 120);
      await scrollSmooth(page, y, 16);
      if (!step.park) { const spot = await clearSpot(page); await moveTo(page, spot.x, spot.y, 300); }
    }
  }

  if (step.choose) { // pick one entry of a drop-down list by its visible text
    const loc = locate(page, step.choose);
    const at = await aim(page, loc);
    await moveTo(page, at.x, at.y, step.moveMs || 650);
    await frame(page, 0.3, { mark: true });
    await loc.selectOption({ label: step.choose.option });
    await page.waitForTimeout(200);
  }

  if (step.hover) { // point at a control and rest there, as the mouse does to open a menu
    const loc = locate(page, step.hover);
    const at = await aim(page, loc);
    await moveTo(page, at.x, at.y, step.moveMs || 650);
    await loc.hover();
    await page.waitForTimeout(300);
  }

  if (step.pointerTo) { // move the pointer to a thing to look at, without pressing: its tip just past the words' end
    const at = await aim(page, locate(page, step.pointerTo));
    const end = await locate(page, step.pointerTo).evaluate(el => {
      const r = document.createRange(); r.selectNodeContents(el); const rs = [...r.getClientRects()].filter(b => b.width);
      const b = rs.length ? rs[rs.length - 1] : el.getBoundingClientRect(); return { x: b.right, y: b.top + b.height * 0.55 };
    });
    let x = Math.min(end.x + 10, VIEW.width - 26 * SCALE - 8), y = end.y;
    // The arrow hangs down and to the right of its tip: where that would cover the next line, take the nearest
    // spot clear of every word and control, if one is close.
    if (!step.pointerTo.onWords) {
      const near = await clearSpot(page, { x, y }, { left: 4, right: 40, up: 14, down: 10, margin: 3 });
      if (near) { x = near.x; y = near.y; }
    }
    await moveTo(page, step.pointerTo.onWords ? at.x : x, step.pointerTo.onWords ? at.y : y, step.moveMs || 650);
  }

  const file = await hold(page, step.hold ?? 2.5);
  stepFrames.push({ name: String(n).padStart(2, '0'), file });
  if (step.poster) posterFrame = file;
}

// ---------------------------------------------------------------- run
let failed = null;
try {
  if (shot.prepare && !opt('skip-prepare')) {
    // Every shot starts from the state copy.sh prepare left (snapshot clips-base), so it can be filmed again
    // and again, in any order, and builds its own state from there through the site's own forms.
    console.log('restore clips-base, then prepare (off camera)...');
    execFileSync(join(DOCKER, 'bin/restore'), ['clips-base'], { cwd: DOCKER, stdio: 'ignore' });
    rmSync(join(DOCKER, 'site/wp-content/wpcpm-filming-links.log'), { force: true });
    await shot.prepare({ browser, newContext, BASE, wp });
  }
  if (opt('prepare-only')) { await browser.close(); console.log('prepared; not filmed'); process.exit(0); }
  const context = await newContext({ signedIn: shot.signedIn !== false });
  const page = await context.newPage();
  page.on('pageerror', e => console.log('page error:', String(e.message).split('\n')[0]));
  for (const [i, step] of shot.steps.entries()) {
    process.stdout.write(`step ${i + 1}/${shot.steps.length}${step.caption ? ': ' + step.caption : ''}\n`);
    await runStep(page, step, i + 1);
  }
  setCaption('');
  await context.close();
} catch (e) {
  failed = e;
} finally {
  await browser.close();
}
if (refusedHosts.size) console.log('requests to other hosts refused:', [...refusedHosts].join(', '));
if (failed) { console.error(String(failed.message || failed)); process.exit(1); }

// ---------------------------------------------------------------- encode
const duration = +clock.toFixed(3);
const list = frames.map(f => `file '${f.file}'\nduration ${f.seconds.toFixed(4)}`).join('\n') + `\nfile '${frames[frames.length - 1].file}'\n`;
writeFileSync(join(WORK, 'frames.txt'), list);
const mp4 = join(OUT, slug + '.mp4');
const crf = String(shot.crf || 30);
execFileSync(FFMPEG, ['-y', '-hide_banner', '-loglevel', 'error', '-f', 'concat', '-safe', '0', '-i', join(WORK, 'frames.txt'),
  '-vf', `fps=${FPS},scale=${OUT_SIZE.width}:${OUT_SIZE.height}:flags=lanczos,format=yuv420p`, '-c:v', 'libx264', '-preset', 'veryslow', '-tune', 'stillimage',
  '-crf', crf, '-profile:v', 'high', '-level', '4.1', '-g', String(FPS * 4), '-an', '-movflags', '+faststart', '-t', String(duration), mp4], { stdio: 'inherit' });

// One full-size frame for every step, as it is held, for review.
const STEPS_DIR = join(OUT, 'frames', slug);
rmSync(STEPS_DIR, { recursive: true, force: true });
mkdirSync(STEPS_DIR, { recursive: true });
for (const sf of stepFrames) execFileSync(FFMPEG, ['-y', '-hide_banner', '-loglevel', 'error', '-i', sf.file, '-q:v', '2', join(STEPS_DIR, sf.name + '.jpg')]);

const poster = join(OUT, slug + '.jpg');
execFileSync(FFMPEG, ['-y', '-hide_banner', '-loglevel', 'error', '-i', posterFrame || frames[Math.floor(frames.length / 2)].file, '-vf', 'scale=1280:-2:flags=lanczos', '-q:v', '3', poster]);

const ts = s => { const ms = Math.round(s * 1000); const h = Math.floor(ms / 3600000), m = Math.floor(ms / 60000) % 60, sec = Math.floor(ms / 1000) % 60, r = ms % 1000; return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(sec).padStart(2, '0')}.${String(r).padStart(3, '0')}`; };
const cueText = t => t.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const vtt = 'WEBVTT\n\n' + cues.filter(c => c.text).map((c, i) => `${i + 1}\n${ts(c.start)} --> ${ts(Math.min(c.end ?? duration, duration))}\n${cueText(c.text)}\n`).join('\n');
writeFileSync(join(OUT, slug + '.vtt'), vtt);

const meta = {
  slug, width: OUT_SIZE.width, height: OUT_SIZE.height, viewport: `${VIEW.width}x${VIEW.height} CSS at ${DSF}`, duration, caption: shot.caption,
  steps: cues.filter(c => c.text).map(c => ({ text: c.text, start: +c.start.toFixed(2), end: +Math.min(c.end ?? duration, duration).toFixed(2) })),
  bytes: statSync(mp4).size, filmed: new Date().toISOString().slice(0, 10), site: BASE,
};
writeFileSync(join(OUT, slug + '.json'), JSON.stringify(meta, null, 2) + '\n');
console.log(`${slug}: ${meta.width}x${meta.height}, ${duration.toFixed(1)} s, ${meta.bytes} bytes, ${frames.length} frames -> ${mp4}`);
