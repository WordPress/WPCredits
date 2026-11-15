#!/usr/bin/env node
// Photographs every screen state an Administrator meets, for the layout audit: full-page PNGs at 1280 wide (scale 1)
// and 390 wide (scale 2), nothing drawn over the page. On the local copy only.
//
//   node bin/clips/snap.mjs [--skip-prepare] [--prepare-only] [--only <text>] [--out <folder>] [--proto]
//
// --only takes comma-separated parts of file names; --out writes the pictures (and results.json) to another folder
// under ui-audit/ (for example `after`); --proto sets the cookie that makes ui-audit/proto/wpcpm-proto.php load the
// prototype stylesheet and script (the must-use plugin must be in place on the copy).
//
// Without --skip-prepare it restores clips-base (made by copy.sh prepare), makes the invented items through the
// site's own forms (_lib.mjs) and saves them as the snapshot audit-ready; with it, it restores audit-ready. Before a
// file is kept, the page's whole text is checked against the real names; a page that holds one is not saved and is
// listed under "Could not capture".
import { homedir } from 'node:os';
// playwright-core as the release tools keep it on this Mac (nothing is installed for the rig).
const { chromium } = await import(homedir() + '/Projects/wpcredits-release/a11y/node_modules/playwright-core/index.mjs');
import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync, readFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { BASE, DOCKER, refuseUnlessCopy, filmingLoaded, cookiesFor, wp, ownerWords } from './copy.mjs';
import * as lib from './shots/_lib.mjs';

const HERE = dirname(fileURLToPath(import.meta.url));
const REPO = resolve(HERE, '../..');
const OUT = join(REPO, '.superpowers/guides/ui-audit');
const WORK = join(REPO, '.superpowers/guides/clips/work');
const argv = process.argv.slice(2);
const opt = n => { const i = argv.indexOf('--' + n); return i >= 0 ? (argv[i + 1] && !argv[i + 1].startsWith('--') ? argv[i + 1] : true) : null; };
const OUTDIR = argv.includes('--out') ? join(OUT, argv[argv.indexOf('--out') + 1]) : null;
const SHOTS = OUTDIR || join(OUT, 'shots');
const SIZES = [{ width: 1280, height: 800, scale: 1 }, { width: 390, height: 844, scale: 2 }];
const MAX_PX = 16000; // a taller capture is cut into parts

refuseUnlessCopy(BASE);
if (!filmingLoaded()) throw new Error('refusing: the filming plugin is not loaded (bin/clips/copy.sh prepare)');
mkdirSync(SHOTS, { recursive: true });

// ---------------------------------------------------------------- names to refuse
const realNames = JSON.parse(readFileSync(join(WORK, 'real-names.json'), 'utf8'));
const esc = s => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const refuse = [...realNames, ...ownerWords()].filter(n => n.trim().length >= 3).map(n => ({ re: new RegExp('(?<![\\p{L}\\p{N}])' + esc(n.trim()) + '(?![\\p{L}\\p{N}])', n.trim().length > 4 ? 'iu' : 'u'), len: n.trim().length }));
const okMail = /@(example(\.[a-z]*)?|localhost|wordpress\.(org|net|com)|w\.org|wordpressfoundation\.org|[a-z0-9.-]*\.wordpress\.(org|net))$/i;
function problem(text) {
  text = text.replace(/[\u2018\u2019]/g, "'").replace(/[\u201C\u201D]/g, '"');
  for (const [i, r] of refuse.entries()) if (r.re.test(text)) return `a real name (list entry ${i}, ${r.len} characters)`;
  for (const m of text.match(/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/gi) || []) if (!okMail.test(m)) return 'an address not at an example domain';
  return '';
}

// ---------------------------------------------------------------- the copy's state
const browser = await chromium.launch({ channel: 'chrome', headless: true, args: ['--hide-scrollbars'] });
async function newContext({ signedIn = true, login = 'wpcredits-local', size = SIZES[0] } = {}) {
  const context = await browser.newContext({ viewport: { width: size.width, height: size.height }, deviceScaleFactor: size.scale, acceptDownloads: true, locale: 'en-US', timezoneId: 'UTC', isMobile: size.width < 600, hasTouch: size.width < 600 });
  await context.route('**/*', route => {
    const u = new URL(route.request().url());
    if (u.origin === BASE || u.protocol === 'data:' || u.protocol === 'blob:') return route.continue();
    if (route.request().resourceType() === 'image') return route.fulfill({ status: 200, contentType: 'image/gif', body: Buffer.from('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7', 'base64') });
    return route.abort();
  });
  if (signedIn) await context.addCookies(cookiesFor(login));
  if (opt('proto')) await context.addCookies([{ name: 'wpcpm_proto', value: '1', domain: 'localhost', path: '/' }]);
  return context;
}
const restore = name => execFileSync(join(DOCKER, 'bin/restore'), [name], { cwd: DOCKER, stdio: 'ignore' });

const NORTHFIELD = { name: 'Northfield College', city: 'Northfield', website: 'northfield.example.org', contact: 'Alex Example', email: 'alex.example@example.org', signer: 'Sam Example' };
const UNIVERSITY = { name: 'Example University', city: 'Exampleton', website: 'university.example.org', contact: 'Jordan Example', email: 'jordan.example@example.org', signer: 'Robin Example' };
const DESIGN = { name: 'Example School of Design', city: 'Hillford', website: 'design.example.org', contact: 'Quinn Example', email: 'quinn.example@example.org' };
const TAYLOR = { name: 'Taylor Example', email: 'taylor.example@example.org', profile: 'https://profiles.wordpress.org/taylor-example/', hours: 4 };

if (!opt('skip-prepare')) {
  console.log('restore clips-base, then prepare the invented items (off camera)...');
  restore('clips-base');
  execFileSync('rm', ['-f', join(DOCKER, 'site/wp-content/wpcpm-filming-links.log')]);
  // A school with its agreement accepted, two students enrolled (each raises "A student to add"), a semester report
  // drafted and waiting for review, an invitation waiting and a tutor listed. Setting switched: enrollment lists.
  await lib.settledSchool(newContext, BASE, DESIGN, join(WORK, 'design-agreement.pdf'));
  lib.setSetting('import_enabled', true);
  const said = await lib.enrollStudents(newContext, BASE, DESIGN.name, [{ name: 'Drew Example', email: 'drew.example@example.org' }, { name: 'Sky Example', email: 'sky.example@example.org' }], { start: '2026-02-02', end: '2026-06-26' });
  if (!/Done: 2 students created/.test(said)) throw new Error('the students were not enrolled');
  await lib.draftReport(newContext, BASE, DESIGN.name, 'January to June 2026');
  await lib.inviteAndTutor(newContext, BASE, lib.loginOf(DESIGN.email));
  // A school whose signed agreement waits for review.
  await lib.applySchool(newContext, BASE, UNIVERSITY);
  await lib.approveSchool(newContext, BASE, UNIVERSITY.name);
  await lib.uploadAgreement(newContext, BASE, lib.loginOf(UNIVERSITY.email), join(WORK, 'university-agreement.pdf'));
  // An application waiting, and a mentor application waiting.
  await lib.applySchool(newContext, BASE, NORTHFIELD);
  await lib.applyMentor(newContext, BASE, TAYLOR);
  try { execFileSync('rm', ['-f', join(DOCKER, 'snapshots/audit-ready.tgz')]); } catch {}
  execFileSync(join(DOCKER, 'bin/snapshot'), ['audit-ready'], { cwd: DOCKER, stdio: 'ignore' });
} else {
  restore('audit-ready');
}
// The copy's own banners stay on the page for the audit.
wp(['option', 'update', 'wpcpm_filming_keep_banners', '1']);

if (opt('prepare-only')) { console.log('prepared: audit-ready'); await browser.close(); process.exit(0); }
const DESIGN_REC = lib.recordOf(DESIGN.name);
const DESIGN_LOGIN = lib.loginOf(DESIGN.email);
const UNIVERSITY_LOGIN = lib.loginOf(UNIVERSITY.email);

// ---------------------------------------------------------------- helpers run in the page
async function settle(page) {
  try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch {}
  await page.evaluate(() => document.fonts ? document.fonts.ready : null).catch(() => {});
  await page.waitForTimeout(300);
}
/** Press every closed fold's summary inside the scope, outermost first, until none is closed. */
async function openAll(page, scope = 'body', skip = null) {
  for (let round = 0; round < 6; round++) {
    const closed = await page.evaluate(({ scope, skip }) => {
      const root = document.querySelector(scope) || document.body;
      return [...root.querySelectorAll('details:not([open]) > summary')].filter(s => {
        const r = s.getBoundingClientRect(); const d = s.parentElement;
        if (skip && d.matches(skip)) return false;
        return r.width > 0 && r.height > 0 && !d.closest('details:not([open]) details');
      }).length;
    }, { scope, skip });
    if (!closed) return;
    const handles = await page.$$(`${scope} details:not([open]) > summary`);
    for (const h of handles) {
      const ok = await h.evaluate((s, skip) => { const r = s.getBoundingClientRect(); return r.width > 0 && r.height > 0 && !(skip && s.parentElement.matches(skip)); }, skip);
      if (ok) { await h.click({ timeout: 3000 }).catch(() => {}); await page.waitForTimeout(80); }
    }
    await settle(page);
  }
}
async function openSummary(page, text, scope = 'body') {
  const s = page.locator(`${scope} summary`, { hasText: text }).first();
  if (await s.count() && !(await s.evaluate(e => e.parentElement.open))) { await s.click(); await settle(page); }
}

// ---------------------------------------------------------------- the scenes
const DASH = '/administrator-dashboard/';
const ADMIN = p => '/wp-admin/admin.php?page=' + p;
const scenes = [];
const add = (area, screen, state, path, how = {}) => scenes.push({ area, screen, state, path, ...how });

// A. Administrator Dashboard: each view with every card open; then each card on its own, open.
add('admin-dashboard', 'overview', 'closed', DASH, { note: 'as it loads' });
add('admin-dashboard', 'overview', 'all-open', DASH, { act: p => openAll(p, 'main') });
add('admin-dashboard', 'watch', 'closed', DASH + '?wpcpm_view=watch');
add('admin-dashboard', 'watch', 'all-open', DASH + '?wpcpm_view=watch', { act: p => openAll(p, 'main') });
add('admin-dashboard', 'cohort-timeline', 'all-open', DASH + '?wpcpm_view=cohort-timeline', { act: p => openAll(p, 'main') });
add('admin-dashboard', 'the-program', 'all-open', DASH + '?wpcpm_view=reference', { act: p => openAll(p, 'main') });
// One file per card, found on the page: added at run time (cardScenes below).

// B. Institution Dashboard.
add('institution-dashboard', 'admin-viewing-school', 'all-open', `/institution-dashboard/?wpcpm_institution_view=${DESIGN_REC}`, { act: p => openAll(p, 'main'), note: 'Administrator viewing Example School of Design (agreement accepted, 2 students, report drafted)' });
add('institution-dashboard', 'admin-viewing-school', 'report-draft-open', `/institution-dashboard/?wpcpm_report=2026-H1&wpcpm_institution_view=${DESIGN_REC}`, { act: p => openAll(p, 'main'), note: 'the January to June 2026 draft open for editing' });
add('institution-dashboard', 'representative', 'all-open', '/institution-dashboard/', { login: DESIGN_LOGIN, act: p => openAll(p, 'main'), note: "signed in as the school's own representative (Quinn Example)" });
add('institution-dashboard', 'representative-agreement-in-review', 'all-open', '/institution-dashboard/', { login: UNIVERSITY_LOGIN, act: p => openAll(p, 'main'), note: 'representative of Example University, whose signed agreement waits for review' });

// C. Sponsor Dashboard, as the copy holds it (the first sponsor, names replaced).
for (const [v, label] of [['', 'overview'], ['sponsorship', 'your-sponsorship'], ['offers', 'offers'], ['reached', 'students-reached'], ['mentors', 'mentors-to-sponsor'], ['company', 'your-company'], ['agreement', 'agreement']]) {
  add('sponsor-dashboard', label, 'all-open', '/sponsor-dashboard/' + (v ? `?wpcpm_view=${v}` : ''), { act: p => openAll(p, 'main'), note: "the copy's first sponsor, as held (scrubbed, names replaced)" });
}

// D. Report cards, as the Administrator: one scrubbed mentor, one scrubbed student.
add('mentor-report-card', 'admin-viewing-mentor', 'all-open', '/mentor-dashboard/', { pick: 'select[name="wpcpm_mentor"]', act: p => openAll(p, 'main'), note: 'second mentor in Viewing as mentor (scrubbed)' });
add('student-report-card', 'admin-viewing-student', 'all-open', '/student-dashboard/', { pick: 'select[name="wpcpm_student_view"]', act: p => openAll(p, 'main'), note: 'second student in Viewing as student (scrubbed)' });

// E. wp-admin screens.
add('wpadmin', 'overview', 'default', ADMIN('wpcpm'));
for (const t of ['queue', 'pipeline', 'accounts', 'reports', 'agreements', 'sync']) add('wpadmin', `institutions-${t}`, 'default', ADMIN('wpcpm-institutions') + `&tab=${t}`);
add('wpadmin', 'institutions-agreements', 'revoke-open', ADMIN('wpcpm-institutions') + '&tab=agreements', { act: async p => { const s = p.locator('#wpbody-content summary', { hasText: /revoke|out of force/i }).first(); if (!(await s.count())) throw new Error('no revoke fold on the Agreements tab'); await s.click(); await settle(p); }, note: "Example School of Design's accepted agreement; the revoke fold opened" });
add('wpadmin', 'institutions-accounts', 'manage-members', ADMIN('wpcpm-institutions') + `&tab=accounts&wpcpm_institution=${DESIGN_REC}`, { note: 'Manage members of Example School of Design' });
for (const t of ['accounts', 'registrations', 'interests', 'sync']) add('wpadmin', `students-${t}`, 'default', ADMIN('wpcpm-students') + `&tab=${t}`);
for (const t of ['queue', 'accounts', 'engagement', 'sync']) add('wpadmin', `mentors-${t}`, t === 'queue' ? 'application-open' : 'default', ADMIN('wpcpm-mentors') + `&tab=${t}`, t === 'queue' ? { note: "Taylor Example's application, its decision area open (it is drawn open)" } : {});
for (const t of ['queue', 'sponsors', 'accounts', 'offers', 'interests', 'reports', 'agreements', 'listing']) add('wpadmin', `sponsors-${t}`, 'default', ADMIN('wpcpm-sponsors') + `&tab=${t}`, { note: 'as the copy holds it (names replaced)' });
for (const t of ['accounts', 'capabilities']) add('wpadmin', `administrators-${t}`, 'default', ADMIN('wpcpm-administrators') + `&tab=${t}`);
add('wpadmin', 'tools-emails-templates', 'list', ADMIN('wpcpm-tool-emails') + '&tab=templates');
add('wpadmin', 'tools-emails-template-editor', 'invite-institution', ADMIN('wpcpm-tool-emails') + '&tab=templates&edit=invite-institution');
add('wpadmin', 'tools-emails-footer', 'default', ADMIN('wpcpm-tool-emails') + '&tab=footer');
add('wpadmin', 'tools-emails-log', 'default', ADMIN('wpcpm-tool-emails') + '&tab=log');
for (const t of ['connection', 'people', 'institutions', 'sponsors', 'security', 'mail', 'advanced']) add('wpadmin', `settings-${t}`, 'details-open', ADMIN('wpcpm-settings') + `&tab=${t}`, { act: async p => { const s = p.locator('#wpbody-content details:not([open]) > summary').first(); if (await s.count()) { await s.scrollIntoViewIfNeeded(); await s.click(); await settle(p); } }, note: 'the first Details fold opened' });

// F. The guide: the first screen, and one job with its notes (viewport-tall captures; the page is very long).
add('guide', 'top', 'first-screen', '/administrator-guide/', { viewportOnly: true });
add('guide', 'job-a-school-applied', 'with-notes', '/administrator-guide/#a-school-applied', { viewportOnly: true, tall: 2.2, note: 'scrolled to the job by its anchor' });

// ---------------------------------------------------------------- capture
const results = [];
const only = opt('only');
async function capture(scene, size) {
  const file = `${scene.area}-${scene.screen}-${scene.state}-${size.width}`.toLowerCase().replace(/[^a-z0-9-]+/g, '-');
  if (only && !String(only).split(',').some(o => file.includes(o))) return;
  const context = await newContext({ login: scene.login || 'wpcredits-local', size });
  const page = await context.newPage();
  const row = { file: file + '.png', path: scene.path, open: scene.state, prepared: scene.note || '', how: '' };
  try {
    await page.goto(BASE + scene.path, { waitUntil: 'domcontentloaded', timeout: 60000 });
    await settle(page);
    if (scene.pick) {
      const value = await page.evaluate(sel => { const s = document.querySelector(sel); const o = s && [...s.options].filter(o => o.value)[1]; return o ? o.value : ''; }, scene.pick);
      if (value) { const name = await page.evaluate(sel => document.querySelector(sel).name, scene.pick); await page.goto(`${BASE}${scene.path}?${encodeURIComponent(name)}=${encodeURIComponent(value)}`, { waitUntil: 'domcontentloaded' }); await settle(page); row.how = 'picked the second entry of the Viewing as list'; }
    }
    if (scene.act) await scene.act(page);
    if (scene.card) { await openSummary(page, scene.card, 'main'); await openAll(page, `#${scene.cardId}`); }
    // A press that opens a decision's note panel or a list row's form (the panel pictures).
    if (scene.press) {
      const scope = scene.cardId ? `#${scene.cardId}` : (scene.path.includes('/wp-admin/') ? '#wpbody-content' : 'main');
      const b = page.locator(`${scope} button`, { hasText: scene.press }).filter({ hasNotText: 'Cancel' }).nth(scene.pressNth || 0);
      await b.scrollIntoViewIfNeeded(); await b.click(); await page.waitForTimeout(250);
      if ((await b.getAttribute('aria-expanded')) !== 'true') throw new Error(`pressing "${scene.press}" opened nothing`);
      await page.evaluate(() => document.activeElement && document.activeElement.blur());
    }
    // Nothing hovered or menu-open in a picture: Escape, the account menu folded, the pointer parked at 0,0.
    await page.keyboard.press('Escape');
    await page.evaluate(() => document.querySelectorAll('details[open]').forEach(d => { if (d.closest('.wpcpm-frame__account, .wpcpm-frame__top, .global-header, #wpadminbar') || /account/.test(d.className)) d.open = false; }));
    await page.mouse.move(0, 0);
    await page.evaluate(() => { if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); });
    await page.waitForTimeout(200);
    await page.evaluate(() => window.scrollTo(0, 0));
    if (scene.path.includes('#')) { const id = scene.path.split('#')[1]; await page.evaluate(id => { const e = document.getElementById(id); if (e) e.scrollIntoView(); }, id); await page.waitForTimeout(400); }
    const text = await page.evaluate(() => { const f = document.querySelector('.global-footer'); const t = document.body.innerText; return f ? t.replace(f.innerText, f.innerText.replace(/WordPress\.com/g, '')) : t; }) + ' ' + await page.evaluate(() => [...document.querySelectorAll('input:not([type=hidden]):not([type=password]),textarea,select')].map(e => e.tagName === 'SELECT' ? [...e.options].map(o => o.text).join(' ') : e.value).join(' '));
    const bad = problem(text);
    if (bad) { row.skipped = `not saved: ${bad} on the page`; results.push(row); console.log('  SKIP', file, row.skipped); return; }
    const height = await page.evaluate(() => Math.max(document.documentElement.scrollHeight, document.body.scrollHeight));
    if (scene.viewportOnly) {
      const h = Math.round(size.height * (scene.tall || 1));
      const y = await page.evaluate(() => scrollY);
      await page.screenshot({ path: join(SHOTS, file + '.png'), clip: { x: 0, y, width: size.width, height: Math.min(h, height - y) }, fullPage: true });
      row.how = (row.how ? row.how + '; ' : '') + `a ${h}px-tall part of the page (the guide is ${height}px tall)`;
    } else if (height * size.scale <= MAX_PX) {
      await page.screenshot({ path: join(SHOTS, file + '.png'), fullPage: true });
    } else {
      const step = Math.floor(MAX_PX / size.scale);
      const parts = [];
      for (let y = 0, n = 1; y < height; y += step, n++) {
        const name = `${file}-part${n}.png`;
        await page.screenshot({ path: join(SHOTS, name), clip: { x: 0, y, width: size.width, height: Math.min(step, height - y) }, fullPage: true });
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

// The cards each view draws, one file per card.
async function cardScenes() {
  const context = await newContext();
  const page = await context.newPage();
  const found = [];
  for (const [view, path] of [['overview', DASH], ['watch', DASH + '?wpcpm_view=watch']]) {
    await page.goto(BASE + path, { waitUntil: 'networkidle' });
    const cards = await page.evaluate(() => [...document.querySelectorAll('main section[id]')].filter(s => s.querySelector(':scope > details > summary, :scope details > summary')).map(s => ({ id: s.id, title: (s.querySelector('summary h3, summary .wpcpm-group__title, summary') || s).innerText.split('\n')[0].trim() })));
    for (const c of cards) found.push({ view, path, ...c });
  }
  await context.close();
  return found;
}

for (const c of await cardScenes()) {
  const slug = c.title.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
  scenes.push({ area: 'admin-dashboard', screen: `${c.view}-${slug}`, state: 'card-open', path: c.path, card: c.title, cardId: c.id, note: `the ${c.title} card open, with every fold inside it` });
}

// The note panels and row forms, pressed open (fix round): each named after its screen with a suffix.
const PANELS = [
  { screen: 'overview-institution-applications', state: 'ask-panel-open', card: 'Institution applications', cardId: 'wpcpm-applications', press: /^Ask a question$/ },
  { screen: 'overview-institution-applications', state: 'reject-panel-open', card: 'Institution applications', cardId: 'wpcpm-applications', press: /^Reject$/ },
  { screen: 'overview-collaboration-agreements', state: 'return-panel-open', card: 'Collaboration Agreements', cardId: 'wpcpm-agreements', press: /^Return with a note$/ },
  { screen: 'overview-requests-from-institutions', state: 'handled-panel-open', card: 'Requests from institutions', cardId: 'wpcpm-requests', press: /^Mark as handled$/ },
];
for (const p of PANELS) scenes.push({ area: 'admin-dashboard', path: DASH, note: `${p.card}: ${p.state}`, ...p });
scenes.push({ area: 'wpadmin', screen: 'mentors-queue', state: 'ask-panel-open', path: ADMIN('wpcpm-mentors') + '&tab=queue', press: /^Ask for more information$/, note: 'Ask for more information pressed' });
scenes.push({ area: 'wpadmin', screen: 'sponsors-agreements', state: 'record-row-open', path: ADMIN('wpcpm-sponsors') + '&tab=agreements', press: /^Record a signed copy$/, note: 'the first row\'s form open' });
scenes.push({ area: 'wpadmin', screen: 'sponsors-agreements', state: 'revoke-row-open', path: ADMIN('wpcpm-sponsors') + '&tab=agreements', press: /^Revoke$/, note: 'the in-force row\'s Revoke open' });

for (const scene of scenes) for (const size of SIZES) await capture(scene, size);
await browser.close();
wp(['option', 'delete', 'wpcpm_filming_keep_banners']);
// With --only, the rows of the files taken again replace their rows in the earlier results.
const RES = join(OUTDIR || OUT, 'results.json');
let all = results;
if (only && existsSync(RES)) {
  const base = r => r.file.split(',')[0].replace(/-part\d+\.png$|\.png$/, '');
  const fresh = new Map(results.map(r => [base(r), r]));
  const earlier = JSON.parse(readFileSync(RES, 'utf8'));
  all = earlier.map(r => fresh.get(base(r)) || r);
  for (const r of results) if (!earlier.some(e => base(e) === base(r))) all.push(r);
}
writeFileSync(RES, JSON.stringify(all, null, 2));
console.log(`${results.filter(r => !r.skipped).length} captured, ${results.filter(r => r.skipped).length} not`);
