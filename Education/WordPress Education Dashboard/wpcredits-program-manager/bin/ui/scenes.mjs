// Every screen and open state the UI rig photographs (snap.mjs) and measures (measure.mjs), and the steps that
// put a page in that state. Nothing here starts a browser or talks to a site: the steps are handed a page.
//
// A scene is { area, screen, state, path, login?, act?, press?, card?, panelOf?, note?, viewportOnly?, tablet? }.
// Its file is <area>-<screen>-<state>-<width>.png. A path or login may hold a token, {DESIGN_RECORD},
// {DESIGN_LOGIN} or {UNIVERSITY_LOGIN}, which snap.mjs and measure.mjs fill from seed.mjs's lookups(); so are
// {DESIGN_TUTOR_LOGIN} (the invented tutor's account), {DESIGN_STUDENT} (a student's user ID at that school) and
// {INSTITUTION_UPDATES_PATH} (the newest update institutions read) and {INSTITUTION_UPDATES_VIEWING_PATH} (the same
// address with the design school named, for an Administrator). A scene whose token the copy cannot fill is skipped.
//   act    { open: scope }        press every closed fold inside the scope, outermost first
//          { openCard: '#id' }    the card with that anchor must be on the page; it and every fold in it open
//          { openFirst: scope }   the first closed fold inside the scope opens
//          { openFold: { text, scope } } the fold whose head reads text opens (the closed folds around it first)
//          { pick: selector }     the second entry of a Viewing as list is picked (then `open` may follow)
//          { refuse: { form, fill } } Gate B: the form is filled and posted, to be refused, and the page it lands on is the
//                                 picture (a refused form keeps its typing for the one drawing that follows, so the scene
//                                 posts again at each width); only a scene whose expect names the problem under a box posts
//          { choose: { input, name, mimeType, text } } Gate B: a file is chosen in the file box, nothing posted
//   gateB, who                    Gate B's state (GATE_B_STATES) and the reader who sees it (GATE_B_READERS)
//   noScript true                 taken in a browser context with JavaScript off (snap.mjs, measure.mjs)
//   expect.texts [[sel, words]]   Gate B: each element must hold those words
//   press  { scope, text, nth? }  the opener whose words match the pattern text is pressed and must say it opened
//                                 (aria-expanded="true", or its fold open); a button that is not an opener is
//                                 never pressed, so nothing is ever posted
//   tablet true                   also taken at 768 (release 2's wp-admin tabs only)
//   widths [w, ...]               also taken at these widths, whatever --sizes says
//   onlyWidths [w, ...]           taken at these widths and no other
//   planner true                  1.123.99's planner scenes, at the Institution Dashboard's seventeen widths
//   folded true                   the dashboard frame's menu folded, as Collapse menu leaves it: the frame's
//                                 own stored state (localStorage wpcpm-frame-folded = 1), set before the page loads
//   firstPx n                     with viewportOnly: the first n px of the page instead of one screen
//   panelBuild 'after'            a scene only the new build can show (listed as "after only")
//   reader, view                  the Institution Dashboard's scenes: who looks (representative, representative-agreement-in-review,
//                                 tutor, admin-viewing-school, admin-no-school) and which view (INSTITUTION_VIEWS)
//   expect { address?, selector?, selectors?, wait? }  what must be true once the page has loaded (checkExpect()), for a
//                                 scene reached by an old address or a view the reader may not open: `address` is a text the address bar must hold (only a hash the script
//                                 moves changes it; the snapper waits up to 10 seconds for it when `wait` is true),
//                                 `selector` an element that must exist, `selectors` a list of which every one must
//                                 (a view read from the address is proved by the frame's current menu item and the view's card). A scene whose expect fails is reported, never saved as a picture.

import { LONGWORD, LONG_STUDENT, LONG_TUTOR } from './seed.mjs';

export const SIZES = [{ width: 1280, height: 800, scale: 1 }, { width: 390, height: 844, scale: 2 }, { width: 768, height: 1024, scale: 1 }];

/**
 * Why a loaded page fails its scene's `expect`, or '' when it holds: `facts` is what the page answered, { address, found }
 * (the address bar's text, and each selector asked for with whether it exists). Names the address missing and every
 * selector missing.
 */
export function expectProblem(expect, facts = {}) {
  if (!expect) return '';
  const why = [];
  if (expect.address && !String(facts.address || '').includes(expect.address)) why.push(`the address does not hold ${expect.address} (it is ${facts.address || 'nothing'})`);
  const wanted = [...(expect.selector ? [expect.selector] : []), ...(expect.selectors || [])];
  const missing = wanted.filter(sel => !(facts.found || {})[sel]);
  if (missing.length) why.push(`missing: ${missing.join(', ')}`);
  // Gate B: what must NOT be drawn for this reader (`absent`, a list of selectors).
  const present = (expect.absent || []).filter(sel => (facts.found || {})[sel]);
  if (present.length) why.push(`drawn, and must not be: ${present.join(', ')}`);
  // Gate B: words an element must hold (`texts`, each [selector, words]), read from the element's text as the reader sees it.
  for (const [sel, words] of expect.texts || []) {
    const text = (facts.texts || {})[sel];
    if (typeof text !== 'string') why.push(`no ${sel}`);
    // Read as the reader sees it, so a tag the sheet sets in capitals ("ACCEPTED") holds its words ("Accepted").
    else if (!text.replace(/\s+/g, ' ').toLowerCase().includes(String(words).toLowerCase())) why.push(`without "${words}" in ${sel}`);
  }
  return why.join('; ');
}

/**
 * The scene's `expect` read from a loaded page: with `wait`, the address is waited for (up to `waitMs`, 10 seconds); then
 * every selector is looked for. Answers expectProblem()'s words, '' when the page holds what the scene expects.
 */
export async function checkExpect(page, scene, waitMs = 10000) {
  const e = scene.expect;
  if (!e) return '';
  if (e.address && e.wait) {
    const until = Date.now() + waitMs;
    while (!page.url().includes(e.address) && Date.now() < until) await page.waitForTimeout(200);
  }
  const wanted = [...(e.selector ? [e.selector] : []), ...(e.selectors || []), ...(e.absent || [])];
  const found = await page.evaluate(list => Object.fromEntries(list.map(sel => [sel, !!document.querySelector(sel)])), wanted);
  const texts = await page.evaluate(list => Object.fromEntries(list.map(sel => { const el = document.querySelector(sel); return [sel, el ? el.innerText || el.textContent || '' : null]; })), (e.texts || []).map(t => t[0]));
  return expectProblem(e, { address: page.url(), found, texts });
}

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
// The Institution Dashboard in the dashboards' frame (the frame's design, sections 3 and 7): every view of every kind of
// reader at the seventeen widths where its menu, its head and its tables change form (the in-between widths and the
// stacking widths, with the phone and the desktop).
export const INSTITUTION_WIDTHS = [390, 601, 700, 716, 768, 782, 783, 880, 900, 927, 928, 1000, 1015, 1024, 1110, 1200, 1280];
// Gate B's no-script pass: the phone, the tablet and the desktop.
export const NOSCRIPT_WIDTHS = [390, 768, 1280];
export const INSTITUTION_VIEWS = ['overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events', 'administrators'];
// Each reader, the account it signs in as (none: the rig's own Administrator) and the views it has, as
// WPCPM_Institutions_Dashboard::views_for() answers for it: a locked school (its agreement not settled) only the
// Overview and the Agreement; a tutor no Enroll students (ACT_ADD_STUDENT) and no Events (a school's own representatives');
// an Administrator For Administrators and no Events (it belongs to no school).
export const INSTITUTION_READERS = {
  'representative': { login: '{DESIGN_LOGIN}', views: ['overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'events'] },
  'representative-agreement-in-review': { login: '{UNIVERSITY_LOGIN}', views: ['overview', 'agreement'] },
  'tutor': { login: '{DESIGN_TUTOR_LOGIN}', views: ['overview', 'students', 'reports', 'institution', 'agreement'] },
  'admin-viewing-school': { login: '', views: ['overview', 'students', 'enroll', 'reports', 'institution', 'agreement', 'administrators'] },
  // A locked school with no agreement at all (approved, nothing generated or uploaded): the Overview and the Agreement.
  'representative-no-agreement': { login: '{NOAGREEMENT_LOGIN}', views: ['overview', 'agreement'] },
};
/** The selector of the frame's current menu item for a view (the Overview's link names no view). */
export function currentItem(view) {
  return view === 'overview' ? 'a.wpcpm-frame__item.is-current[aria-current="page"]:not([href*="wpcpm_view="])' : `a.wpcpm-frame__item.is-current[aria-current="page"][href*="wpcpm_view=${view}"]`;
}
const INSTITUTION_PAGE = '/institution-dashboard/';
/** A view's address on the Institution Dashboard; an Administrator's carries the school. */
function institutionPath(view, school = false) {
  const q = [];
  if (view !== 'overview') q.push(`wpcpm_view=${view}`);
  if (school) q.push('wpcpm_institution_view={DESIGN_RECORD}');
  return INSTITUTION_PAGE + (q.length ? '?' + q.join('&') : '');
}
const mid = (more = []) => ({ widths: [...new Set([...more, ...MID_WIDTHS])].sort((a, b) => a - b) });

// Gate B (the Institution Dashboard on the shared parts): every open state of the gate's list, by its key, with the view it
// is drawn on. The readers beyond B2's are the representatives of the schools the seed leaves in each agreement state and
// the long-word school, and an Administrator viewing each; a reader signs in as its account or names its school.
export const GATE_B_STATES = {
  'student-drew': 'students', 'student-sky': 'students', 'student-card': 'students', 'student-card-invited': 'students',
  'student-report-card': 'students', 'student-correct': 'students',
  'details-correct-now': 'institution', 'details-ask-checked': 'institution', 'details-correct-checked': 'institution', 'details-tracks': 'institution', 'details-assignment': 'institution',
  'reports-index': 'reports', 'reports-read': 'reports', 'reports-editor': 'reports', 'report-revisions': 'reports',
  'invite-refused': 'institution', 'tutor-add-refused': 'institution', 'details-refused': 'institution', 'student-correct-refused': 'students',
  'agreement-none': 'agreement', 'agreement-generated': 'agreement', 'agreement-submitted': 'agreement', 'agreement-returned': 'agreement',
  'agreement-revoked': 'agreement', 'agreement-accepted': 'agreement', 'agreement-newer-copy': 'agreement',
  'enroll-check-13': 'enroll', 'enroll-file-chosen': 'enroll', 'agreement-file-chosen': 'agreement',
  'viewing-as': 'overview', 'long-words-overview': 'overview', 'long-words-students': 'students', 'long-words-institution': 'institution',
  'overview-contact': 'overview', 'overview-good-to-know': 'overview',
  // 1.123.98: the narrowed lists See them opens, a student's card opened from one, and the people card reading "0 people".
  'only-no-sign-in': 'students', 'only-finishing': 'students', 'only-ended-open': 'students', 'only-pending-graduation': 'students',
  'only-no-sign-in-student-card': 'students', 'people-0': 'institution',
  // 1.123.99, S1: the people card of a school whose contact's account was removed from it.
  'people-removed-contact': 'institution',
};
// Each reader of a Gate B scene: the account it signs in as, or the school an Administrator names.
export const GATE_B_READERS = {
  'representative': { login: '{DESIGN_LOGIN}' }, 'tutor': { login: '{DESIGN_TUTOR_LOGIN}' }, 'admin-viewing-school': { school: '{DESIGN_RECORD}' },
  'representative-no-agreement': { login: '{NOAGREEMENT_LOGIN}' }, 'admin-viewing-no-agreement': { school: '{NOAGREEMENT_RECORD}' },
  'representative-generated': { login: '{GENERATED_LOGIN}' }, 'admin-viewing-generated': { school: '{GENERATED_RECORD}' },
  'representative-agreement-in-review': { login: '{UNIVERSITY_LOGIN}' }, 'admin-viewing-in-review': { school: '{UNIVERSITY_RECORD}' },
  'representative-returned': { login: '{RETURNED_LOGIN}' }, 'admin-viewing-returned': { school: '{RETURNED_RECORD}' },
  'representative-revoked': { login: '{REVOKED_LOGIN}' }, 'admin-viewing-revoked': { school: '{REVOKED_RECORD}' },
  'representative-newer-copy': { login: '{INSTITUTE_LOGIN}' }, 'admin-viewing-newer-copy': { school: '{INSTITUTE_RECORD}' },
  'representative-long-words': { login: '{LONG_LOGIN}' }, 'admin-viewing-long-words': { school: '{LONG_RECORD}' },
  // 1.123.98: the settled school whose only person is its contact, with no account (seed.mjs, PEOPLE0): an Administrator alone can look.
  'admin-viewing-people0': { school: '{PEOPLE_ZERO_RECORD}' },
  // 1.123.99, S1: the settled school whose contact's account was removed (seed.mjs, REMOVED_CONTACT).
  'admin-viewing-removed-contact': { school: '{REMOVED_CONTACT_RECORD}' },
};
// The B2 scenes that are a Gate B state already, by reader and view: each reader's report index. (The agreement states and
// the compact Viewing as are scenes of their own, each expecting its own words or control, fix round 1.)
const B2_STATES = {
  'representative reports': 'reports-index', 'tutor reports': 'reports-index', 'admin-viewing-school reports': 'reports-index',
};
// 1.123.98: the narrowed lists' keys (WPCPM_Institutions_Dashboard::ONLY_KEYS) and the words each list's sentence begins
// with after its count (only_title(), class-wpcpm-institutions-dashboard.php), as the plural reads.
export const ONLY_KEYS = ['no-sign-in', 'finishing', 'ended-open', 'pending-graduation'];
export const ONLY_WORDS = { 'no-sign-in': 'students have not signed in yet', finishing: 'students finish within 30 days', 'ended-open': 'placements ended and are still open', 'pending-graduation': 'students are waiting to graduate' };
/** A Gate B scene's address: the view (none for the Overview), its own arguments, then the school an Administrator names. */
function gatePath(view, reader, args = {}) {
  const q = [];
  if (view !== 'overview') q.push(`wpcpm_view=${view}`);
  for (const [k, v] of Object.entries(args)) q.push(`${k}=${v}`);
  const school = (GATE_B_READERS[reader] || {}).school;
  if (school) q.push(`wpcpm_institution_view=${school}`);
  return '/institution-dashboard/' + (q.length ? '?' + q.join('&') : '');
}
// The design school's two students with accounts (states.php's accounts): Drew Example signed in, Sky Example invited.
const STUDENT = { wpcpm_institution_student: '{DESIGN_STUDENT}' };
// A small list for the file box: chosen, never posted.
const CSV = { name: 'students-example.csv', mimeType: 'text/csv', text: 'name,email\nAsh Example,ash.example@example.org\n' };
const PDF = { name: 'agreement-example.pdf', mimeType: 'application/pdf', text: '%PDF-1.4\n%%EOF\n' };
const R3 = ['representative', 'tutor', 'admin-viewing-school'];
const RA = ['representative', 'admin-viewing-school'];
const both = s => ['representative-' + s, 'admin-viewing-' + s];
// Each agreement state's readers, and the words that prove it: the tag (WPCPM_UI::tag(), class-wpcpm-ui.php:242-244) and the
// sentence after it, as class-wpcpm-institution-panel.php prints them (none :935; generated :1528; submitted :2407; returned
// :2414 and :1550; revoked :2414 and :1553; accepted :2707-2709; a newer copy :2664). Each is [element, words it holds].
const AGREEMENT_STATES = {
  'agreement-none': { readers: both('no-agreement'), words: [['#wpcpm-agreement', 'The program has no Collaboration Agreement recorded for your institution.']] },
  'agreement-generated': { readers: both('generated'), words: [['#wpcpm-agreement', "You generated the program's agreement on"], ['#wpcpm-agreement', 'It is not signed and uploaded yet.']] },
  'agreement-submitted': { readers: ['representative-agreement-in-review', 'admin-viewing-in-review'], words: [['#wpcpm-agreement .wpcpm-status--amber', 'Waiting for review']] },
  'agreement-returned': { readers: both('returned'), words: [['#wpcpm-agreement .wpcpm-status--red', 'Returned'], ['#wpcpm-agreement', 'An Administrator returned your agreement, with this note:']] },
  'agreement-revoked': { readers: both('revoked'), words: [['#wpcpm-agreement .wpcpm-status--red', 'Revoked'], ['#wpcpm-agreement', "The program revoked this institution's agreement."]] },
  'agreement-accepted': { readers: RA, words: [['#wpcpm-agreement .wpcpm-status--green', 'Accepted']] },
  'agreement-newer-copy': { readers: both('newer-copy'), words: [['#wpcpm-agreement .wpcpm-status--green', 'Accepted'], ['#wpcpm-agreement', 'A newer signed agreement is waiting for review.']] },
};
// The compact Viewing as: the head's first row (WPCPM_UI::head_row(), class-wpcpm-ui.php:1493), its GET form
// (viewing_open(), :1503) and the select by the name the switcher posts (WPCPM_Institution_Roster::ARG_VIEW,
// class-wpcpm-institutions-dashboard.php:2326); with no script, its Show (viewing_show(), :1517).
export const VIEWING = '.wpcpm-head .wpcpm-ui-head-row form.wpcpm-ui-viewing select[name="wpcpm_institution_view"]';
const VIEWING_SHOW = '.wpcpm-head form.wpcpm-ui-viewing .wpcpm-ui-viewing__show button';
// The Overview's head beside the school's site icon (1.123.97; seed.mjs, ICONS; render_head(),
// class-wpcpm-institutions-dashboard.php): the design school's icon loads and is the first of the head's two children;
// the long-word school's does not load and takes itself off the page, so the copy is the only child of a head that
// keeps the logo's class. An icon that did not load is never there, so the first also says the picture was answered.
export const SITE_ICON = '.wpcpm-institution > .wpcpm-head.wpcpm-head--logo > img.wpcpm-institution__icon:first-child';
export const SITE_ICON_GONE = '.wpcpm-institution > .wpcpm-head.wpcpm-head--logo > .wpcpm-head__copy:only-child';
// The long words, the seed's own (seed.mjs), each found on the page as the reader reads it.
const word40 = v => String(v || '').split(/\s+/).find(w => w.length === 40) || '';
// The refusals: each its form, the boxes it types with a value its handler refuses, and exactly the problem under that box
// (WPCPM_Form_Errors::problem(), class-wpcpm-form-errors.php:236, the box's id and `-problem`) and the box marked invalid (:213).
// The student form's End date box is `wpcpm-student-` and the key of "students|End Date" (control_id(), key(): an f and the
// md5's first twelve, class-wpcpm-institution-student-form.php:249-251 and :1667).
const END_DATE = 'wpcpm-student-f154fd4923bc1';
export const REFUSALS = {
  // An address with no dot in its domain: the browser takes it, sanitize_email() drops it, is_email() refuses it (invite-bad-email).
  'invite-refused': { form: '#wpcpm-people-invite form', fill: [['input[name="email"]', 'malformed.example@example']], problem: '#wpcpm-people-invite[open] p.wpcpm-field__problem[id^="wpcpm-invite-email-"][id$="-problem"]', invalid: '#wpcpm-people-invite[open] input[name="email"][aria-invalid="true"]' },
  // The same rule for a tutor's address (wpcpm_tutor_email, class-wpcpm-institution-tutors.php:203).
  'tutor-add-refused': { form: '#wpcpm-tutors-add form', fill: [['input[name="name"]', 'Refused Example'], ['input[name="email"]', 'refused.example@example']], problem: '#wpcpm-tutors-add[open] p.wpcpm-field__problem[id^="wpcpm-tutor-email-"][id$="-problem"]', invalid: '#wpcpm-tutors-add[open] input[name="email"][aria-invalid="true"]' },
  // An emptied required answer: Contact Person is required (class-wpcpm-institution-application.php:779), refused-now.
  'details-refused': { form: '#wpcpm-details-now form', fill: [['input[name="wpcpm_details_contact_person"]', '']], problem: '#wpcpm-details-now[open] p.wpcpm-field__problem#wpcpm-details-contact_person-problem', invalid: '#wpcpm-details-now[open] #wpcpm-details-contact_person[aria-invalid="true"]' },
  // An end date before its start date (the design school's students start 2026-02-02): both dates refused (student-form.php:1161-1166).
  'student-correct-refused': { form: '#wpcpm-student-form form', fill: [['#' + END_DATE, '2026-01-05']], problem: `#wpcpm-student-form[open] p.wpcpm-field__problem#${END_DATE}-problem`, invalid: `#wpcpm-student-form[open] #${END_DATE}[aria-invalid="true"]` },
};
/**
 * The Gate B scenes that B2 does not take already: each a state, its readers, how it is reached and what proves it.
 * `opens` is the selector that must be open (or drawn) once the act is done; every scene also proves its view.
 */
function gateScenes() {
  const out = [];
  const add = (key, readers, how) => { for (const who of readers) out.push({ key, who, ...(typeof how === 'function' ? how(who) : how) }); };
  const fold = (text, scope, opens, args = {}) => ({ args, state: 'open', act: { openFold: { text, scope } }, opens: [opens] });
  // The roster's student rows (the inner fold titled by the name; a name with an account is a link, never pressed).
  add('student-drew', R3, fold('Drew Example', '#wpcpm-roster', '#wpcpm-roster details.wpcpm-ui-fold--inner[open]'));
  add('student-sky', R3, fold('Sky Example', '#wpcpm-roster', '#wpcpm-roster details.wpcpm-ui-fold--inner[open]'));
  // The student's card (class-wpcpm-institution-student-view.php): the item header, the Student Report Card fold.
  add('student-card', R3, { args: STUDENT, state: 'as-loaded', opens: ['.wpcpm-ui-item__head', '[data-wpcpm-report]'] });
  // The invited student who has not signed in: the line with Send the invitation again (#wpcpm-student-invite), which a tutor is not drawn.
  add('student-card-invited', R3, who => ({ args: { wpcpm_institution_student: '{DESIGN_STUDENT_INVITED}' }, state: 'as-loaded', opens: ['.wpcpm-ui-item__head', ...(who === 'tutor' ? [] : ['#wpcpm-student-invite'])] }));
  add('student-report-card', R3, fold('Student Report Card', 'main', 'details[data-wpcpm-report][open]', STUDENT));
  add('student-correct', RA, fold("Correct this student.s details", 'main', '#wpcpm-student-form[open]', STUDENT));
  // Your institution's details (class-wpcpm-institution-details-card.php): each group's fold by its id, Tracks and the Assigned Administrator.
  add('details-correct-now', RA, fold('Correct these', '#wpcpm-details-now', '#wpcpm-details-now[open]'));
  add('details-ask-checked', ['representative'], fold('Ask to correct these', '#wpcpm-details-checked', '#wpcpm-details-checked[open]'));
  add('details-correct-checked', ['admin-viewing-school'], fold('Correct these', '#wpcpm-details-checked', '#wpcpm-details-checked[open]'));
  add('details-tracks', ['admin-viewing-school'], fold('Change the tracks', '#wpcpm-details', '#wpcpm-details-tracks[open]'));
  add('details-assignment', ['admin-viewing-school'], fold('Change who follows it up', '#wpcpm-details', '#wpcpm-details-assignment[open]'));
  // Semester reports (class-wpcpm-semester-report-screen.php): the approved report read, the draft in the editor, its earlier versions.
  add('reports-read', R3, { args: { wpcpm_report: '2025-H2' }, state: 'as-loaded', opens: ['#wpcpm-report'] });
  add('reports-editor', ['admin-viewing-school'], { args: { wpcpm_report: '2026-H1' }, state: 'as-loaded', opens: ['#wpcpm-report form textarea'] });
  add('report-revisions', ['admin-viewing-school'], fold('Earlier versions of this report', '#wpcpm-report', '#wpcpm-report details.wpcpm-ui-fold--inner[open]', { wpcpm_report: '2026-H1' }));
  // Refused forms with their typing kept (WPCPM_Flash::keep): the scene posts the form itself at each width, as its reader,
  // and the picture is the page the refusal lands on, the problem under the box it refused (WPCPM_Form_Errors::problem()).
  const refused = (key, args = {}) => ({ args, state: 'refused', act: { refuse: { form: REFUSALS[key].form, fill: REFUSALS[key].fill } }, opens: [REFUSALS[key].problem, REFUSALS[key].invalid] });
  // An address the browser takes (type=email allows a host with no dot) and is_email() refuses: "invite-bad-email".
  add('invite-refused', RA, refused('invite-refused'));
  add('tutor-add-refused', RA, refused('tutor-add-refused'));
  // The contact's name emptied: the application's own rule refuses a required answer ("refused-now").
  add('details-refused', RA, refused('details-refused'));
  // An end date before the start date: the form's own rule refuses both dates ("Check this date against the start date").
  add('student-correct-refused', RA, refused('student-correct-refused', STUDENT));
  // The agreement in each state, for the school's representative and an Administrator viewing it: the Agreement view, and the
  // Overview of the four schools the seed adds for it (their waiting rows are new). The settled school's, the one in review's and
  // the one with none are B2's own Agreement scenes, but for an Administrator viewing the two that are not settled.
  // Each scene is skipped, not saved under the wrong name, when the seed did not reach its state: lookups() answers the
  // school's login and record only while the summary is in it (seed.mjs, inState()).
  for (const [key, { readers, words }] of Object.entries(AGREEMENT_STATES)) {
    add(key, readers, { state: 'as-loaded', act: { open: 'main' }, opens: [], texts: words });
  }
  // The compact Viewing as in the head's first row, on an Administrator's Overview as it loads.
  add('viewing-as', ['admin-viewing-school'], { state: 'as-loaded', opens: [VIEWING] });
  // The file box after a file is chosen (WPCPM_UI::file()): its name shown, nothing posted.
  add('enroll-file-chosen', RA, { state: 'chosen', act: { choose: { input: '#wpcpm-import-file', ...CSV } }, opens: ['#wpcpm-import .wpcpm-ui-file__name:not([hidden])'] });
  add('agreement-file-chosen', both('no-agreement'), { state: 'chosen', act: { choose: { input: 'input[name="wpcpm_agreement_file"]', ...PDF } }, opens: ['.wpcpm-ui-file__name:not([hidden])'] });
  // The enrollment check: the long-word school's list of thirteen lines, checked and waiting (Create these student records).
  add('enroll-check-13', both('long-words'), { args: { wpcpm_batch: '{LONG_BATCH}' }, state: 'as-loaded', opens: ['#wpcpm-import table.wpcpm-ui-table tbody tr:nth-child(13)'] });
  // The long words: the school's name in the head and the menu, the student's on the roster, the tutor's name and address.
  // Its site icon is the one that does not load (1.123.97): the head keeps the logo's class with the copy alone in it.
  add('long-words-overview', both('long-words'), { state: 'as-loaded', opens: [SITE_ICON_GONE], texts: [['.wpcpm-head', LONGWORD.name]] });
  add('long-words-students', both('long-words'), { state: 'all-open', act: { open: 'main' }, opens: [], texts: [['#wpcpm-roster', word40(LONG_STUDENT.name)]] });
  add('long-words-institution', both('long-words'), { state: 'all-open', act: { open: 'main' }, opens: [], texts: [['body', word40(LONG_TUTOR.name)], ['body', LONG_TUTOR.email]] });
  // The Overview's contact card, once an Administrator follows the school up, and "Good to know" with the decided request.
  // The design school's site icon is the one that loads (1.123.97): the head draws it beside the copy.
  add('overview-contact', R3, { state: 'as-loaded', opens: ['.wpcpm-institution__contact', SITE_ICON] });
  // A tutor is not given the row "Your request was decided" (1.123.96: its card is not drawn for a tutor): the tutor's scene
  // expects it absent.
  add('overview-good-to-know', ['representative', 'tutor'], who => (who === 'tutor' ? { state: 'as-loaded', opens: [], absent: ['.wpcpm-institution__news a[href*="wpcpm_view=institution"]'] } : { state: 'as-loaded', opens: ['.wpcpm-institution__news a[href*="wpcpm_view=institution"]'] }));
  // 1.123.98: each narrowed list the Overview's facts open (See them), for the reader offered it (a tutor the two lists of
  // facts alone), its sentence, Show all students and one list; a student's card opened from the list of students who have
  // not signed in yet (the invited student, with Send the invitation again); and Your institution of the settled school
  // whose only person is its contact with no account, for an Administrator: the people card's head reads "0 people".
  for (const key of ONLY_KEYS) {
    add(`only-${key}`, key === 'finishing' || key === 'pending-graduation' ? ['representative', 'tutor'] : ['representative'], { args: { wpcpm_only: key }, state: 'as-loaded', opens: ['#wpcpm-roster .wpcpm-ui-list'], texts: [['#wpcpm-roster', ONLY_WORDS[key]], ['#wpcpm-roster', 'Show all students']] });
  }
  add('only-no-sign-in-student-card', ['representative'], { args: { wpcpm_only: 'no-sign-in', wpcpm_institution_student: '{DESIGN_STUDENT_INVITED}' }, state: 'as-loaded', opens: ['.wpcpm-ui-item__head', '#wpcpm-student-invite'] });
  // 1.123.99, S1: its contact row says the account is no longer among the school's people.
  add('people-removed-contact', ['admin-viewing-removed-contact'], { state: 'all-open', act: { open: 'main' }, opens: ['#wpcpm-people'], texts: [['#wpcpm-people', "Their account is no longer among this institution's people."]] });
  add('people-0', ['admin-viewing-people0'], { state: 'all-open', act: { open: 'main' }, opens: ['#wpcpm-people'], texts: [['#wpcpm-people', '0 people']] });
  return out;
}

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

  // B2. The Institution Dashboard's every view for every kind of reader, all open, at the seventeen widths; each
  // Overview also as it loads. The four scenes above keep their names (the comparison needs them), so the Overview
  // all open is named by its view here.
  for (const [reader, { login, views }] of Object.entries(INSTITUTION_READERS)) {
    const school = reader === 'admin-viewing-school';
    for (const view of views) {
      const how = { reader, view, onlyWidths: INSTITUTION_WIDTHS, act: { open: 'main' } };
      if (login) how.login = login;
      // Gate B: the compact Viewing as is an Administrator's Overview as it loads; a B2 view that is a Gate B state is named for it.
      if (view === 'overview') add('institution-dashboard', `${reader}-overview`, 'closed', institutionPath(view, school), { ...how, act: undefined, note: 'the Overview as it loads, nothing opened' });
      const gateB = B2_STATES[`${reader} ${view}`];
      add('institution-dashboard', `${reader}-${view}`, 'all-open', institutionPath(view, school), { ...how, ...(gateB ? { gateB, who: reader } : {}), note: `${reader}: the ${view} view, every fold and panel open` });
    }
  }
  // An Administrator whose address names no school: the choice, on the Overview and on a view that must be kept.
  add('institution-dashboard', 'admin-no-school-choose-an-institution', 'all-open', institutionPath('overview'), { reader: 'admin-no-school', view: 'overview', choice: true, onlyWidths: INSTITUTION_WIDTHS, act: { open: 'main' }, note: 'no school named: "Choose an institution"' });
  add('institution-dashboard', 'admin-no-school-choice-keeps-students', 'all-open', institutionPath('students'), { reader: 'admin-no-school', view: 'students', choice: true, onlyWidths: INSTITUTION_WIDTHS, act: { open: 'main' }, note: 'no school named, on ?wpcpm_view=students: the choice keeps the view' });
  // The old addresses, each of which must land on its view (the snapper asserts `expect`).
  add('institution-dashboard', 'students-card', 'old-address', INSTITUTION_PAGE + '?wpcpm_institution_student={DESIGN_STUDENT}', { login: '{DESIGN_LOGIN}', expect: { selectors: [currentItem('students'), '.wpcpm-ui-item__head', '[data-wpcpm-report]'] }, act: { open: 'main' }, note: "a student's card by the address before the frame (the design school's first student, an account since Gate B): it is drawn on Students (the address is not rewritten)" });
  add('institution-dashboard', 'reports-draft', 'old-address', INSTITUTION_PAGE + '?wpcpm_report=2026-H1&wpcpm_institution_view={DESIGN_RECORD}', { expect: { selectors: [currentItem('reports'), '#wpcpm-report'] }, act: { open: 'main' }, note: 'the January to June 2026 draft by the address before the frame (an Administrator viewing the school); it is drawn on Semester reports (the address is not rewritten)' });
  add('institution-dashboard', 'enroll-hash', 'old-address', INSTITUTION_PAGE + '#wpcpm-import', { login: '{DESIGN_LOGIN}', expect: { address: 'wpcpm_view=enroll', selector: '#wpcpm-import', wait: true }, act: { open: 'main' }, note: 'the bare page with #wpcpm-import: the script moves it to Enroll students, the scene waits for the address' });
  // A batch by the address before the frame (the design school's newest enrollment list): drawn on Enroll students.
  add('institution-dashboard', 'batch-enroll', 'old-address', INSTITUTION_PAGE + '?wpcpm_batch={DESIGN_BATCH}', { login: '{DESIGN_LOGIN}', expect: { selectors: [currentItem('enroll'), '#wpcpm-import'] }, act: { open: 'main' }, note: "a batch by the address before the frame; it is drawn on Enroll students (the address is not rewritten)" });
  // A view the reader may not open, asked for by its address: the Overview is drawn instead.
  add('institution-dashboard', 'locked-asks-students', 'refused-view', INSTITUTION_PAGE + '?wpcpm_view=students', { login: '{UNIVERSITY_LOGIN}', expect: { selectors: [currentItem('overview')] }, act: { open: 'main' }, note: 'a locked school (its copy in review) asks for Students: the Overview is drawn' });
  add('institution-dashboard', 'tutor-asks-enroll', 'refused-view', INSTITUTION_PAGE + '?wpcpm_view=enroll', { login: '{DESIGN_TUTOR_LOGIN}', expect: { selectors: [currentItem('overview')] }, act: { open: 'main' }, note: 'a tutor asks for Enroll students: the Overview is drawn' });
  // The Institution guide and one update, for a representative and for an Administrator viewing the school. The
  // guide is some 40,000px tall: its first 2,400px.
  for (const [who, login, school] of [['representative', '{DESIGN_LOGIN}', ''], ['administrator-viewing', '', '?wpcpm_institution_view={DESIGN_RECORD}']]) {
    const how = { onlyWidths: INSTITUTION_WIDTHS, ...(login ? { login } : {}) };
    add('institution-pages', `guide-${who}`, 'as-loaded', '/institution-guide/' + school, { ...how, viewportOnly: true, firstPx: 2400, note: 'the Institution guide in the frame (its first 2400px)' });
    // The Administrator's address is the update's own with the school named: a whole path of its own ({..._VIEWING_PATH}).
    add('institution-pages', `update-${who}`, 'as-loaded', login ? '{INSTITUTION_UPDATES_PATH}' : '{INSTITUTION_UPDATES_VIEWING_PATH}', { ...how, note: 'the newest update institutions read, in the frame' });
  }

  // C2. Gate B: every open state of the gate's list B2 does not take already, for each reader who can see it, at the
  // seventeen widths, each proving its view and its state (`expect`), and taken on the new build only.
  for (const g of gateScenes()) {
    const view = GATE_B_STATES[g.key];
    const r = GATE_B_READERS[g.who];
    add('institution-dashboard', `${g.who}-${g.key}`, g.state, gatePath(view, g.who, g.args), {
      gateB: g.key, who: g.who, ...(r.login ? { login: r.login } : {}), ...(g.act ? { act: g.act } : {}), onlyWidths: INSTITUTION_WIDTHS, panelBuild: 'after',
      expect: { selectors: [currentItem(view), ...g.opens], ...(g.texts ? { texts: g.texts } : {}), ...(g.absent ? { absent: g.absent } : {}) }, note: `Gate B, ${g.key}: ${g.who}`,
    });
  }

  // C3. Gate B, no script (fix round 1): a browser context with JavaScript off (`noScript`, snap.mjs), at 390, 768 and 1280.
  // Each view of the representative and of an Administrator viewing the school as it loads (nothing can be pressed: the
  // picture is what a reader without script gets, every fold as the server draws it); the Viewing as select with its Show;
  // and one refusal of each form kind, posted with no script through the same act.refuse (the form posts as a browser
  // with no script posts it, and the refusal's page is the picture).
  for (const reader of ['representative', 'admin-viewing-school']) {
    const { login, views } = INSTITUTION_READERS[reader];
    for (const view of views) {
      add('institution-dashboard', `noscript-${reader}-${view}`, 'as-loaded', institutionPath(view, reader === 'admin-viewing-school'), { noScript: true, who: reader, view, ...(login ? { login } : {}), onlyWidths: NOSCRIPT_WIDTHS, note: `${reader}: the ${view} view as it loads with JavaScript off` });
    }
  }
  add('institution-dashboard', 'noscript-admin-viewing-school-viewing-as', 'as-loaded', gatePath('overview', 'admin-viewing-school'), { noScript: true, gateB: 'viewing-as', who: 'admin-viewing-school', onlyWidths: NOSCRIPT_WIDTHS, panelBuild: 'after', expect: { selectors: [currentItem('overview'), VIEWING, VIEWING_SHOW] }, note: 'the Viewing as select and its Show, with JavaScript off' });
  for (const key of Object.keys(REFUSALS)) {
    const view = GATE_B_STATES[key];
    const r = REFUSALS[key];
    add('institution-dashboard', `noscript-representative-${key}`, 'refused', gatePath(view, 'representative', key === 'student-correct-refused' ? STUDENT : {}), { noScript: true, gateB: key, who: 'representative', login: GATE_B_READERS.representative.login, act: { refuse: { form: r.form, fill: r.fill } }, onlyWidths: NOSCRIPT_WIDTHS, panelBuild: 'after', expect: { selectors: [currentItem(view), r.problem, r.invalid] }, note: `${key}, posted with JavaScript off` });
  }

  // Every Institution Dashboard scene of the seventeen widths (each reader's views all open, the choice, the guide and the
  // update, Gate B's states) again at 1110 and 1200 with the menu folded, as Collapse menu leaves it.
  for (const s of list.filter(x => (x.reader || x.gateB || x.area === 'institution-pages') && x.state !== 'closed' && x.onlyWidths === INSTITUTION_WIDTHS)) {
    list.push({ ...s, state: s.state + '-menu-folded', onlyWidths: FOLDED_WIDTHS, folded: true, note: s.note + '; the side menu folded (Collapse menu)' });
  }

  // C. Every Sponsor Dashboard view, as the copy holds it (the first sponsor, names replaced).
  for (const [v, label] of [['', 'overview'], ['sponsorship', 'your-sponsorship'], ['offers', 'offers'], ['reached', 'students-reached'], ['mentors', 'mentors-to-sponsor'], ['company', 'your-company'], ['agreement', 'agreement']]) {
    add('sponsor-dashboard', label, 'all-open', '/sponsor-dashboard/' + (v ? `?wpcpm_view=${v}` : ''), { act: { open: 'main' }, note: "the copy's first sponsor (scrubbed, names replaced)" });
  }

  // D. Both Report Cards, as the Administrator viewing the second entry of the Viewing as list.
  add('mentor-report-card', 'admin-viewing-mentor', 'all-open', '/mentor-dashboard/', { act: { pick: 'select[name="wpcpm_mentor"]', open: 'main' } });
  add('student-report-card', 'admin-viewing-student', 'all-open', '/student-dashboard/', { act: { pick: 'select[name="wpcpm_student_view"]', open: 'main' } });
  // 1.123.99: the fold "Plan a group session" opened on a mentor's own Mentor Report Card (its summary pressed, nothing
  // posted), for the invented mentor with a meeting link set and for the one with none, at the Institution Dashboard's
  // seventeen widths. The fold and its form are drawn by both builds; the line over the first field by the new one alone.
  for (const [who, login] of [['link', '{PLANNER_LINK_LOGIN}'], ['none', '{PLANNER_NONE_LOGIN}']]) {
    add('mentor-report-card', `planner-${who}`, 'fold-open', '/mentor-dashboard/', { login, planner: true, onlyWidths: INSTITUTION_WIDTHS, act: { openFold: { text: '^Plan a group session', scope: '.wpcpm-calls' } }, expect: { selectors: ['details.wpcpm-sessions__new[open] form.wpcpm-sessions__form'] }, note: `1.123.99: the planner open, the mentor ${who === 'link' ? 'with a meeting link set' : 'with no meeting link'}` });
  }
  // Both Report Cards as they are today, with the seed's content (seed-states.php, `report-cards`): the planner's mentor
  // Linden Example with three current students and a past one, and Drew Example, one of them. Each as its own reader and as
  // an Administrator viewing it; a fold opened only by pressing its summary, nothing posted.
  add('mentor-report-card', 'today-mentor', 'as-loaded', '/mentor-dashboard/', { login: '{PLANNER_LINK_LOGIN}', note: 'the mentor on their own card, as it loads' });
  add('mentor-report-card', 'today-mentor', 'mentee-open', '/mentor-dashboard/', { login: '{PLANNER_LINK_LOGIN}', act: { openFold: { text: 'Drew Example', scope: 'main' } }, note: "Drew Example's row opened, its Report form fold left closed" });
  add('mentor-report-card', 'today-admin-viewing-mentor', 'as-loaded', '/mentor-dashboard/?wpcpm_mentor={PLANNER_LINK_ID}', { note: 'an Administrator viewing the mentor, as it loads' });
  add('student-report-card', 'today-student', 'as-loaded', '/student-dashboard/', { login: '{DREW_LOGIN}', note: 'the student on their own card, as it loads' });
  add('student-report-card', 'today-student', 'report-open', '/student-dashboard/', { login: '{DREW_LOGIN}', act: { openFold: { text: '.', scope: 'details.wpcpm-report__disclosure' } }, note: 'the Report form fold opened' });
  add('student-report-card', 'today-admin-viewing-student', 'as-loaded', '/student-dashboard/?wpcpm_student_view={DREW_ID}', { note: 'an Administrator viewing the student, as it loads' });
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

/**
 * What Gate B measures, from what measure.mjs collects on a page (its `raw`): the parts' buttons (`.wpcpm-btn`,
 * WPCPM_UI::button(), class-wpcpm-ui.php:166) all of one height; the card fold heads 56px (the shell's card fold,
 * `.wpcpm-ui-fold-card > .wpcpm-ui-fold > .wpcpm-ui-fold__head`, class-wpcpm-dashboard-card.php:668-680) and the inner fold
 * heads 44px (`.wpcpm-ui-fold--inner > summary.wpcpm-ui-fold__head`, class-wpcpm-ui.php:1081-1082); the parts' boxes
 * (`.wpcpm-ui-field__control`, field() and the combo's select, class-wpcpm-ui.php:508) all of one height, a text area
 * aside; and no row action (`.wpcpm-ui-list__act`, class-wpcpm-ui.php:1474) drawn with a border.
 */
export function gateMeasures(r) {
  const nums = a => [...new Set(a)].sort((x, y) => x - y);
  const buttonHeights = nums((r.buttons || []).filter(b => b.parts).map(b => b.h));
  const cards = (r.heads || []).filter(h => h.kind === 'card');
  const cardHeads = nums(cards.map(h => h.h));
  const inner = (r.heads || []).filter(h => h.kind === 'inner');
  const innerHeads = nums(inner.map(h => h.h));
  const fieldHeights = nums((r.fields || []).filter(f => f.parts && f.tag !== 'textarea').map(f => f.h));
  // The Overview's rows carry the one secondary button the design gives them (design 5): that one is not a bordered row
  // action. Any other bordered one is, a second one on such a row too (Gate B's fix round).
  const rowActionBorders = (r.rowActions || []).filter(a => a.border > 0 && !(a.overview && a.secondary && a.alone)).length;
  return {
    buttonHeights, oneButtonHeight: buttonHeights.length <= 1,
    // A card head is 56px; under 800px of main area its stacked form is 70px (Gate B's fix round: the parts' narrow form),
    // and a head of one line, with no phrase and no tag, stays 56px there (round 2, F8; `words` false).
    cardHeads, cardHeads56: cards.every(h => h.h === (h.narrow && h.words !== false ? 70 : 56)),
    // An inner fold head of words is 44px in the one-row form, its second piece on the title's line (round 2, F1): under
    // the title there only when the line has no room for both. Otherwise, and in the stacked form, it is its text
    // column's lines and the head's padding (66px with a second piece stacked). A head that holds a photo is the photo
    // and the head's own padding, the same above and below its content, and nothing touches the edge (Gate B, B3,
    // ruled): 68px, or its stacked lines.
    innerHeads, innerHeads44: inner.every(h => {
      if (h.photo) return h.photo.above === h.photo.below && h.photo.above >= 8;
      if (h.lead === undefined) return h.h === 44;
      if (h.second && h.second.under && h.second.fits && !h.stacked) return false;
      // The parts' padding, 12px above and below (round 3, R1: the box model makes any head its lead plus its padding),
      // and a lead that is its lines: 20px each, and 2px between the title and a second piece drawn under it.
      const lines = h.lines === undefined ? Math.round((h.lead - (h.second && h.second.under ? 2 : 0)) / 20) : h.lines;
      const lead = 20 * lines + (h.second && h.second.under ? 2 : 0);
      return 24 === h.pad && h.lead === lead && Math.round(h.h) === h.lead + h.pad;
    }),
    fieldHeights, oneFieldHeight: fieldHeights.length <= 1,
    rowActionBorders, rowActionsBorder0: 0 === rowActionBorders,
  };
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

/**
 * Press a fold's head. A head whose middle is a link (a student's name on the roster, which opens the student's card) is
 * pressed on its chevron, in the page, and never on the link; any other head is clicked where it is, as before.
 */
async function pressHead(h) {
  await h.scrollIntoViewIfNeeded().catch(() => {});
  const onLink = await h.evaluate(s => { const r = s.getBoundingClientRect(); const e = document.elementFromPoint(r.left + r.width / 2, r.top + r.height / 2); const a = e && e.closest('a'); return !!(a && s.contains(a)); });
  if (onLink) return h.evaluate(s => { const c = s.querySelector('.wpcpm-ui-fold__chev') || s.querySelector('.wpcpm-ui-fold__end') || s; c.click(); });
  return h.click({ timeout: 3000 });
}

/** Press every closed fold's head inside the scope, outermost first, until none is closed. */
export async function openAll(page, scope = 'body') {
  for (let round = 0; round < 6; round++) {
    const heads = await page.$$(`${scope} details:not([open]) > summary`);
    let pressed = 0;
    for (const h of heads) {
      const ok = await h.evaluate(s => { const r = s.getBoundingClientRect(); return r.width > 0 && r.height > 0 && !s.parentElement.open && !s.parentElement.closest('.wpcpm-frame__account'); });
      if (ok) { await pressHead(h).catch(() => {}); pressed++; await page.waitForTimeout(80); }
    }
    if (!pressed) return;
    await settle(page);
  }
}

/** Open, outermost first, every closed fold around an element (a fold inside a closed one, a form inside a closed fold). */
async function openAround(page, selector) {
  if (!(await page.locator(selector).count())) throw new Error(`${selector} is not on the page`);
  // Five seconds at most: a box that is missing fails its scene at once, never after the default thirty.
  const heads = await page.locator(selector).first().evaluate(e => { const out = []; for (let d = e.parentElement && e.parentElement.closest('details'); d; d = d.parentElement && d.parentElement.closest('details')) out.unshift(d); return out.filter(d => !d.open).length; }, null, { timeout: 5000 });
  for (let i = 0; i < heads; i++) {
    // The outermost closed fold around the element, pressed on its head; then the next.
    const head = await page.locator(selector).first().evaluateHandle(e => { let top = null; for (let d = e.parentElement && e.parentElement.closest('details'); d; d = d.parentElement && d.parentElement.closest('details')) if (!d.open) top = d; return top ? top.querySelector(':scope > summary') : null; }, null, { timeout: 5000 });
    const el = head.asElement();
    if (!el) break;
    await pressHead(el);
    await settle(page);
  }
}

/**
 * Post a form to be refused, as the scene's reader: its folds opened, its boxes filled, its first submit pressed, the page it
 * lands on waited for. Only a refusal scene does this (its expect names the problem under a box, so a post that was taken is
 * never saved as a picture); WPCPM_Flash::keep() hands the typing back to the one drawing that follows, which is this one.
 */
async function refuse(page, { form, fill = [], submit = '[type="submit"]' }) {
  if (!(await page.locator(form).count())) throw new Error(`no form ${form} to post`);
  await openAround(page, form);
  const f = page.locator(form).first();
  for (const [sel, value] of fill) await f.locator(sel).first().fill(value);
  page.once('dialog', d => d.accept());
  await Promise.all([page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 30000 }), f.locator(submit).first().click()]);
  await settle(page);
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
  if (act.refuse) { await refuse(page, act.refuse); how.push('posted the form to be refused'); }
  if (act.choose) { if (!(await page.locator(act.choose.input).count())) throw new Error(`no file box ${act.choose.input} on the page`); await openAround(page, act.choose.input); await page.locator(act.choose.input).first().setInputFiles({ name: act.choose.name, mimeType: act.choose.mimeType, buffer: Buffer.from(act.choose.text || '') }, { timeout: 5000 }); await page.waitForTimeout(250); how.push('chose a file, posted nothing'); }
  if (act.openFold) {
    const s = page.locator(`${act.openFold.scope} summary`, { hasText: new RegExp(act.openFold.text, 'i') }).first();
    if (!(await s.count())) throw new Error(`no fold reading "${act.openFold.text}" in ${act.openFold.scope}`);
    // A fold inside a closed one (a student's row inside its group) has the folds around it opened first.
    const css = `${act.openFold.scope} summary`;
    await s.evaluate(e => { e.dataset.uiRigTarget = '1'; });
    await openAround(page, `${css}[data-ui-rig-target]`);
    await s.evaluate(e => { delete e.dataset.uiRigTarget; });
    if (!(await s.evaluate(e => e.parentElement.open))) { await pressHead(s); await settle(page); }
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
