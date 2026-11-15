// Every screen and open state the UI rig photographs (snap.mjs) and measures (measure.mjs), and the steps that
// put a page in that state. Nothing here starts a browser or talks to a site: the steps are handed a page.
//
// A scene is { area, screen, state, path, login?, act?, press?, card?, panelOf?, note?, viewportOnly?, tablet? }.
// Its file is <area>-<screen>-<state>-<width>.png. A path or login may hold a token, {DESIGN_RECORD},
// {DESIGN_LOGIN} or {UNIVERSITY_LOGIN}, which snap.mjs and measure.mjs fill from seed.mjs's lookups().
//   act    { open: scope }        press every closed fold inside the scope, outermost first
//          { openCard: '#id' }    the card with that anchor must be on the page; it and every fold in it open
//          { openFirst: scope }   the first closed fold inside the scope opens
//          { openFold: { text, scope } } the fold whose head reads text opens
//          { pick: selector }     the second entry of a Viewing as list is picked (then `open` may follow)
//   press  { scope, text, nth? }  the opener whose words match the pattern text is pressed and must say it opened
//                                 (aria-expanded="true", or its fold open); a button that is not an opener is
//                                 never pressed, so nothing is ever posted
//   tablet true                   also taken at 768 (release 2's wp-admin tabs only)
//   widths [w, ...]               also taken at these widths, whatever --sizes says
//   onlyWidths [w, ...]           taken at these widths and no other
//   folded true                   the dashboard frame's menu folded, as Collapse menu leaves it: the frame's
//                                 own stored state (localStorage wpcpm-frame-folded = 1), set before the page loads
//   firstPx n                     with viewportOnly: the first n px of the page instead of one screen
//   panelBuild 'after'            a scene only the new build can show (listed as "after only")

export const SIZES = [{ width: 1280, height: 800, scale: 1 }, { width: 390, height: 844, scale: 2 }, { width: 768, height: 1024, scale: 1 }];

/** The size taken at a width: one of SIZES, or that width at 900 tall, scale 1 (2 under 600). */
export function sizeOf(width) {
  const w = Number(width);
  if (!Number.isInteger(w) || w < 320 || w > 2560) throw new Error(`not a width: ${width}`);
  return SIZES.find(s => s.width === w) || { width: w, height: 900, scale: w < 600 ? 2 : 1 };
}

/** Every width a run takes: the --sizes list (1280,390 when it is not given) and every scene's own widths. */
export function widthsOf(list, sizesArg) {
  const base = String(sizesArg || '1280,390').split(',').filter(Boolean).map(Number);
  const extra = list.flatMap(s => [...(s.widths || []), ...(s.onlyWidths || [])]);
  return { base, all: [...new Set([...base, ...extra])] };
}

// The two views whose widest tables stack by the width of the dashboard's main area (a container query at
// 880px, parts.css), taken at the widths around it, and at 1110 and 1200 again with the menu folded.
export const STACK_WIDTHS = [700, 783, 1000, 1015, 1110, 1200];
export const FOLDED_WIDTHS = [1110, 1200];
export const SPONSOR_AGREEMENT_WIDTHS = [601, 782, 1015];
// Gate 4: the widths between the phone and the desktop, where the card heads change their form by the main
// area's width (a container query at 800px, parts.css): the Overview as it loads, all open and with Institution
// applications open, Watch and The program as they load and all open, the menu as the frame draws it there.
export const MID_WIDTHS = [601, 700, 768, 782, 783, 900, 927, 928, 1000, 1024];
// The Events card at 1015 too: an item header whose call link is too long for one line; gate 7: at 601, 768, 928
// and 1024 as well, where its head takes its two forms with no event waiting.
export const EVENTS_WIDTHS = [601, 768, 928, 1015, 1024];
// Gate 7 (round 7b): the card Students waiting to graduate (a table from 880px of main area, stacked below) at every
// width the rig takes.
export const GRADUATION_WIDTHS = [601, 700, 716, 768, 782, 783, 880, 900, 927, 928, 1000, 1015, 1024, 1110, 1200];
// Gate 5: the two reports cards (wide fixed tables, the semester reports stacked below 936px of main area) and
// the Requests card (its Recently closed list in the narrow form) at the widths of the narrow form.
export const REPORTS_WIDTHS = [601, 768, 1015];
export const REQUESTS_WIDTHS = [601, 768];
// Gate 6: Watch with every card open also at 716 (682px of main area, where Syncs and health and Students gone
// quiet take four pairs a line) and 880 (their last stacked width).
export const WATCH_WIDTHS = [716, 880];
const mid = (more = []) => ({ widths: [...new Set([...more, ...MID_WIDTHS])].sort((a, b) => a - b) });

const DASH = '/administrator-dashboard/';
const ADMIN = (page, tab = '') => '/wp-admin/admin.php?page=' + page + (tab ? '&tab=' + tab : '');

// The Administrator Dashboard's cards, by the anchor key each has on the page (wpcpm-<key>), view by view
// (the plan's Tasks 9 to 17).
export const CARDS = {
  overview: { path: DASH, keys: ['mentor-applications', 'applications', 'agreements', 'requests', 'reports', 'sponsor-applications', 'sponsor-posts', 'sponsor-offers-review', 'sponsor-events', 'sponsor-agreements', 'sponsor-reports'] },
  watch: { path: DASH + '?wpcpm_view=watch', keys: ['cohort-timeline', 'offers-low', 'duplicates', 'interests', 'tiers-ending', 'health', 'quiet-students'] },
  'the-program': { path: DASH + '?wpcpm_view=reference', keys: ['sponsors', 'programs', 'mentor-engagement', 'tools'] },
};

// Each Decide panel, by its card and the words of the opener that opens it.
export const PANELS = [
  { card: 'applications', panel: 'ask', text: '^Ask a question' },
  { card: 'applications', panel: 'reject', text: '^Reject' },
  { card: 'agreements', panel: 'return', text: '^Return with a note' },
  { card: 'requests', panel: 'handled', text: '^Mark as handled' },
  { card: 'requests', panel: 'decline', text: '^Decline' },
  { card: 'sponsor-posts', panel: 'return', text: '^Return with a note' },
  { card: 'sponsor-offers-review', panel: 'return', text: '^Return with a note' },
  { card: 'sponsor-events', panel: 'return', text: '^Return with a note' },
  { card: 'sponsor-agreements', panel: 'return', text: '^Return with a note' },
  { card: 'sponsor-applications', panel: 'ask', text: '^Ask a question' },
  { card: 'sponsor-applications', panel: 'reject', text: '^Reject' },
  // The panels the shared parts' build gained (1.123.92): each shows only there.
  { card: 'sponsor-applications', panel: 'approve', text: '^Approve', after: true },
  { card: 'requests', panel: 'details-decline', text: '^Decline', item: 'details', after: true },
  { card: 'sponsor-events', panel: 'cancel-note', text: '^Cancel with a note', after: true },
  { card: 'sponsor-events', panel: 'change', text: '^Change the event', after: true },
  { card: 'sponsor-events', panel: 'cancel-event', text: '^Cancel the event', after: true },
];

// Release 2's wp-admin tabs (design 4.2), the only ones taken at 768 as well.
export const RELEASE_2_TABS = ['wpcpm-sponsors queue', 'wpcpm-sponsors agreements', 'wpcpm-mentors queue', 'wpcpm-students interests', 'wpcpm-institutions queue', 'wpcpm-institutions pipeline', 'wpcpm-institutions accounts', 'wpcpm-institutions agreements', 'wpcpm-tool-emails log'];

/**
 * Every scene, in the order they are taken. `graduation` (gate 7, a students state with somebody pending) adds the
 * card Students waiting to graduate open alone and the two Report Cards of the pending student the state made
 * ({PENDING_STUDENT}, {PENDING_MENTOR}).
 */
export function scenes({ graduation = false } = {}) {
  const list = [];
  const add = (area, screen, state, path, how = {}) => list.push({ area, screen, state, path, ...how });

  // A. The Administrator Dashboard: each view closed and with every fold open, each card open alone, each
  // Decide panel pressed open.
  add('admin-dashboard', 'overview', 'closed', DASH, { note: 'as it loads', ...mid() });
  add('admin-dashboard', 'overview', 'all-open', DASH, { act: { open: 'main' }, ...mid() });
  add('admin-dashboard', 'watch', 'closed', CARDS.watch.path, mid());
  add('admin-dashboard', 'watch', 'all-open', CARDS.watch.path, { act: { open: 'main' }, ...mid(WATCH_WIDTHS) });
  add('admin-dashboard', 'the-program', 'closed', CARDS['the-program'].path, mid());
  add('admin-dashboard', 'the-program', 'all-open', CARDS['the-program'].path, { act: { open: 'main' }, ...mid(STACK_WIDTHS) });
  add('admin-dashboard', 'the-program', 'all-open-menu-folded', CARDS['the-program'].path, { act: { open: 'main' }, onlyWidths: FOLDED_WIDTHS, folded: true, note: 'the side menu folded (Collapse menu)' });
  add('admin-dashboard', 'cohort-timeline', 'closed', DASH + '?wpcpm_view=cohort-timeline');
  add('admin-dashboard', 'cohort-timeline', 'all-open', DASH + '?wpcpm_view=cohort-timeline', { act: { open: 'main' }, widths: STACK_WIDTHS });
  add('admin-dashboard', 'cohort-timeline', 'all-open-menu-folded', DASH + '?wpcpm_view=cohort-timeline', { act: { open: 'main' }, onlyWidths: FOLDED_WIDTHS, folded: true, note: 'the side menu folded (Collapse menu)' });
  for (const [view, { path, keys }] of Object.entries(CARDS)) {
    for (const key of keys) {
      // The mentor applications are a row with no fold: its picture is the row as drawn.
      const state = key === 'mentor-applications' ? 'row' : 'card-open';
      // The sponsor agreements' Out of force rows (a long name beside its tag and Reinstate) at the widths around
      // the facts-under layout's thresholds as well.
      const extra = key === 'sponsor-agreements' ? { widths: SPONSOR_AGREEMENT_WIDTHS } : key === 'applications' ? mid() : key === 'sponsor-events' ? { widths: EVENTS_WIDTHS } : key === 'reports' || key === 'sponsor-reports' ? { widths: REPORTS_WIDTHS } : key === 'requests' ? { widths: REQUESTS_WIDTHS } : {};
      add('admin-dashboard', `${view}-${key}`, state, path, { card: key, act: { openCard: '#wpcpm-' + key }, note: `the card wpcpm-${key} open alone, with every fold inside it`, ...extra });
    }
  }
  for (const p of PANELS) {
    // Only the card's own fold opens first, so the picture differs from card-open (where every fold is open).
    // A panel of one kind of item (the request to correct a school's details) is pressed in that item.
    add('admin-dashboard', `overview-${p.card}`, `${p.panel}-panel-open`, DASH, { panelOf: p.card, panelBuild: p.after ? 'after' : '', act: { openHead: '#wpcpm-' + p.card }, press: { scope: '#wpcpm-' + p.card, text: p.text, item: p.item }, note: `the card's own fold and the ${p.item ? p.item + ' item' : 'first item'}'s ${p.panel} panel open, nothing else` });
  }
  // The Events card's third part, the program's own planner, is a fold of its own.
  add('admin-dashboard', 'overview-sponsor-events', 'plan-fold-open', DASH, { panelOf: 'sponsor-events', panelBuild: 'after', act: { openHead: '#wpcpm-sponsor-events', openFold: { text: '^Plan an event', scope: '#wpcpm-sponsor-events' } }, note: "the card's own fold and Plan an event of the program's open, nothing else" });

  // Gate 7: the card only a students state with somebody pending draws, on the new build only.
  if (graduation) {
    add('admin-dashboard', 'overview-graduation', 'card-open', DASH, { card: 'graduation', panelBuild: 'after', act: { openCard: '#wpcpm-graduation' }, note: 'the card wpcpm-graduation open alone, with every fold inside it', widths: GRADUATION_WIDTHS });
  }

  // B. The Institution Dashboard, as an Administrator viewing the invented school and as its representatives.
  add('institution-dashboard', 'admin-viewing-school', 'all-open', '/institution-dashboard/?wpcpm_institution_view={DESIGN_RECORD}', { act: { open: 'main' }, note: 'Example School of Design: agreement accepted, two students, a report drafted' });
  add('institution-dashboard', 'admin-viewing-school', 'report-draft-open', '/institution-dashboard/?wpcpm_report=2026-H1&wpcpm_institution_view={DESIGN_RECORD}', { act: { open: 'main' }, note: 'the January to June 2026 draft open for editing' });
  add('institution-dashboard', 'representative', 'all-open', '/institution-dashboard/', { login: '{DESIGN_LOGIN}', act: { open: 'main' }, note: "the school's own representative" });
  add('institution-dashboard', 'representative-agreement-in-review', 'all-open', '/institution-dashboard/', { login: '{UNIVERSITY_LOGIN}', act: { open: 'main' }, note: 'Example University, its signed agreement waiting for review' });

  // C. Every Sponsor Dashboard view, as the copy holds it (the first sponsor, names replaced).
  for (const [v, label] of [['', 'overview'], ['sponsorship', 'your-sponsorship'], ['offers', 'offers'], ['reached', 'students-reached'], ['mentors', 'mentors-to-sponsor'], ['company', 'your-company'], ['agreement', 'agreement']]) {
    add('sponsor-dashboard', label, 'all-open', '/sponsor-dashboard/' + (v ? `?wpcpm_view=${v}` : ''), { act: { open: 'main' }, note: "the copy's first sponsor (scrubbed, names replaced)" });
  }

  // D. Both Report Cards, as the Administrator viewing the second entry of the Viewing as list.
  add('mentor-report-card', 'admin-viewing-mentor', 'all-open', '/mentor-dashboard/', { act: { pick: 'select[name="wpcpm_mentor"]', open: 'main' } });
  add('student-report-card', 'admin-viewing-student', 'all-open', '/student-dashboard/', { act: { pick: 'select[name="wpcpm_student_view"]', open: 'main' } });
  if (graduation) {
    add('student-report-card', 'admin-viewing-pending-student', 'all-open', '/student-dashboard/?wpcpm_student_view={PENDING_STUDENT}', { act: { open: 'main' }, note: 'the pending student the state made, Riley Example' });
    add('mentor-report-card', 'admin-viewing-pending-mentor', 'all-open', '/mentor-dashboard/?wpcpm_mentor={PENDING_MENTOR}', { act: { open: 'main' }, note: "the pending student's mentor, Harper Example" });
  }

  // E. Every wp-admin tab of every plugin screen, and the open states the releases change.
  add('wpadmin', 'overview', 'default', ADMIN('wpcpm'));
  for (const t of ['queue', 'pipeline', 'accounts', 'reports', 'agreements', 'sync']) add('wpadmin', `institutions-${t}`, 'default', ADMIN('wpcpm-institutions', t));
  add('wpadmin', 'institutions-accounts', 'manage-members', ADMIN('wpcpm-institutions', 'accounts') + '&wpcpm_institution={DESIGN_RECORD}', { note: 'Manage members of Example School of Design' });
  add('wpadmin', 'institutions-agreements', 'revoke-open', ADMIN('wpcpm-institutions', 'agreements'), { act: { openFold: { text: 'revoke', scope: '#wpbody-content' } }, note: "the accepted agreement's revoke fold open" });
  for (const t of ['queue', 'sponsors', 'accounts', 'offers', 'interests', 'reports', 'agreements', 'listing']) add('wpadmin', `sponsors-${t}`, 'default', ADMIN('wpcpm-sponsors', t));
  add('wpadmin', 'sponsors-agreements', 'record-row-open', ADMIN('wpcpm-sponsors', 'agreements'), { press: { scope: '#wpbody-content', text: '^Record a signed copy$' }, note: "the first row's form open" });
  add('wpadmin', 'sponsors-agreements', 'revoke-row-open', ADMIN('wpcpm-sponsors', 'agreements'), { press: { scope: '#wpbody-content', text: '^Revoke$' }, note: "the in-force row's Revoke open" });
  for (const t of ['queue', 'accounts', 'engagement', 'sync']) add('wpadmin', `mentors-${t}`, 'default', ADMIN('wpcpm-mentors', t));
  add('wpadmin', 'mentors-queue', 'ask-panel-open', ADMIN('wpcpm-mentors', 'queue'), { press: { scope: '#wpbody-content', text: '^Ask for more information$' }, note: 'Ask for more information pressed' });
  for (const t of ['accounts', 'registrations', 'interests', 'sync']) add('wpadmin', `students-${t}`, 'default', ADMIN('wpcpm-students', t));
  for (const t of ['accounts', 'capabilities']) add('wpadmin', `administrators-${t}`, 'default', ADMIN('wpcpm-administrators', t));
  add('wpadmin', 'tools', 'default', ADMIN('wpcpm-tools'));
  add('wpadmin', 'tools-header-notices', 'default', ADMIN('wpcpm-tool-header-notices'));
  add('wpadmin', 'tools-emails-templates', 'list', ADMIN('wpcpm-tool-emails', 'templates'));
  add('wpadmin', 'tools-emails-template-editor', 'invite-institution', ADMIN('wpcpm-tool-emails', 'templates') + '&edit=invite-institution');
  add('wpadmin', 'tools-emails-footer', 'default', ADMIN('wpcpm-tool-emails', 'footer'));
  add('wpadmin', 'tools-emails-log', 'default', ADMIN('wpcpm-tool-emails', 'log'));
  add('wpadmin', 'tools-need-help', 'default', ADMIN('wpcpm-tool-handbook'));
  add('wpadmin', 'tools-mentor-status-checker', 'default', ADMIN('wpcpm-tool-mentor-status-checker'));
  add('wpadmin', 'tools-duplicate-finder', 'default', ADMIN('wpcpm-tool-duplicate-finder'));
  add('wpadmin', 'tools-track-builder', 'default', ADMIN('wpcpm-tool-track-builder'));
  for (const t of ['connection', 'people', 'institutions', 'sponsors', 'security', 'mail', 'advanced']) add('wpadmin', `settings-${t}`, 'details-open', ADMIN('wpcpm-settings', t), { act: { openFirst: '#wpbody-content' }, note: 'the first Details fold open' });

  // F. The Administrator guide's first screen (the page is far taller than any picture).
  add('guide', 'top', 'first-screen', '/administrator-guide/', { viewportOnly: true });
  // A job of the guide with its notes, as the audit took it: the section from its heading to the next one.
  add('guide', 'job-a-school-applied', 'with-notes', '/administrator-guide/', { act: { reveal: '#a-school-applied' }, section: '#a-school-applied', note: 'the job "A school applied", from its heading to the next heading of its level' });

  // G. The two pages the switch of the shared parts turned on, as an Administrator, inside the dashboard's frame:
  // the Administrator guide and the newest Program update (its address is the copy's: {UPDATES_PATH}).
  // The guide is some 40,000px tall: its first 2,400px, where the frame, the contents and the first jobs are.
  add('admin-pages', 'guide', 'as-loaded', '/administrator-guide/', { viewportOnly: true, firstPx: 2400, note: 'the Administrator guide in the frame, as it loads (its first 2400px)' });
  // Their "On this page" list is a button the script shows where the list is folded (a narrow main area), not a
  // details fold: it is pressed where it is shown; on a wide window the list is drawn open and nothing is pressed.
  add('admin-pages', 'guide', 'contents-open', '/administrator-guide/', { press: { scope: '.wpcpm-guide__contents', text: '^On this page', ifShown: true }, viewportOnly: true, firstPx: 2400, note: 'On this page pressed open where it is folded; drawn open on a wide window (its first 2400px)' });
  add('admin-pages', 'program-updates', 'as-loaded', '{UPDATES_PATH}', { note: 'the newest Program update in the frame, as it loads' });
  // The updates page's list is headed by its own name, Program updates.
  add('admin-pages', 'program-updates', 'fold-open', '{UPDATES_PATH}', { press: { scope: '.wpcpm-guide__contents', text: '^Program updates', ifShown: true }, note: 'the Program updates list pressed open where it is folded; drawn open on a wide window' });

  for (const s of list) {
    if (s.area !== 'wpadmin') continue;
    const q = new URLSearchParams(s.path.split('?')[1] || '');
    if (RELEASE_2_TABS.includes(`${q.get('page')} ${q.get('tab')}`)) s.tablet = true;
  }
  return list;
}

/** The box of a section: from its heading's top to the next heading of the same level or higher (page coordinates). */
export async function sectionBox(page, selector) {
  return page.evaluate(sel => {
    const h = document.querySelector(sel);
    if (!h) return null;
    const level = Number((h.tagName.match(/^H(\d)$/) || [0, 6])[1]);
    const all = [...document.querySelectorAll('h1,h2,h3,h4,h5,h6')];
    const next = all.slice(all.indexOf(h) + 1).find(e => Number(e.tagName[1]) <= level && e.getBoundingClientRect().height > 0);
    const top = Math.max(0, h.getBoundingClientRect().top + scrollY - 16);
    const bottom = next ? next.getBoundingClientRect().top + scrollY - 8 : document.documentElement.scrollHeight;
    return { y: Math.floor(top), height: Math.ceil(bottom - top) };
  }, selector);
}

/**
 * In the page, handed the emulated window's width (never innerWidth: under phone emulation Chrome widens the
 * layout viewport to a page that is too wide, so innerWidth grows with the fault): the page's scrollWidth, every
 * card wider than its container's content box, and every element of a card or of the main area that ends past
 * its card's edge or past the window, the outermost of each run only (its parent ends inside), and nothing
 * inside a box that clips or scrolls sideways, a fixed box or a screen reader's text.
 */
export function overflowFacts(width) {
  const W = Number(width) || innerWidth;
  const desc = e => (e.tagName.toLowerCase() + (e.id ? '#' + e.id : '') + (typeof e.className === 'string' && e.className.trim() ? '.' + e.className.trim().split(/\s+/).slice(0, 2).join('.') : '') + ' "' + (e.innerText || '').replace(/\s+/g, ' ').trim().slice(0, 40) + '"');
  const shown = e => { const r = e.getBoundingClientRect(); const c = getComputedStyle(e); return r.width > 0 && r.height > 0 && c.visibility !== 'hidden' && c.position !== 'fixed' && !e.closest('.screen-reader-text, [hidden], details:not([open]) > :not(summary)'); };
  const clips = e => getComputedStyle(e).overflowX !== 'visible';
  const clippedBetween = (e, top) => { for (let p = e.parentElement; p && p !== top; p = p.parentElement) if (clips(p)) return true; return false; };
  const contentRight = e => { const r = e.getBoundingClientRect(); const c = getComputedStyle(e); return r.right - parseFloat(c.paddingRight) - parseFloat(c.borderRightWidth); };
  const out = { scrollWidth: document.documentElement.scrollWidth, width: W, lines: [] };
  const cards = [...document.querySelectorAll('.wpcpm-ui-fold-card, .wpcpm-group, .wpcpm-sponsor__card, .wpcpm-card')].filter(c => shown(c) && !c.parentElement.closest('.wpcpm-ui-fold-card, .wpcpm-group, .wpcpm-sponsor__card, .wpcpm-card'));
  for (const card of cards) {
    const cr = card.getBoundingClientRect();
    const limit = contentRight(card.parentElement);
    if (cr.right > limit + 1) out.lines.push({ kind: 'OVERFLOW', what: 'card', el: desc(card), right: Math.round(cr.right), limit: Math.round(limit) });
    let n = 0;
    for (const e of card.querySelectorAll('*')) {
      if (n >= 4) break;
      if (!shown(e) || clippedBetween(e, card)) continue;
      const r = e.getBoundingClientRect();
      if (r.right <= cr.right + 1) continue;
      const p = e.parentElement;
      if (p !== card && p.getBoundingClientRect().right > cr.right + 1 && !clips(p)) continue; // the outermost of a run only
      out.lines.push({ kind: 'OVERFLOW', what: 'past its card', el: desc(e), right: Math.round(r.right), limit: Math.round(cr.right), card: desc(card).slice(0, 60) });
      n++;
    }
  }
  // Gate 6: every cell of a stacked table (a row not drawn as a table row: its header, its pairs, its action
  // line) whose content is wider than its box, or whose text's right edge passes the left edge of the next pair
  // on its line. A cell inside a closed fold or a hidden cell is not read.
  const textBox = el => {
    const w = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
    let l = Infinity, r = -Infinity, node;
    while ((node = w.nextNode())) {
      if (!node.textContent.trim() || node.parentElement.closest('.screen-reader-text')) continue;
      const range = document.createRange(); range.selectNodeContents(node);
      for (const q of range.getClientRects()) if (q.width >= 0.5) { l = Math.min(l, q.left); r = Math.max(r, q.right); }
    }
    return r > l ? { left: l, right: r } : null;
  };
  let cellLines = 0;
  for (const tr of document.querySelectorAll('table.wpcpm-ui-table tr')) {
    if (cellLines >= 40) break;
    if (!shown(tr) || getComputedStyle(tr).display === 'table-row' || tr.parentElement.tagName === 'THEAD') continue;
    const cells = [...tr.children].filter(c => shown(c) && getComputedStyle(c).display !== 'none');
    cells.forEach((c, i) => {
      if (c.scrollWidth > c.clientWidth + 1) { out.lines.push({ kind: 'CELL', what: 'content wider than its box', el: desc(c), right: c.scrollWidth, limit: c.clientWidth, card: desc(tr).slice(0, 60) }); cellLines++; return; }
      const t = textBox(c);
      if (!t) return;
      const r = c.getBoundingClientRect();
      for (const o of cells.slice(i + 1)) {
        const q = o.getBoundingClientRect();
        if (Math.min(r.bottom, q.bottom) - Math.max(r.top, q.top) <= 1 || q.left < r.left) continue; // not on its line, or not after it
        if (t.right > q.left + 0.5 && t.left < q.left) { out.lines.push({ kind: 'CELL', what: 'text into the next pair', el: desc(c), right: Math.round(t.right), limit: Math.round(q.left), card: desc(tr).slice(0, 60) }); cellLines++; }
        break;
      }
    });
  }
  const main = document.querySelector('main') || document.body;
  let n = 0;
  for (const e of main.querySelectorAll('*')) {
    if (n >= 6) break;
    if (!shown(e) || clippedBetween(e, main)) continue;
    const r = e.getBoundingClientRect();
    if (r.right <= W + 1) continue;
    const p = e.parentElement;
    if (p && p !== main && p.getBoundingClientRect().right > W + 1 && !clips(p)) continue;
    if (out.lines.some(l => l.el === desc(e))) continue;
    out.lines.push({ kind: 'OVERFLOW', what: 'past the window', el: desc(e), right: Math.round(r.right), limit: W });
    n++;
  }
  return out;
}

/** The lines the overflow check writes for one picture: `H-SCROLL` when the page is wider than the window, one `OVERFLOW` per element; none when nothing is wider. */
export function overflowLines(file, facts) {
  const lines = [];
  if (facts.scrollWidth > facts.width) lines.push(`H-SCROLL ${file}: the page is ${facts.scrollWidth}px wide in a ${facts.width}px window`);
  for (const l of facts.lines || []) {
    if (l.kind === 'CELL') lines.push(`CELL ${file}: ${l.what}: ${l.el} ${l.what === 'text into the next pair' ? 'ends at' : 'is'} ${l.right}px, ${l.what === 'text into the next pair' ? 'the next pair begins at' : 'its box'} ${l.limit}px (row ${l.card})`);
    else lines.push(`OVERFLOW ${file}: ${l.what}: ${l.el} ends at ${l.right}px, its limit ${l.limit}px${l.card ? ' (card ' + l.card + ')' : ''}`);
  }
  return lines;
}

/** A scene's file name at a size, without the extension. */
export function fileName(scene, size) {
  return `${scene.area}-${scene.screen}-${scene.state}-${size.width}`.toLowerCase().replace(/[^a-z0-9-]+/g, '-');
}

/**
 * Whether a scene is taken at a size: a scene with onlyWidths at those alone; 768 for release 2's wp-admin tabs
 * when --sizes lists it, and for a scene whose own widths name it; any other width when the run's --sizes lists it (`base`, 1280 and 390 by default) or the scene's own
 * widths do.
 */
export function takenAt(scene, size, base = [1280, 390]) {
  if (scene.onlyWidths) return scene.onlyWidths.includes(size.width);
  if (size.width === 768) return (base.includes(768) && !!scene.tablet) || (scene.widths || []).includes(768);
  return base.includes(size.width) || (scene.widths || []).includes(size.width);
}

/** What a context needs before the scene's page loads: the frame's menu folded, as its own stored state. */
export async function prepare(context, scene) {
  if (scene.folded) await context.addInitScript(() => { try { localStorage.setItem('wpcpm-frame-folded', '1'); } catch {} });
}

/** The scene with its tokens filled; null when a token has no value on this copy. */
export function fill(scene, values) {
  let missing = false;
  const sub = s => (typeof s === 'string' ? s.replace(/\{([A-Z_]+)\}/g, (m, k) => { if (!values[k]) missing = true; return k.endsWith('_PATH') ? String(values[k] || '') : encodeURIComponent(values[k] || ''); }) : s); // a _PATH token is a whole path
  const out = { ...scene, path: sub(scene.path), login: sub(scene.login) };
  return missing ? null : out;
}

/**
 * Why a loaded page is an error page and never a picture of the screen, or '' when it is not: an answer of 400
 * or more (the proxy's 502 after a database restore among them), the proxy's own error page, WordPress's
 * wp_die() page (body#error-page) or its critical error, or no database. `page` is what the browser read:
 * { status, title, bodyId, text }.
 */
export function errorPage({ status = 0, title = '', bodyId = '', text = '' } = {}) {
  if (status >= 400) return `the page answered ${status}`;
  const head = `${title}\n${String(text).slice(0, 600)}`;
  if (/\b(?:502 Bad Gateway|503 Service (?:Temporarily )?Unavailable|504 Gateway Time-?out)\b/i.test(head)) return 'the proxy answered with an error page';
  if (bodyId === 'error-page') return "WordPress's error page (wp_die)";
  if (/There has been a critical error on this website|Error establishing a database connection/i.test(head)) return "WordPress's error page";
  return '';
}

// ------------------------------------------------------------------ the steps, handed a page

export async function settle(page) {
  try { await page.waitForLoadState('networkidle', { timeout: 15000 }); } catch {}
  // A lazy image loads now, not when a full-page capture scrolls past it; every image is waited for.
  await page.evaluate(() => {
    document.querySelectorAll('img[loading="lazy"]').forEach(i => { i.loading = 'eager'; });
    return Promise.all([...document.images].filter(i => !i.complete).map(i => new Promise(done => { i.addEventListener('load', done, { once: true }); i.addEventListener('error', done, { once: true }); setTimeout(done, 5000); })));
  }).catch(() => {});
  await page.evaluate(() => (document.fonts ? document.fonts.ready : null)).catch(() => {});
  await page.waitForTimeout(300);
}

/**
 * What a context does before any page script runs, so two runs draw the same pixels: the page's clock reads
 * the rig's frozen moment (`now`, in seconds; timers still run), and nothing animates, moves or blinks.
 */
export const STILL_CSS = '*,*::before,*::after{animation-duration:0s!important;animation-delay:0s!important;animation-iteration-count:1!important;transition:none!important;caret-color:transparent!important;scroll-behavior:auto!important}';
export async function still(context, now) {
  if (now > 0) await context.clock.setFixedTime(now * 1000);
  await context.addInitScript(css => {
    const add = () => { const s = document.createElement('style'); s.dataset.uiRig = 'still'; s.textContent = css; (document.head || document.documentElement).appendChild(s); };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', add); else add();
  }, STILL_CSS);
}

/** Press every closed fold's head inside the scope, outermost first, until none is closed. */
export async function openAll(page, scope = 'body') {
  for (let round = 0; round < 6; round++) {
    const heads = await page.$$(`${scope} details:not([open]) > summary`);
    let pressed = 0;
    for (const h of heads) {
      const ok = await h.evaluate(s => { const r = s.getBoundingClientRect(); return r.width > 0 && r.height > 0 && !s.parentElement.open && !s.parentElement.closest('.wpcpm-frame__account'); });
      if (ok) { await h.click({ timeout: 3000 }).catch(() => {}); pressed++; await page.waitForTimeout(80); }
    }
    if (!pressed) return;
    await settle(page);
  }
}

async function press(page, { scope, text, nth = 0, item = '', ifShown = false }) {
  const re = new RegExp(text);
  // An item of one kind: the request to correct a school's details is the Decide row that approves "and
  // updates the records".
  if (item === 'details') {
    const id = await page.evaluate(sc => { const e = [...document.querySelectorAll(`${sc} [id^="wpcpm-decide-req-"]`)].find(n => /Approve and update the records/.test(n.textContent)); return e ? e.id : ''; }, scope);
    if (!id) throw new Error(`no request to correct a school's details in ${scope}`);
    scope = '#' + id;
  }
  // An opener's words may go on with the item's title for a screen reader ("Return with a note <title>"), so
  // the pattern is read against the openers alone: a fold's head or a button that says whether it is open.
  const target = page.locator(`${scope} summary, ${scope} button[aria-expanded]`, { hasText: re }).nth(nth);
  if (!(await target.count())) {
    const other = page.locator(`${scope} button, ${scope} input[type=submit]`, { hasText: re });
    if (await other.count()) throw new Error(`/${text}/ in ${scope} is not an opener (pressing it would post)`);
    throw new Error(`nothing reading /${text}/ to press in ${scope}`);
  }
  // An opener the page shows only where its list is folded (ifShown) is not pressed where it is hidden.
  if (ifShown && !(await target.isVisible())) return 'nothing pressed: the opener is not shown at this width';
  // A fold the card's act opened already stays open: a press on its head would close it.
  if (await target.evaluate(e => e.tagName === 'SUMMARY' && e.parentElement.open)) return;
  await target.scrollIntoViewIfNeeded();
  await target.click();
  await page.waitForTimeout(250);
  const opened = await target.evaluate(e => (e.tagName === 'SUMMARY' ? e.parentElement.open : e.getAttribute('aria-expanded') === 'true'));
  if (!opened) throw new Error(`pressing /${text}/ opened nothing`);
}

/**
 * The pictures of the copy's own that the page drew broken (loaded, no pixels), each by its address: the sponsor
 * badge answers 429 past its ceiling of 120 an hour per caller, which a session of the rig reaches. Each is asked
 * for once more after a pause; what is still broken is answered, and the picture is not saved.
 */
export async function brokenImages(page, base, pause = 3000) {
  const broken = () => page.evaluate(b => [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.currentSrc.startsWith(b + '/') && i.getBoundingClientRect().width > 0).map(i => i.currentSrc), base);
  if (!(await broken()).length) return [];
  await page.waitForTimeout(pause);
  await page.evaluate(b => Promise.all([...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.currentSrc.startsWith(b + '/')).map(i => new Promise(done => { i.addEventListener('load', done, { once: true }); i.addEventListener('error', done, { once: true }); setTimeout(done, 5000); const s = i.src; i.src = ''; i.src = s; }))), base);
  return broken();
}

/**
 * Put the page in the scene's state: load it, do its act and its press, then nothing hovered or open by
 * chance (Escape pressed, the account menu folded, the pointer parked at 0,0, nothing focused) and the view at
 * the top. Answers a short note of how it was reached. Throws when the state cannot be reached.
 */
export async function stage(page, scene, base) {
  const how = [];
  const answer = await page.goto(base + scene.path, { waitUntil: 'domcontentloaded', timeout: 60000 });
  await settle(page);
  // An error page (a 502 after a restore, a wp_die) is never a picture of the screen, as a sign-in page is not.
  const read = async () => ({ status: answer ? answer.status() : 0, ...(await page.evaluate(() => ({ title: document.title, bodyId: document.body ? document.body.id : '', text: document.body ? document.body.innerText : '' }))) });
  const failed = errorPage(await read());
  if (failed) throw new Error(`refusing the picture: ${failed}`);
  const act = scene.act || {};
  if (act.pick) {
    const value = await page.evaluate(sel => { const s = document.querySelector(sel); const o = s && [...s.options].filter(o => o.value)[1]; return o ? o.value : ''; }, act.pick);
    if (!value) throw new Error(`no second entry in ${act.pick}`);
    const name = await page.evaluate(sel => document.querySelector(sel).name, act.pick);
    await page.goto(`${base}${scene.path}?${encodeURIComponent(name)}=${encodeURIComponent(value)}`, { waitUntil: 'domcontentloaded' });
    await settle(page);
    how.push('picked the second entry of the Viewing as list');
  }
  if (act.openCard) {
    if (!(await page.locator(act.openCard).count())) throw new Error(`${act.openCard} is not on the page (the card is not drawn on this copy)`);
    await openAll(page, act.openCard);
  }
  if (act.openHead) {
    if (!(await page.locator(act.openHead).count())) throw new Error(`${act.openHead} is not on the page (the card is not drawn on this copy)`);
    const head = page.locator(`${act.openHead} details > summary`).first();
    if (await head.count() && !(await head.evaluate(s => s.parentElement.open))) { await head.click(); await settle(page); }
  }
  if (act.reveal) {
    const found = await page.evaluate(sel => { const e = document.querySelector(sel); if (!e) return false; for (let d = e.closest('details'); d; d = d.parentElement && d.parentElement.closest('details')) d.open = true; return true; }, act.reveal);
    if (!found) throw new Error(`${act.reveal} is not on the page`);
    await settle(page);
  }
  if (act.open) await openAll(page, act.open);
  if (act.openFirst) {
    const s = page.locator(`${act.openFirst} details:not([open]) > summary`).first();
    if (await s.count()) { await s.scrollIntoViewIfNeeded(); await s.click(); await settle(page); }
  }
  if (act.openFold) {
    const s = page.locator(`${act.openFold.scope} summary`, { hasText: new RegExp(act.openFold.text, 'i') }).first();
    if (!(await s.count())) throw new Error(`no fold reading "${act.openFold.text}" in ${act.openFold.scope}`);
    if (!(await s.evaluate(e => e.parentElement.open))) { await s.click(); await settle(page); }
  }
  if (scene.press) { const said = await press(page, scene.press); if (said) how.push(said); }
  // A page that sent the browser to sign in is never a picture of the screen (a sign-in lost, for example to
  // another WP-CLI process writing the same account's sessions at the same moment).
  if (await page.evaluate(() => /wp-login\.php/.test(location.pathname) || document.body.classList.contains('login'))) throw new Error('the page asked to sign in (the sign-in cookie was not accepted)');
  const after = errorPage({ ...(await read()), status: 0 });
  if (after) throw new Error(`refusing the picture: ${after}`);
  // Escape closes a Decide panel too (its keyboard rule), so a scene that pressed one open keeps it.
  if (!scene.press) await page.keyboard.press('Escape');
  await page.evaluate(() => document.querySelectorAll('details[open]').forEach(d => { if (d.closest('.wpcpm-frame__account, .wpcpm-frame__top, .global-header, #wpadminbar') || /account/.test(d.className)) d.open = false; }));
  await page.mouse.move(0, 0);
  await page.evaluate(() => { if (document.activeElement && document.activeElement !== document.body) document.activeElement.blur(); });
  await page.waitForTimeout(200);
  await page.evaluate(() => window.scrollTo(0, 0));
  return how.join('; ');
}
