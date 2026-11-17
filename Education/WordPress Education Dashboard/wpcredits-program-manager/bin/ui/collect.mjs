// What measure.mjs and snap.mjs's --no-pictures read in a loaded page (collect(), handed the emulated window's width and
// run in the page through page.evaluate) and the per-screen summary of it (summarize()): the counts of the design's
// section 8 item 4, every gap against the rhythm (RHYTHM). Nothing here starts a browser or talks to a site.

// ------------------------------------------------------------------ what is read in the page
export function collect(W) {
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
    // `parts`: the parts' own button (WPCPM_UI::button(), class-wpcpm-ui.php:166), which Gate B holds to one height.
    out.buttons.push({ el: desc(b), parts: b.matches('.wpcpm-btn'), h: box(b).h, font: `${px(c.fontSize)}/${c.fontWeight}`, radius: px(c.borderTopLeftRadius), bg: c.backgroundColor, fg: c.color, border: c.borderTopColor, pad: `${px(c.paddingTop)} ${px(c.paddingRight)}`, wraps: box(b).h > 44 });
  }
  // Fields: every box, the parts' .wpcpm-ui-field__control among them.
  for (const f of all('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]):not([type="file"]), select, textarea, .wpcpm-ui-field__control')) {
    if (f.matches('.wpcpm-ui-field__control') && !f.matches('input, select, textarea')) continue;
    const c = getComputedStyle(f);
    // `parts`: the parts' own box (field() and the combo's select, class-wpcpm-ui.php:508), which Gate B holds to one height.
    out.fields.push({ el: desc(f), parts: f.matches('.wpcpm-ui-field__control'), tag: f.tagName.toLowerCase(), h: box(f).h, font: `${px(c.fontSize)} ${c.fontFamily.split(',')[0].replace(/"/g, '')}`, radius: px(c.borderTopLeftRadius), border: c.borderTopColor, w: Math.round(f.getBoundingClientRect().width) });
  }
  // Fold heads: a card's head or an inner fold's head, open or closed; the parts' .wpcpm-ui-fold__head too.
  for (const s of all('summary, .wpcpm-p-head, .wpcpm-overview__row-in, .wpcpm-ui-fold__head')) {
    if (s.matches('summary') && s.closest('.wpcpm-ui-fold__head') && s !== s.closest('.wpcpm-ui-fold__head')) continue;
    const det = s.closest('details');
    const card = s.matches('.wpcpm-group__summary, .wpcpm-p-head, .wpcpm-overview__row-in') || !!s.closest('.wpcpm-ui-fold-card > .wpcpm-ui-fold > .wpcpm-ui-fold__head, .wpcpm-ui-fold-card > details > summary');
    const head = { el: desc(s), kind: card ? 'card' : 'inner', open: det ? det.open : null, h: box(s).h };
    // A card head under 800px of main area is in its stacked form (the parts' narrow form, a container query on the
    // frame's main area, wpcpm-main): Gate B's measure holds it to 70px there, 56 above (gateMeasures()).
    const main = s.closest('.wpcpm-frame__main');
    const narrow = main ? (() => { const mc = getComputedStyle(main); return main.clientWidth - parseFloat(mc.paddingLeft) - parseFloat(mc.paddingRight) < 800; })() : W <= 600;
    if (card && main) head.narrow = narrow;
    // Gate B round 2, F8: a card head of one line (no phrase, no tag) is 56px in the narrow form as well; `words` says
    // whether it holds a phrase or a tag, which give the stacked form its second line.
    if (card) { const ph = s.querySelector('.wpcpm-ui-fold__phrase'); head.words = !!(ph && vis(ph) && ph.textContent.trim()) || !!s.querySelector('.wpcpm-status, .wpcpm-ui-fold__end > .wpcpm-status'); }
    // A head that holds a photo (a student's row, design 6.1; Gate B, B3 ruled): the room above and below its content,
    // read by the boxes of the portrait, the text column (the lead), the end group and the chevron (round 2, F8: leaf
    // elements missed a text column's own wrapped text), which the measure holds equal and off the edge.
    const photo = s.querySelector('.wpcpm-ui-fold__portrait');
    if (photo && vis(photo)) {
      const sb = s.getBoundingClientRect();
      // The end group's children too (Task 28 fix round 2, X13): in the stacked form the group draws no box of its own
      // (display: contents) and its tag is the grid's item under the name and dates.
      const rs = [...s.querySelectorAll(':scope > .wpcpm-ui-fold__portrait, :scope > .wpcpm-ui-fold__lead, :scope > .wpcpm-ui-fold__end, :scope > .wpcpm-ui-fold__end > *, :scope > .wpcpm-ui-fold__chev')].filter(vis).map(e => e.getBoundingClientRect()).filter(r => r.height > 0);
      head.photo = { above: Math.round(Math.min(...rs.map(r => r.top)) - sb.top), below: Math.round(sb.bottom - Math.max(...rs.map(r => r.bottom))) };
    }
    // An inner head of words (round 2, F1 and F8): its text column's height and the head's padding, whether it is in the
    // stacked form, and where its second piece is: under the title, and whether the line had room for both.
    const lead = s.querySelector(':scope > .wpcpm-ui-fold__lead');
    if (!card && !(photo && vis(photo)) && lead && vis(lead)) {
      const sc = getComputedStyle(s);
      head.lead = Math.round(lead.getBoundingClientRect().height);
      head.pad = Math.round(parseFloat(sc.paddingTop) + parseFloat(sc.paddingBottom));
      head.stacked = narrow;
      // The text lines the lead draws (round 3, R1): its lead is those lines, 20px each.
      const lr = document.createRange();
      lr.selectNodeContents(lead);
      const rows = [];
      [...lr.getClientRects()].filter(r => r.width > 0 && r.height > 0).sort((a, b) => a.top - b.top).forEach(r => {
        const mid = (r.top + r.bottom) / 2;
        if (!rows.some(x => mid >= x.top && mid <= x.bottom)) rows.push({ top: r.top, bottom: r.bottom });
      });
      head.lines = rows.length;
      const title = lead.querySelector('.wpcpm-ui-fold__title');
      const second = lead.querySelector('.wpcpm-ui-fold__second');
      if (title && second && vis(second)) {
        const text = e => { const r = document.createRange(); r.selectNodeContents(e); return [...r.getClientRects()]; };
        const tr = text(title), sr = text(second);
        const titleBottom = Math.max(...tr.map(r => r.bottom)), secondTop = Math.min(...sr.map(r => r.top));
        const width = rects => rects.reduce((a, r) => a + r.width, 0);
        head.second = { under: secondTop >= titleBottom - 1, fits: tr.length === 1 && width(tr) + 12 + width(sr) <= lead.getBoundingClientRect().width + 0.5 };
      }
    }
    out.heads.push(head);
  }
  for (const t of all('.wpcpm-status, .wpcpm-ui-chip, .wpcpm-p-tag, .wpcpm-inst-mark, .wpcpm-people__mark, .wpcpm-sreg-outcome strong, .wpcpm-card__age')) {
    const c = getComputedStyle(t);
    out.tags.push({ el: desc(t), look: `${px(c.fontSize)}/${c.fontWeight} ${c.textTransform} r${px(c.borderTopLeftRadius)} h${box(t).h}` });
  }
  // In-card headings, the parts' .wpcpm-ui-h among them, with the distance actually drawn above and below.
  // An item's title (design 2.10, its facts 4px under it) and a sponsor offer's title in Tools from our sponsors
  // (today's offer grid) are not in-card headings, and are not counted as headings.
  for (const h of all('h3, h4, .wpcpm-p-h, .wpcpm-ui-h')) {
    if (h.closest('summary, .wpcpm-p-head, .wpcpm-overview__row-in, .wpcpm-ui-fold__head, table, .wpcpm-mentor-tile, .wpcpm-p-list__line') || h.matches('.wpcpm-group__title, .wpcpm-queue-title, .wpcpm-administrator__item-title, .wpcpm-student__heading, .wpcpm-head__eyebrow, .wpcpm-ui-item__title, .wpcpm-tools__title') || h.matches('.wpcpm-report__sub')) continue; // the semester report's document keeps its own markup and report-print.css (the frame's design 6.3)
    const c = getComputedStyle(h);
    const hb = box(h); const p = sib(h, -1), n = sib(h, 1);
    const decide = /^decide$/i.test((h.innerText || '').trim()) || !!h.closest('.wpcpm-ui-decide');
    // A guide page's own headings are prose, an article's (the Institution and Administrator guides in the frame): not
    // a dashboard block's, so they take no gap of the rhythm (Gate B's fix round).
    const prose = !!h.closest('.wpcpm-guide__body');
    // On a phone "How to do this" is the open card's first line, 16px over the next block (design 2.3).
    const afterHelp = !!(p && p.matches('.wpcpm-ui-fold__help--phone'));
    out.headings.push({ el: desc(h), look: `${px(c.fontSize)}/${c.fontWeight} ${c.textTransform}`, above: p ? Math.round(hb.top - box(p).bottom) : null, below: n ? Math.round(box(n).top - hb.bottom) : null, decide, afterHelp, prose });
  }
  // Gaps: label to its box, box to its hint, field to field, last field to the action row.
  // The file box's own input is hidden under its button and the chosen name (WPCPM_UI::file()): the box is measured, not it.
  const controls = all('input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]), select, textarea').filter(c => !c.matches('.wpcpm-ui-file__input--hidden'));
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
      // The compact Viewing as is the parts' one label beside its box (design 7, the parts' 2.4): not a gap of the rhythm.
      else if (!c.matches('.wpcpm-ui-viewing__box')) out.gaps.push({ kind: 'label-beside-field', px: Math.round(cb.left - lb.right), el: desc(c) });
    }
    if (hint) out.gaps.push({ kind: 'field-hint', px: Math.round(box(hint).top - cb.bottom), el: desc(c) });
    const form = c.form || c.closest('form');
    if (!form) continue;
    if (!byForm.has(form)) byForm.set(form, []);
    byForm.get(form).push(f);
  }
  const nextVisible = e => { let n = e.nextElementSibling; while (n && (!vis(n) || n.matches('input[type="hidden"]'))) n = n.nextElementSibling; return n; };
  for (const [form, list] of byForm) {
    if (form.matches('.wpcpm-emails__filters, .wpcpm-roster__bar')) continue; // a filter bar is a grid, not a column (the roster's: class-wpcpm-institution-roster-view.php:458)
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
    if (h.prose) continue;
    if (h.above !== null) out.gaps.push({ kind: h.afterHelp ? 'help-heading' : 'heading-above', px: h.above, el: h.el });
    if (h.below !== null) out.gaps.push({ kind: h.decide ? 'heading-below-decide' : 'heading-below', px: h.below, el: h.el });
  }
  // Folds 10 apart: a fold (or a card) to the next one beside it.
  for (const f of all('.wpcpm-ui-fold-card, .wpcpm-ui-fold:not(.wpcpm-ui-fold-card .wpcpm-ui-fold--row), details.wpcpm-group__disclosure')) {
    let n = sib(f, 1);
    // A run as a list (Task 28, W6): a fold alone in its item takes the next item's fold as its neighbor.
    const item = !n && f.matches('.wpcpm-ui-fold--inner') && f.parentElement && f.parentElement.matches('.wpcpm-ui-folds__item') ? sib(f.parentElement, 1) : null;
    if (item && item.matches('.wpcpm-ui-folds__item')) n = item.querySelector(':scope > .wpcpm-ui-fold--inner');
    // A run of inner folds shares its hairlines, 0 apart (design 2.3): a kind of its own.
    const run = f.matches('.wpcpm-ui-fold--inner') && n && n.matches('.wpcpm-ui-fold--inner');
    if (n && n.matches('.wpcpm-ui-fold-card, .wpcpm-ui-fold, details.wpcpm-group__disclosure')) out.gaps.push({ kind: run ? 'inner-fold-run' : 'fold-fold', px: Math.round(box(n).top - box(f).bottom), el: desc(f) });
  }
  // Row actions: one border per row action; a quiet one measures border 0, a transparent fill, no side padding.
  for (const b of all('.wpcpm-ui-list__act button, .wpcpm-ui-list__act a, .wpcpm-p-list__act button, .wpcpm-p-list__act a')) {
    const c = getComputedStyle(b);
    const border = px(c.borderTopWidth) + px(c.borderRightWidth) + px(c.borderBottomWidth) + px(c.borderLeftWidth);
    // The Overview's rows, what waits and what is good to know, carry one secondary button each (design 5, the Sponsor
    // Dashboard's rows): `overview`, `secondary` and `alone` let the measure allow that one (gateMeasures()).
    const act = b.closest('.wpcpm-ui-list__act, .wpcpm-p-list__act');
    out.rowActions.push({ el: desc(b), border, bg: c.backgroundColor, h: box(b).h, padX: px(c.paddingLeft) + px(c.paddingRight), quiet: b.matches('.wpcpm-btn--quiet'), overview: !!b.closest('.wpcpm-institution__waiting, .wpcpm-institution__news'), secondary: b.matches('.wpcpm-btn--secondary'), alone: !!act && act.querySelectorAll('button, a').length === 1 });
  }
  out.filters = all('.subsubsub, .wpcpm-p-list__filters, .wpcpm-ui-filter').map(f => (f.matches('.subsubsub') ? 'wordpress-subsubsub' : 'chips'));
  out.searches = all('.wpcpm-p-list__search, .wpcpm-ui-search, form.wpcpm-mapp-find, .wpcpm-inst-find .wpcpm-admin-combo, .wpcpm-emails__search').map(s => { const i = s.querySelector('input:not([type="hidden"])'); const l = s.querySelector('label'); if (!i || !l) return 'no-label'; const ib = box(i), lb = box(l); return `${lb.bottom <= ib.top + 1 ? 'label-above' : 'label-beside'} ${Math.round(ib.right - ib.left)}px`; });
  out.itemHeads = all('.wpcpm-administrator__item-title, .wpcpm-queue-title, .wpcpm-ui-item__title').map(h => { const c = getComputedStyle(h); return `${px(c.fontSize)}/${c.fontWeight}`; });
  out.kvLabelColors = all('.wpcpm-app-answers tbody th, .wpcpm-mentee__table tbody th, .wpcpm-ui-table--keyvalue tbody th').map(e => getComputedStyle(e).color);
  out.overflow = { scrollWidth: document.documentElement.scrollWidth, viewport: W, cut: [...document.querySelectorAll('main *, #wpbody-content *')].filter(e => { const r = e.getBoundingClientRect(); return r.width > 0 && r.right > W + 1 && !e.closest('.screen-reader-text, [hidden]') && getComputedStyle(e).position !== 'fixed'; }).slice(0, 12).map(desc) };
  return out;
}

// ------------------------------------------------------------------ the summary
export const RHYTHM = { 'label-field': [6], 'field-hint': [6], 'field-field': [14], 'field-actions': [16], 'decide-panel': [16], 'heading-above': [24], 'heading-below': [8], 'heading-below-decide': [12], 'fold-fold': [10], 'label-beside-field': [], 'help-heading': [16], 'inner-fold-run': [0] };
const uniq = a => [...new Set(a)];
const nums = a => uniq(a).sort((x, y) => x - y);
export function summarize(r) {
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

// Gate B's measures, per Institution Dashboard screen and for the whole set at each width (gateMeasures(), scenes.mjs).
export const gateLine = (k, g) => `GATE B ${k.padEnd(72)} btn ${g.buttonHeights.join('/') || '-'} ${g.oneButtonHeight ? 'ok' : 'OFF'}  card ${g.cardHeads.join('/') || '-'} ${g.cardHeads56 ? 'ok' : 'OFF'}  inner ${g.innerHeads.join('/') || '-'} ${g.innerHeads44 ? 'ok' : 'OFF'}  field ${g.fieldHeights.join('/') || '-'} ${g.oneFieldHeight ? 'ok' : 'OFF'}  rowbd ${g.rowActionBorders} ${g.rowActionsBorder0 ? 'ok' : 'OFF'}`;
