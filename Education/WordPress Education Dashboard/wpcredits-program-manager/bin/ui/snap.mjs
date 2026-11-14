#!/usr/bin/env node
// Photographs every screen and open state of scenes.mjs on the local copy: full-page PNGs at 1280 wide
// (scale 1) and 390 wide (scale 2), and 768 for release 2's wp-admin tabs when --sizes asks for it; nothing
// drawn over the page. On the local copy only, prepared by bin/ui/copy.sh prepare.
//
//   node bin/ui/snap.mjs --out <folder> [--only <comma list>] [--skip-seed] [--sizes 1280,390] [--no-extra]
//
// --out is a folder outside this repository or under .superpowers/ (never committed); it gets the pictures and
// results.json. --only takes comma-separated parts of file names. Without --skip-seed the copy is restored to
// ui-base, the invented items are made through the site's own forms (seed.mjs) and saved as the snapshot
// ui-ready; with it, ui-ready is restored. Before a file is kept the page's whole text, and every field's value,
// is read against the names to refuse (real-names.json, collected from the copy by copy.sh prepare, and
// WPCPM_UI_REFUSE, a comma list) and against every address that is not a role address the plugin prints or
// at an invented domain: a page that holds one is not saved and is listed under "Could not capture". A capture taller than 16,000px is cut into -part1, -part2.
//
// Playwright is WPCPM_PLAYWRIGHT, or the release script's copy under the home folder.
import { execFileSync } from 'node:child_process';
import { existsSync, mkdirSync, readFileSync, readdirSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { ADMIN_LOGIN, BASE, DOCKER, cookiesFor, frozenNow, refreshRig, refuseUnlessCopy, rigLoaded, waitForCopy, wp } from './copy.mjs';
import { SIZES, brokenImages, fileName, fill, overflowFacts, overflowLines, prepare, scenes, sectionBox, sizeOf, stage, still, takenAt, widthsOf } from './scenes.mjs';
import { INVENTED, WORK, lookups, seed } from './seed.mjs';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const PLAYWRIGHT = process.env.WPCPM_PLAYWRIGHT || join(homedir(), 'Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs');
const MAX_PX = 16000; // a taller capture is cut into parts

const argv = process.argv.slice(2);
const opt = n => { const i = argv.indexOf('--' + n); return i >= 0 ? (argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : true) : null; };
const OUT = typeof opt('out') === 'string' ? resolve(opt('out')) : '';
if (!OUT) { console.error('usage: node bin/ui/snap.mjs --out <folder> [--only <comma list>] [--skip-seed] [--sizes 1280,390]'); process.exit(2); }
const inRepo = !relative(REPO, OUT).startsWith('..');
if (inRepo && !(OUT + sep).startsWith(join(REPO, '.superpowers') + sep)) throw new Error('refusing: --out is inside the repository but not under .superpowers/ (pictures are never committed)');
const only = typeof opt('only') === 'string' ? opt('only').split(',').filter(Boolean) : null;
// --sizes is any list of widths, every scene taken at each (768 for release 2's tabs only); a scene's own
// widths (the wide tables' views) are added to it. --no-extra leaves those out.
const { base: widths, all: allWidths } = widthsOf(scenes(), typeof opt('sizes') === 'string' ? opt('sizes') : '');
const sizes = (opt('no-extra') ? widths : allWidths).map(sizeOf);

refuseUnlessCopy(BASE);
if (!rigLoaded()) throw new Error('refusing: the rig\'s must-use plugin is not loaded (bin/ui/copy.sh prepare)');
if (refreshRig()) console.log('the copy\'s must-use plugin was older than bin/ui/wpcpm-ui-rig.php: replaced');
mkdirSync(OUT, { recursive: true });

// ---------------------------------------------------------------- names to refuse, read at run time
/** Every word of three letters or more in the plugin's own code (includes/, assets/, blocks/ and the main file) and the seed's invented names, lowercased. */
function wordsOfOwnStrings() {
  const words = new Set();
  const take = text => { for (const w of String(text).match(/\p{L}{3,}/gu) || []) words.add(w.toLowerCase()); };
  const walk = dir => {
    for (const e of readdirSync(dir, { withFileTypes: true })) {
      const p = join(dir, e.name);
      if (e.isDirectory()) walk(p);
      else if (/\.(php|js|json|html|txt)$/.test(e.name)) take(readFileSync(p, 'utf8'));
    }
  };
  for (const d of ['includes', 'assets', 'blocks']) if (existsSync(join(REPO, d))) walk(join(REPO, d));
  take(readFileSync(join(REPO, 'wpcredits-program-manager.php'), 'utf8'));
  for (const v of Object.values(INVENTED)) take(Object.values(v).join(' '));
  return words;
}
const namesFile = join(WORK, 'real-names.json');
if (!existsSync(namesFile)) throw new Error(`refusing: ${namesFile} is missing (bin/ui/copy.sh prepare collects it)`);
const extra = String(process.env.WPCPM_UI_REFUSE || '').split(',');
const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const collected = JSON.parse(readFileSync(namesFile, 'utf8')).map(n => String(n).trim());
const ownWords = wordsOfOwnStrings();
// A one-word entry that is also a word of the plugin's own strings or of the rig's invented names (an
// Administrator's first name that is "Team" or "Design" would refuse every menu that says "Contributor Team
// Matcher" and the invented "Example School of Design") is left out, as the scrub leaves such words out; so is
// the copy's own address. WPCPM_UI_REFUSE's entries are always kept. Only the count is printed.
const leftOut = collected.filter(n => n === ADMIN_LOGIN || (!/\s/.test(n) && ownWords.has(n.toLowerCase())) || /^(?:https?:\/\/)?(?:localhost|127\.0\.0\.1)(?::\d+)?\/?$/i.test(n));
const refuse = [...collected.map((n, i) => ({ n, i })).filter(({ n }) => !leftOut.includes(n)), ...extra.map(n => ({ n: String(n).trim(), i: 'extra' }))].filter(({ n }) => n.length >= 3)
  .map(({ n, i }) => ({ re: new RegExp('(?<![\\p{L}\\p{N}])' + esc(n) + '(?![\\p{L}\\p{N}])', n.length > 4 ? 'iu' : 'u'), len: n.length, i }));
console.log(`names to refuse: ${refuse.length} (${leftOut.length} one-word entries left out as words of the plugin's own strings or the invented names)`);
// The role addresses the plugin's own strings print (a grep for @wordpress in includes/), each by its exact
// address, and the invented domains (example.org, example.com, the scrub's example.test, the copy's localhost).
const ROLE_ADDRESSES = ['education', 'info'].map(box => `${box}@wordpressfoundation.org`);
const okMail = m => ROLE_ADDRESSES.includes(m.toLowerCase()) || /@(example\.(org|com|test)|localhost)$/i.test(m);
function problem(text) {
  text = text.replace(/[‘’]/g, "'").replace(/[“”]/g, '"');
  for (const r of refuse) if (r.re.test(text)) return `a real name (list entry ${r.i}, ${r.len} characters)`;
  // An address the page's text runs straight into the next word (an address at example.test followed at once by "Received") is read up to that
  // word: a lowercase domain cut where a capital begins. A domain written in capitals is never cut.
  // So is an address at an invented domain run straight into a lowercase word (an address at example.com followed at once by "new", a tag's word
  // in innerText): only the invented domains are read that way.
  for (const m of text.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi) || []) if (!okMail(m.replace(/(@[a-z0-9.-]+\.[a-z]{2,}?)[A-Z].*$/, '$1')) && !okMail(m) && !okMail(m.replace(/(@example\.(?:org|com|test))[a-z]+$/i, '$1'))) return 'an address that is neither a role address the plugin prints nor at an invented domain';
  return '';
}

// ---------------------------------------------------------------- the browser and the copy's state
const { chromium } = await import(pathToFileURL(PLAYWRIGHT).href);
// Drawn in software, in one colour profile, never in tiles drawn apart: on the GPU two runs of the same page
// differed in a few antialiased pixels of rounded corners at scale 2.
const STILL_ARGS = ['--hide-scrollbars', '--disable-gpu', '--disable-gpu-compositing', '--disable-gpu-rasterization', '--disable-partial-raster', '--disable-skia-runtime-opts', '--force-color-profile=srgb', '--disable-lcd-text', '--font-render-hinting=none'];
const browser = await chromium.launch({ channel: 'chrome', headless: true, args: STILL_ARGS });
async function newContext({ signedIn = true, login = ADMIN_LOGIN, size = SIZES[0] } = {}) {
  const context = await browser.newContext({ viewport: { width: size.width, height: size.height }, deviceScaleFactor: size.scale, acceptDownloads: true, locale: 'en-US', timezoneId: 'UTC', isMobile: size.width < 600, hasTouch: size.width < 600 });
  // Nothing leaves the copy: another origin's image is a blank pixel, anything else is refused.
  await context.route('**/*', route => {
    const u = new URL(route.request().url());
    if (u.origin === BASE || u.protocol === 'data:' || u.protocol === 'blob:') return route.continue();
    if (route.request().resourceType() === 'image') return route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64') });
    return route.abort();
  });
  if (signedIn) await context.addCookies(cookiesFor(login));
  await still(context, NOW);
  return context;
}
let NOW = 0; // the frozen moment, once the copy holds one
const docker = (cmd, name) => execFileSync(join(DOCKER, 'bin', cmd), [name], { cwd: DOCKER, stdio: 'ignore' });
// A restore restarts WordPress: nothing goes on until the copy answers 200 again (its proxy restarted if not).
const restore = async name => { docker('restore', name); await waitForCopy(); };

try {
  if (!opt('skip-seed')) {
    console.log('restore ui-base, then make the invented items through the site\'s own forms...');
    await restore('ui-base');
    execFileSync('rm', ['-f', join(DOCKER, 'site/wp-content/wpcpm-ui-rig-links.log'), join(DOCKER, 'snapshots/ui-ready.tgz')]);
    await seed(newContext, BASE);
    // The moment the relative-time words and the pages' scripts read from now on, kept in ui-ready.
    wp(['option', 'update', 'wpcpm_ui_rig_now', String(Math.floor(Date.now() / 60000) * 60)]);
    docker('snapshot', 'ui-ready');
  } else {
    await restore('ui-ready');
    if (!frozenNow()) {
      wp(['option', 'update', 'wpcpm_ui_rig_now', String(Math.floor(Date.now() / 60000) * 60)]);
      execFileSync('rm', ['-f', join(DOCKER, 'snapshots/ui-ready.tgz')]); // bin/snapshot will not write over one
      docker('snapshot', 'ui-ready');
      console.log('ui-ready held no frozen moment: one was stored and ui-ready saved again');
    }
  }
  NOW = frozenNow();
  console.log(`the pages read the time as ${new Date(NOW * 1000).toISOString()}`);
  // The copy's own banners stay on the page in the pictures.
  wp(['option', 'update', 'wpcpm_ui_rig_keep_banners', '1']);
  const values = lookups();

  // -------------------------------------------------------------- capture
  const results = [];
  async function capture(scene, size) {
    const file = fileName(scene, size);
    if (only && !only.some(o => file.includes(o))) return;
    const row = { file: file + '.png', path: scene.path, open: scene.state, prepared: scene.note || '', how: '', ...(scene.panelBuild ? { build: scene.panelBuild } : {}) };
    const filled = fill(scene, values);
    if (!filled) { row.skipped = 'not captured: an invented item it needs is not on the copy'; results.push(row); console.log('  FAIL', file, row.skipped); return; }
    row.path = filled.path;
    const context = await newContext({ login: filled.login || ADMIN_LOGIN, size });
    await prepare(context, filled);
    const page = await context.newPage();
    try {
      row.how = await stage(page, filled, BASE);
      const text = await page.evaluate(() => { const f = document.querySelector('.global-footer'); const t = document.body.innerText; return f ? t.replace(f.innerText, f.innerText.replace(/WordPress\.com/g, '')) : t; })
        + ' ' + await page.evaluate(() => [...document.querySelectorAll('input:not([type=hidden]):not([type=password]),textarea,select')].map(e => (e.tagName === 'SELECT' ? [...e.options].map(o => o.text).join(' ') : e.value)).join(' '));
      // A picture of the copy's own that did not load (the badge's 429) would differ from run to run: not saved.
      const lost = await brokenImages(page, BASE);
      if (lost.length) { row.skipped = `not saved: a picture of the copy did not load (${lost.map(u => u.slice(BASE.length)).join(', ')})`; results.push(row); console.log('  SKIP', file, row.skipped); return; }
      const bad = problem(text);
      if (bad) { row.skipped = `not saved: ${bad} on the page`; results.push(row); console.log('  SKIP', file, row.skipped); return; }
      // The overflow check, against the emulated window's width: a line per fault in overflow.txt, none when nothing is wider.
      row.overflow = overflowLines(file, await page.evaluate(overflowFacts, size.width));
      for (const l of row.overflow) console.log('  ' + l);
      const height = await page.evaluate(() => Math.max(document.documentElement.scrollHeight, document.body.scrollHeight));
      if (filled.section) {
        const b = await sectionBox(page, filled.section);
        if (!b || b.height < 10) throw new Error(`the section ${filled.section} has no height on the page`);
        await page.screenshot({ path: join(OUT, file + '.png'), clip: { x: 0, y: b.y, width: size.width, height: Math.min(b.height, Math.floor(MAX_PX / size.scale)) }, fullPage: true, animations: 'disabled', caret: 'hide' });
        row.how = (row.how ? row.how + '; ' : '') + `a ${b.height}px-tall part of a page ${height}px tall`;
      } else if (filled.viewportOnly) {
        const top = Math.min(filled.firstPx || size.height, height);
        await page.screenshot({ path: join(OUT, file + '.png'), clip: { x: 0, y: 0, width: size.width, height: top }, fullPage: true, animations: 'disabled', caret: 'hide' });
        row.how = (row.how ? row.how + '; ' : '') + `the first ${top}px of a page ${height}px tall`;
      } else if (height * size.scale <= MAX_PX) {
        await page.screenshot({ path: join(OUT, file + '.png'), fullPage: true, animations: 'disabled', caret: 'hide' });
      } else {
        const step = Math.floor(MAX_PX / size.scale);
        const parts = [];
        for (let y = 0, n = 1; y < height; y += step, n++) {
          const name = `${file}-part${n}.png`;
          await page.screenshot({ path: join(OUT, name), clip: { x: 0, y, width: size.width, height: Math.min(step, height - y) }, fullPage: true, animations: 'disabled', caret: 'hide' });
          parts.push(name);
        }
        row.file = parts.join(', ');
        row.how = (row.how ? row.how + '; ' : '') + `${height}px tall, cut into ${parts.length} parts`;
      }
      results.push(row);
      console.log('  ok', file);
    } catch (e) {
      row.skipped = 'not captured: ' + String(e.message || e).split('\n')[0];
      results.push(row);
      console.log('  FAIL', file, row.skipped);
    } finally {
      await context.close();
    }
  }

  for (const scene of scenes()) for (const size of sizes) if (takenAt(scene, size, widths)) await capture(scene, size);

  // With --only, the rows of the files taken again replace their rows in the earlier results.
  const RES = join(OUT, 'results.json');
  let all = results;
  if (only && existsSync(RES)) {
    const base = r => r.file.split(',')[0].replace(/-part\d+\.png$|\.png$/, '');
    const fresh = new Map(results.map(r => [base(r), r]));
    const earlier = JSON.parse(readFileSync(RES, 'utf8'));
    all = earlier.map(r => fresh.get(base(r)) || r);
    for (const r of results) if (!earlier.some(e => base(e) === base(r))) all.push(r);
  }
  writeFileSync(RES, JSON.stringify(all, null, 2));
  const checked = all.filter(r => Array.isArray(r.overflow));
  const faults = checked.flatMap(r => r.overflow);
  writeFileSync(join(OUT, 'overflow.txt'), faults.concat(`${checked.length} pictures checked, ${faults.length} lines`).join('\n') + '\n');
  console.log(`overflow check: ${checked.length} pictures checked, ${faults.length} lines (overflow.txt)`);
  const lost = results.filter(r => r.skipped);
  console.log(`${results.length - lost.length} captured, ${lost.length} not`);
  if (lost.length) {
    console.log('\nCould not capture');
    for (const r of lost) console.log(`- ${r.file} (${r.path}): ${r.skipped}`);
  }
} finally {
  await browser.close();
  try { wp(['option', 'delete', 'wpcpm_ui_rig_keep_banners']); } catch {}
}
