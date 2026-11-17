// The invented items the pictures show, made through the site's own forms, as a visitor or as the local
// Administrator would, on the copy only. Invented schools and people at example.org only. snap.mjs calls
// seed() on a copy restored to ui-base (copy.sh prepare), then saves the result as the snapshot ui-ready.
//
// The work folder (agreement PDFs) is WPCPM_UI_WORK, or .superpowers/ui-rig/work in this repository.
import { execFileSync } from 'node:child_process';
import { appendFileSync, copyFileSync, existsSync, mkdirSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { ADMIN_LOGIN, DOCKER, refuseUnlessCopy, wp } from './copy.mjs';

const REPO = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
export const WORK = process.env.WPCPM_UI_WORK || join(REPO, '.superpowers/ui-rig/work');
const LINKS = join(DOCKER, 'site/wp-content/wpcpm-ui-rig-links.log');
// Every file the seed makes on the copy, by its path under wp-content, one a line: copy.sh restore removes
// exactly these and nothing else.
export const CREATED = join(WORK, 'created-files.txt');

// The invented schools and people. Every name holds "Example", which the must-use plugin's queues keep.
export const STATE_COLLEGE = { name: 'Example State College', city: 'Exampleford', website: 'state.example.org', contact: 'Alex Example', email: 'alex.example@example.org', signer: 'Sam Example' };
export const UNIVERSITY = { name: 'Example University', city: 'Exampleton', website: 'university.example.org', contact: 'Jordan Example', email: 'jordan.example@example.org', signer: 'Robin Example' };
export const DESIGN = { name: 'Example School of Design', city: 'Hillford', website: 'design.example.org', contact: 'Quinn Example', email: 'quinn.example@example.org' };
export const INSTITUTE = { name: 'Example Institute of Technology', city: 'Examplestad', website: 'tech.example.org', contact: 'Avery Example', email: 'avery.example@example.org' };
export const COMMUNITY = { name: 'Example Community College', city: 'Examplevale', website: 'community.example.org', contact: 'Rowan Example', email: 'rowan.example@example.org' };
export const TAYLOR = { name: 'Taylor Example', email: 'taylor.example@example.org', profile: 'https://profiles.wordpress.org/taylor-example/', hours: 4 };
// The tutor the Institution Dashboard's tutor scenes sign in as: listed at the design school through its Add a tutor
// form and given a tutor-level account (the list alone holds no account).
export const TUTOR = { name: 'Tutor Example', email: 'tutor.example@example.org' };
// A school approved and nothing more: no agreement generated or uploaded (a locked school with no agreement at all).
export const NOAGREEMENT = { name: 'Example College of Music', city: 'Examplebrook', website: 'music.example.org', contact: 'Reese Example', email: 'reese.example@example.org' };
// Gate B: a school in each agreement state the forms reach (generated, returned with a note, revoked), and a school of
// its own for the long words, so the ordinary scenes keep their ordinary names.
export const GENERATED = { name: 'Example College of Engineering', city: 'Examplecrest', website: 'engineering.example.org', contact: 'Parker Example', email: 'parker.example@example.org' };
export const RETURNED = { name: 'Example Institute of Arts', city: 'Exampledale', website: 'arts.example.org', contact: 'Dakota Example', email: 'dakota.example@example.org' };
export const REVOKED = { name: 'Example Business School', city: 'Examplemoor', website: 'business.example.org', contact: 'Kendall Example', email: 'kendall.example@example.org' };
// The long words: each an unbroken word of 40 characters, and an address of 48, invented, "Example" in each.
export const LONGWORD = { name: 'Examplepolytechnicforopensourcewebdesign', city: 'Exampleridge', website: 'polytechnic.example.org', contact: 'Sasha Example', email: 'sasha.example@example.org' };
export const LONG_STUDENT = { name: 'Examplestudentwhosenamerunspasttheedgeof Example', email: 'longname.student.example@example.org' };
export const LONG_TUTOR = { name: 'Exampletutorwithanunbrokennamefortylongs Example', email: 'exampletutorwithaverylongaddress0001@example.org' };
// The long-word school's other student, and the thirteen lines of the list it checks and leaves waiting: eleven new
// students, one already enrolled (the long-word student's address) and one with no address.
export const LONG_OTHER = { name: 'Noor Example', email: 'noor.example@example.org' };
export const CHECKED_LIST = [...Array.from({ length: 11 }, (v, i) => ({ name: `Enrollee ${String(i + 1).padStart(2, '0')} Example`, email: `enrollee${String(i + 1).padStart(2, '0')}.example@example.org` })), { name: LONG_STUDENT.name, email: LONG_STUDENT.email }, { name: 'Enrollee Without Address Example', email: '' }];
// The update institutions read, written in the post editor: invented words only.
export const UPDATE = { title: 'Example update for schools', body: 'An invented update for the picture rig: the spring term opens on an example date, and every school is asked to check its list of students before then.' };
// Every invented school and person the seed makes, the students, colleague and tutor included.
// 1.123.98: the design school's students in every state the Overview's facts read, and a settled school whose only person
// is its contact, with no account (seed-states.php, `overview-more`).
export const PEOPLE0 = { name: 'Example Conservatory of Media', city: 'Examplemere', website: 'conservatory.example.org', contact: 'Kai Example', email: 'kai.example@example.org' };
export const OVERVIEW_MORE = { names: 'Nia Example, Oakley Example, Penn Example, Quill Example, Remy Example, Sol Example, Tam Example, Uma Example, Vale Example, Wren Example, Xan Example, Examplecontributorteamwithanunbrokenname, Core, Accessibility' };
export const INVENTED = { PEOPLE0, OVERVIEW_MORE, STATE_COLLEGE, UNIVERSITY, DESIGN, INSTITUTE, COMMUNITY, TAYLOR, TUTOR, NOAGREEMENT, UPDATE, GENERATED, RETURNED, REVOKED, LONGWORD, LONG_STUDENT, LONG_TUTOR, LONG_OTHER, GATE_B: { names: 'Enrollee Example, Refused Example, Exampleside, Hillford, Drew Example, Sky Example' }, OTHERS: { names: 'Drew Example, Sky Example, Pat Example, Lee Example, Ash Example, Blake Example, Emery Example, Finley Example, Example Polytechnic, Example Academy of Arts, Example Technical School, Example Hosting Co, Example Plugins Ltd, Jamie Example, Taylor Sample, Casey Example, Morgan Example, Riley Example' }, GATE_7: { names: 'Riley Example, Examplegraduatewaitingonthefinalquizpass Example, Sage Example, TEST Example Academy, Example Academy of Arts, Harper Example, Exampleinstituteforappliedwebengineering, Christopherexample Wojciechowskaexample, Unlisted Example, TEST Example Academy of Design Media and the Applied Arts, Rowan Mentor Example, Indigo Example, Marlowe Example, Learner Example' } };
// The two pictures the invented sponsor application's logos are, written into a folder the copy has already.
export const LOGOS = { colour: 'uploads/2026/10/ui-rig-logo-colour.png', white: 'uploads/2026/10/ui-rig-logo-white.png' };
// The two site icons the Institution Dashboard's head is drawn beside (1.123.97), each a path under the copy's own
// wp-content, so neither the copy nor the browser asks anything off this machine: `loads` is a picture the seed
// writes, the design school's; `fails` names a file the seed never writes, the long-word school's, so the copy answers
// 404 and the icon takes itself off the page. seed-states.php stores each as its school's cached site icon (`icons`).
export const ICONS = { loads: 'uploads/2026/10/ui-rig-school-icon.png', fails: 'uploads/2026/10/ui-rig-school-icon-missing.png' };
const FFMPEG = process.env.WPCPM_FFMPEG || 'ffmpeg';

/** Run a little PHP on the copy and answer its last line. */
export function php(code) { return wp(['eval', code]); }

/** The newest application post ID with this title, or 0. */
export function applicationId(title) {
  return Number(php(`$ids = get_posts( array( 'post_type' => 'wpcpm_inst_app', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'title' => ${JSON.stringify(title)}, 'suppress_filters' => false ) ); echo $ids ? (int) $ids[0] : 0;`)) || 0;
}

/** The Airtable record ID (invented by the stand-in) of an approved invented school. */
export function recordOf(name) {
  const id = applicationId(name);
  return id ? php(`echo (string) get_post_meta( ${id}, WPCPM_Institution_Application::META_RECORD, true );`) : '';
}

/** The login of the account holding an invented address. */
export function loginOf(email) {
  return php(`$u = get_user_by( 'email', ${JSON.stringify(email)} ); echo $u ? $u->user_login : '';`);
}

/** The files in the plugin's private uploads folder (where uploaded agreements are kept), by path under wp-content. */
export function privateFiles() {
  const out = php(`$b = class_exists( 'WPCPM_Private_Files' ) ? WPCPM_Private_Files::base() : ''; $o = array(); if ( $b && is_dir( $b ) ) { foreach ( new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $b, FilesystemIterator::SKIP_DOTS ) ) as $f ) { if ( $f->isFile() && 0 === strpos( $f->getPathname(), WP_CONTENT_DIR . '/' ) ) { $o[] = substr( $f->getPathname(), strlen( WP_CONTENT_DIR ) + 1 ); } } } echo wp_json_encode( $o );`);
  return out.startsWith('[') ? JSON.parse(out) : [];
}

/** The address of the newest Program update an Administrator reads, as a path on the copy, or ''. */
export function updatesPath() {
  // Read as the copy's Administrator: an update at the Administrators level is not listed for nobody.
  const out = wp(['eval', `if ( ! class_exists( 'WPCPM_Updates' ) ) { echo ''; return; } $p = WPCPM_Updates::posts( 1, 'administrator' ); echo $p ? wp_make_link_relative( get_permalink( $p[0] ) ) : '';`, `--user=${process.env.WPCPM_UI_LOGIN || 'wpcredits-local'}`]);
  return out.startsWith('/') ? out : '';
}

/** The user ID of an invented student, by address, or ''. */
export function userIdOf(email) {
  return php(`$u = get_user_by( 'email', ${JSON.stringify(email)} ); echo $u ? (int) $u->ID : '';`);
}

/** The address of the newest update institutions read, as a path on the copy, or '' (read as the design school's representative). */
export function institutionUpdatesPath() {
  const login = loginOf(DESIGN.email);
  if (!login) return '';
  const out = wp(['eval', `if ( ! class_exists( 'WPCPM_Updates' ) ) { echo ''; return; } $p = WPCPM_Updates::posts( 1, 'institution' ); echo $p ? wp_make_link_relative( get_permalink( $p[0] ) ) : '';`, `--user=${login}`]);
  return out.startsWith('/') ? out : '';
}

/** The newest enrollment list (an import batch) of an invented school, by its record, or ''. */
export function batchOf(record) {
  if (!record) return '';
  const out = php(`$ids = get_posts( array( 'post_type' => WPCPM_Institution_Import::POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'DESC', 'meta_key' => WPCPM_Institution_Import::META_INSTITUTION, 'meta_value' => ${JSON.stringify(record)} ) ); echo $ids ? (int) $ids[0] : '';`);
  return /^\d+$/.test(out) ? out : '';
}

/** The values the scenes' tokens stand for: {DESIGN_RECORD}, {DESIGN_LOGIN}, {UNIVERSITY_LOGIN}, {NOAGREEMENT_LOGIN}, {DESIGN_BATCH}, {DESIGN_TUTOR_LOGIN}, {DESIGN_STUDENT}, {UPDATES_PATH}, {INSTITUTION_UPDATES_PATH}, {INSTITUTION_UPDATES_VIEWING_PATH}. */
export function lookups() {
  summaries.clear();
  const update = institutionUpdatesPath();
  const record = recordOf(DESIGN.name);
  return {
    INSTITUTION_UPDATES_VIEWING_PATH: update && record ? update + (update.includes('?') ? '&' : '?') + 'wpcpm_institution_view=' + encodeURIComponent(record) : '', DESIGN_RECORD: record, DESIGN_LOGIN: loginOf(DESIGN.email), DESIGN_BATCH: batchOf(record), DESIGN_TUTOR_LOGIN: loginOf(TUTOR.email), // The import makes student records, not accounts: an invented student has no user ID, and the address before the frame is
    // then asked with 0, which still names a student (the Students view must be drawn, with no card).
    DESIGN_STUDENT: userIdOf('drew.example@example.org') || '0', UPDATES_PATH: updatesPath(), INSTITUTION_UPDATES_PATH: update,
    // Gate B: the design school's invited student (an account, no sign-in), each agreement state's school by its
    // representative and its record, and the long-word school with its waiting list of thirteen lines.
    DESIGN_STUDENT_INVITED: userIdOf('sky.example@example.org'),
    // Fix round 1: a school's login and record are answered only while its agreement is in the state its scenes are named
    // for, and the long-word school's only once its long words are made, so a seed step that failed makes its scenes skip
    // ("an invented item it needs is not on the copy") instead of saving pictures under a state's name they do not show.
    NOAGREEMENT_LOGIN: inState(NOAGREEMENT, 'none', () => loginOf(NOAGREEMENT.email)), NOAGREEMENT_RECORD: inState(NOAGREEMENT, 'none', () => recordOf(NOAGREEMENT.name)),
    UNIVERSITY_LOGIN: inState(UNIVERSITY, 'submitted', () => loginOf(UNIVERSITY.email)), UNIVERSITY_RECORD: inState(UNIVERSITY, 'submitted', () => recordOf(UNIVERSITY.name)),
    GENERATED_LOGIN: inState(GENERATED, 'generated', () => loginOf(GENERATED.email)), GENERATED_RECORD: inState(GENERATED, 'generated', () => recordOf(GENERATED.name)),
    RETURNED_LOGIN: inState(RETURNED, 'returned', () => loginOf(RETURNED.email)), RETURNED_RECORD: inState(RETURNED, 'returned', () => recordOf(RETURNED.name)),
    REVOKED_LOGIN: inState(REVOKED, 'revoked', () => loginOf(REVOKED.email)), REVOKED_RECORD: inState(REVOKED, 'revoked', () => recordOf(REVOKED.name)),
    INSTITUTE_LOGIN: inState(INSTITUTE, 'newer-copy', () => loginOf(INSTITUTE.email)), INSTITUTE_RECORD: inState(INSTITUTE, 'newer-copy', () => recordOf(INSTITUTE.name)),
    LONG_LOGIN: longWords(() => loginOf(LONGWORD.email)), LONG_RECORD: longWords(() => recordOf(LONGWORD.name)), LONG_BATCH: longWords(() => batchOf(recordOf(LONGWORD.name))),
    // 1.123.98: the school whose only person is its contact with no account, by its index row (it has no application).
    PEOPLE_ZERO_RECORD: people0Record() };
}

/** The record of the settled school whose only person is its contact (PEOPLE0), while nobody holds its contact's address; or ''. */
export function people0Record() {
  const out = php(`$o = ''; foreach ( WPCPM_Institutions_Index::rows() as $id => $r ) { if ( is_array( $r ) && ${JSON.stringify(PEOPLE0.name)} === ( $r['name'] ?? '' ) && WPCPM_Institution_Agreement::is_settled( (string) $id ) && ! get_user_by( 'email', ${JSON.stringify(PEOPLE0.email)} ) ) { $o = (string) $id; } } echo $o;`);
  return /^rec[A-Za-z0-9]{14}$/.test(out) ? out : '';
}

// The summaries lookups() reads, once a call (each read is a WP-CLI process).
const summaries = new Map();

/**
 * What `read` answers, while the school's agreement summary is in `state` ('newer-copy': accepted, with a newer copy
 * waiting, its `pending_id` set); '' otherwise.
 */
function inState(school, state, read) {
  const key = school.name;
  if (!summaries.has(key)) summaries.set(key, [agreementFact(key, 'state'), agreementFact(key, 'pending_id')]);
  const [now, pending] = summaries.get(key);
  const holds = 'newer-copy' === state ? now === 'accepted' && /^[1-9]\d*$/.test(pending) : now === state;
  return holds ? read() : '';
}

/**
 * What `read` answers, once the long-word school is settled with its long words made: the long-named student on its roster
 * (LONG_STUDENT.email) and the long-named tutor on its list (LONG_TUTOR.email); '' otherwise.
 */
function longWords(read) {
  const record = recordOf(LONGWORD.name);
  if (!record || agreementFact(LONGWORD.name, 'state') !== 'accepted') return '';
  const made = php(`$r = ${JSON.stringify(record)}; $student = in_array( ${JSON.stringify(LONG_STUDENT.email)}, array_column( WPCPM_Roster_Index::rows( $r ), 'email' ), true ); $tutor = in_array( ${JSON.stringify(LONG_TUTOR.email)}, array_column( WPCPM_Institution_Tutors::list_for( $r ), 'email' ), true ); echo $student && $tutor ? 'yes' : 'no';`);
  return made === 'yes' ? read() : '';
}

/**
 * Show a box the page draws inside a closed fold or a Decide panel (the panel's opener pressed where the script hid it),
 * so the seed can type into it on either build: the folds around it opened, the panel's opener pressed when it is shown.
 */
async function reveal(page, selector) {
  const el = page.locator(selector).first();
  if (!(await el.count())) throw new Error(`${selector} is not on the page`);
  await el.evaluate(e => { for (let d = e.closest('details'); d; d = d.parentElement && d.parentElement.closest('details')) d.open = true; });
  const panel = await el.evaluate(e => { const p = e.closest('[data-wpcpm-panel]'); return p && p.id ? p.id : ''; });
  if (panel && !(await el.isVisible())) {
    const opener = page.locator(`button[aria-controls="${panel}"]`).first();
    if (await opener.count() && await opener.isVisible()) { await opener.click(); await page.waitForTimeout(250); }
  }
  return el;
}

/** Press a form's button and wait for the page the post lands on. */
async function post(page, button) {
  page.once('dialog', d => d.accept()); // the screen's own question, on an invented item
  await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle', timeout: 60000 }), button.click()]);
}

/** A seed step that leaves one state: made, or not made with why, and the seed goes on (a scene that needs it reports it). */
async function soft(state, step) {
  try { await step(); console.log(`  seed: made ${state}`); } catch (e) { console.log(`  seed: not made ${state}: ${String(e.message || e).split('\n')[0]}`); }
}

/** The copy's rate-limit rows cleared, so one more invented application from this address is taken (WPCPM_Form_Guard::PER_HOUR). */
function clearCeiling() {
  if (php(`WPCPM_Ceiling::delete_all(); echo 'cleared';`) !== 'cleared') throw new Error('the rate-limit rows were not cleared');
}

/** An agreement fact of an invented school's summary (`state`, `pending_id`, `agreement_id`). */
function agreementFact(name, key) {
  const record = recordOf(name);
  return record ? php(`$s = WPCPM_Institution_Agreement::summary( ${JSON.stringify(record)} ); echo isset( $s[${JSON.stringify(key)}] ) ? (string) $s[${JSON.stringify(key)}] : '';`) : '';
}

/** Open a card's own fold unless it is drawn open already (a press on an open one would close it). */
async function openCard(page, anchor) {
  const head = page.locator(`${anchor} details > summary`).first();
  if (await head.count() && !(await head.evaluate(s => s.parentElement.open))) await head.click();
}

function keptLink(test) {
  const lines = existsSync(LINKS) ? readFileSync(LINKS, 'utf8').trim().split('\n').reverse() : [];
  return lines.find(test) || '';
}

/** A school applies through the public form and confirms its address with the link in its acknowledgement. */
export async function applySchool(newContext, BASE, s) {
  const context = await newContext({ signedIn: false });
  const page = await context.newPage();
  await page.goto(BASE + '/institution-application/', { waitUntil: 'networkidle' });
  const f = (label, value) => page.getByLabel(label, { exact: false }).first().fill(value);
  await f('Name of your institution', s.name);
  await page.locator('select[name^="wpcpm_application"]').first().selectOption({ label: s.country || 'Canada' }, { force: true });
  await f('City', s.city);
  await f('Website', s.website);
  await f('Name of the person we should contact', s.contact);
  await f('Their email address', s.email);
  await f('Their role at the institution', s.role || 'Internship coordinator');
  await f('Department or faculty', s.department || 'Faculty of Computer Science');
  await page.getByLabel('Fixed duration (e.g. semester-based)').check();
  await f('Roughly how many students may be interested?', String(s.students || 25));
  await page.getByLabel('English', { exact: true }).check();
  if (s.signer) { await f('Who will sign the agreement for the institution?', s.signer); await f('Their title', s.signerTitle || 'Dean of the Faculty'); }
  await f('Why are you interested in offering WordPress Credits to your students?', 'Our students build websites in their final year, and we want them to learn by contributing to an open source project with a mentor beside them. Credits for that work would fit our internship course.');
  await page.getByLabel('I confirm I have read the WordPress.org privacy policy', { exact: false }).check();
  await page.waitForTimeout(7000); // the form refuses a submission made faster than a person could type it
  await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: /send|submit|apply/i }).first().click()]);
  const id = applicationId(s.name);
  if (!id) throw new Error(`the application from ${s.name} was not stored`);
  const link = keptLink(l => l.includes('wpcpm_apply_verify') && l.includes(`app=${id}`));
  if (!link.startsWith(BASE + '/')) throw new Error('no confirmation link was kept for the application');
  await page.goto(link.replace(/&amp;/g, '&'), { waitUntil: 'networkidle' });
  await context.close();
  if (php(`echo get_post_meta( ${id}, WPCPM_Institution_Application::META_VERIFIED, true ) ? 'yes' : 'no';`) !== 'yes') throw new Error('the address is still not confirmed');
  return id;
}

/** The Administrator approves an application on the Overview. */
export async function approveSchool(newContext, BASE, name) {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/administrator-dashboard/', { waitUntil: 'networkidle' });
  await openCard(page, '#wpcpm-applications');
  const approve = page.getByRole('button', { name: 'Approve', exact: true });
  const item = page.locator('#wpcpm-applications').locator('article, li, div', { hasText: name }).filter({ has: approve }).last();
  page.once('dialog', d => d.accept());
  await Promise.all([page.waitForLoadState('networkidle'), item.getByRole('button', { name: 'Approve', exact: true }).click()]);
  const said = await page.getByText('The application is approved.', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!said) throw new Error(`approving ${name} did not say it was approved`);
  return recordOf(name);
}

/** The school's own account makes the template agreement, saves it as a PDF and uploads it as signed. */
export async function uploadAgreement(newContext, BASE, login, pdfPath) {
  const context = await newContext({ login });
  await context.addInitScript(() => { window.print = () => {}; }); // the agreement page opens the print dialog by itself
  const page = await context.newPage();
  // The Agreement view on the frame's build; a build before the frame reads no view and draws the whole page.
  const AGREEMENT = BASE + '/institution-dashboard/?wpcpm_view=agreement';
  await page.goto(AGREEMENT, { waitUntil: 'networkidle' });
  const [doc] = await Promise.all([
    context.waitForEvent('page', { timeout: 8000 }).catch(() => null),
    page.getByRole('button', { name: /^Generate the agreement/ }).first().click(),
  ]);
  const agreement = doc || page;
  await agreement.waitForLoadState('networkidle');
  await agreement.pdf({ path: pdfPath, format: 'A4', printBackground: true });
  if (doc) await doc.close(); else await page.goto(AGREEMENT, { waitUntil: 'networkidle' });
  await uploadSigned(page, pdfPath);
  const said = (await page.locator('main').innerText()).replace(/\s+/g, ' ');
  await context.close();
  return said;
}

/**
 * The upload form, by the names it posts (the same on both builds): the PDF, the program's template, the box that says it
 * is signed by somebody who can commit the institution, and Upload the signed agreement.
 */
async function uploadSigned(page, pdfPath) {
  const file = await reveal(page, 'input[name="wpcpm_agreement_file"]');
  const form = page.locator('form', { has: page.locator('input[name="wpcpm_agreement_file"]') }).first();
  await file.setInputFiles(pdfPath);
  await form.locator('input[name="wpcpm_agreement_kind"][value="template"]').check({ force: true });
  await form.locator('input[type="checkbox"][name="wpcpm_agreement_signed"]').check({ force: true });
  await Promise.all([page.waitForLoadState('networkidle'), form.getByRole('button', { name: /^Upload the signed agreement/ }).first().click()]);
  await page.waitForLoadState('networkidle');
}

/** Gate B: the school's own account generates the template agreement and uploads nothing (the agreement stays "generated"). */
export async function generateOnly(newContext, BASE, login) {
  const context = await newContext({ login });
  await context.addInitScript(() => { window.print = () => {}; });
  const page = await context.newPage();
  await page.goto(BASE + '/institution-dashboard/?wpcpm_view=agreement', { waitUntil: 'networkidle' });
  const [doc] = await Promise.all([
    context.waitForEvent('page', { timeout: 8000 }).catch(() => null),
    page.getByRole('button', { name: /^Generate the agreement/ }).first().click(),
  ]);
  await (doc || page).waitForLoadState('networkidle');
  await context.close();
  if (agreementFact(GENERATED.name, 'state') !== 'generated') throw new Error(`the agreement of ${GENERATED.name} is not generated`);
}

/** Gate B: the Administrator returns a school's uploaded agreement with a note, on the Overview's Collaboration Agreements card. */
export async function returnAgreement(newContext, BASE, name) {
  const pending = agreementFact(name, 'pending_id');
  if (!/^\d+$/.test(pending) || pending === '0') throw new Error(`no uploaded agreement of ${name} waits`);
  const action = php('echo WPCPM_Institution_Agreement::ACTION_RETURN;');
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/administrator-dashboard/', { waitUntil: 'networkidle' });
  await openCard(page, '#wpcpm-agreements');
  const form = page.locator('form', { has: page.locator(`input[name="wpcpm_agreement_post"][value="${pending}"]`) }).filter({ has: page.locator(`input[name="action"][value="${action}"]`) }).first();
  const note = await reveal(page, `form:has(input[name="wpcpm_agreement_post"][value="${pending}"]):has(input[name="action"][value="${action}"]) textarea[name="wpcpm_agreement_note"]`);
  await note.fill('Example note: the signature page is missing. Please scan all five pages, the signed one included, and upload the whole document again.');
  await post(page, form.locator('[type="submit"]').first());
  await context.close();
  if (agreementFact(name, 'state') !== 'returned') throw new Error(`the agreement of ${name} was not returned`);
}

/** Gate B: the Administrator revokes a school's accepted agreement, with its note, on Institutions > Agreements. */
export async function revokeAgreement(newContext, BASE, name) {
  const accepted = agreementFact(name, 'agreement_id');
  if (!/^\d+$/.test(accepted) || accepted === '0') throw new Error(`no accepted agreement of ${name}`);
  const action = php('echo WPCPM_Institution_Agreement::ACTION_REVOKE;');
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/wp-admin/admin.php?page=wpcpm-institutions&tab=agreements', { waitUntil: 'networkidle' });
  const sel = `form:has(input[name="wpcpm_agreement_post"][value="${accepted}"]):has(input[name="action"][value="${action}"])`;
  const note = await reveal(page, `${sel} textarea[name="wpcpm_agreement_note"]`);
  await note.fill('Example note: revoked for the picture rig, the signer has left the faculty and a new agreement is on its way.');
  await post(page, page.locator(sel).first().locator('[type="submit"]').first());
  await context.close();
  if (agreementFact(name, 'state') !== 'revoked') throw new Error(`the agreement of ${name} was not revoked`);
}

/** Gate B: a settled school's own account uploads a newer signed copy, which waits for review beside the one in force. */
export async function uploadNewerCopy(newContext, BASE, school) {
  const pdf = join(WORK, `${school.website.split('.')[0]}-agreement.pdf`);
  if (!existsSync(pdf)) throw new Error(`no agreement PDF of ${school.name} in the work folder`);
  const context = await newContext({ login: loginOf(school.email) });
  const page = await context.newPage();
  await page.goto(BASE + '/institution-dashboard/?wpcpm_view=agreement', { waitUntil: 'networkidle' });
  await uploadSigned(page, pdf);
  await context.close();
  const pending = agreementFact(school.name, 'pending_id');
  if (agreementFact(school.name, 'state') !== 'accepted' || !pending || pending === '0') throw new Error(`no newer copy of ${school.name} waits beside the accepted one`);
}

/** Gate B: a school applied, approved and its agreement uploaded by its own account (it waits for review). */
async function applyAndUpload(newContext, BASE, school) {
  clearCeiling();
  await applySchool(newContext, BASE, school);
  await approveSchool(newContext, BASE, school.name);
  const up = await uploadAgreement(newContext, BASE, loginOf(school.email), join(WORK, `${school.website.split('.')[0]}-agreement.pdf`));
  if (!up.includes('The signed agreement is uploaded')) throw new Error(`the agreement upload of ${school.name} was not taken`);
}

/** The Administrator accepts the school's uploaded agreement on the Overview's Collaboration Agreements card. */
export async function acceptAgreement(newContext, BASE, name) {
  // The named school's waiting copy, by its post: never the first row, which after a failed step could be another school's.
  const pending = agreementFact(name, 'pending_id');
  if (!/^[1-9]\d*$/.test(pending)) throw new Error(`no uploaded agreement of ${name} waits`);
  const action = php('echo WPCPM_Institution_Agreement::ACTION_ACCEPT;');
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/administrator-dashboard/', { waitUntil: 'networkidle' });
  await openCard(page, '#wpcpm-agreements');
  const form = page.locator(`#wpcpm-agreements form:has(input[name="wpcpm_agreement_post"][value="${pending}"]):has(input[name="action"][value="${action}"])`).first();
  if (!(await form.count())) { await context.close(); throw new Error(`the Accept it of ${name}'s agreement is not on the card`); }
  await post(page, form.locator('[type="submit"]').first());
  await context.close();
  if (agreementFact(name, 'state') !== 'accepted') throw new Error(`the agreement of ${name} was not accepted`);
}

/** Switch one of the plugin's settings on the copy. The placeholder token is taken off first, never written. */
export function setSetting(key, value) {
  if (!/^[a-z0-9_]+$/.test(key)) throw new Error('bad setting key');
  return php(`remove_all_filters( 'option_wpcpm_settings' ); $o = get_option( 'wpcpm_settings', array() ); if ( ! is_array( $o ) ) { $o = array(); } $o[${JSON.stringify(key)}] = ${JSON.stringify(value)}; update_option( 'wpcpm_settings', $o ); echo 'set';`);
}

/** A person applies to mentor through the public form, and confirms the address. */
export async function applyMentor(newContext, BASE, m) {
  const context = await newContext({ signedIn: false });
  const page = await context.newPage();
  await page.goto(BASE + '/become-a-mentor/', { waitUntil: 'networkidle' });
  const f = (label, value) => page.getByLabel(label, { exact: false }).first().fill(value);
  await f('Full name', m.name);
  await f('Email', m.email);
  await f('WordPress.org profile URL', m.profile);
  await page.locator('select[name="wpcpm_mentor_application[country]"]').selectOption({ label: m.country || 'Canada' }, { force: true });
  await page.locator('input[name="wpcpm_mentor_application[teacher]"][value="No"]').check();
  for (const l of m.languages || ['English']) await page.locator(`input[name="wpcpm_mentor_application[languages][]"][value="${l}"]`).check();
  for (const e of m.expertise || ['Accessibility', 'Core']) await page.locator(`input[name="wpcpm_mentor_application[expertise][]"][value="${e}"]`).check();
  await f('Available hours per week to mentor students', String(m.hours || 4));
  await page.locator('input[name="wpcpm_mentor_application[sponsored]"][value="No"]').check();
  const wants = page.locator('input[name="wpcpm_mentor_application[wants_sponsor]"][value="No"]');
  if (await wants.count()) await wants.check();
  await page.locator('input[name="wpcpm_mentor_application[gpl]"]').check();
  await page.locator('input[name="wpcpm_mentor_application[consent]"]').check();
  await page.waitForTimeout(7000);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('main form').getByRole('button').last().click()]);
  const id = Number(php(`$ids = get_posts( array( 'post_type' => 'wpcpm_ment_app', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'title' => ${JSON.stringify(m.name)}, 'suppress_filters' => false ) ); echo $ids ? (int) $ids[0] : 0;`));
  if (!id) throw new Error('the mentor application was not stored');
  const link = keptLink(l => l.includes('wpcpm_mentor_apply_verify'));
  if (!link.startsWith(BASE + '/')) throw new Error('no confirmation link was kept for the mentor application');
  await page.goto(link.replace(/&amp;/g, '&'), { waitUntil: 'networkidle' });
  await context.close();
  return id;
}

/**
 * The Administrator checks a list of invented students for a school on its Institution Dashboard (Enroll students), by the
 * names the form posts (the same on both builds): the list waits, checked, with Create these student records. Answers the
 * context and the page, open on the check; with `close`, closes them and answers nothing (the list left waiting).
 */
export async function checkList(newContext, BASE, schoolName, students, { program = 'WordPress Credits Program 150h', start, end, close = false } = {}) {
  const record = recordOf(schoolName);
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=enroll&wpcpm_institution_view=${record}`, { waitUntil: 'networkidle' });
  // The form is the Enroll students view's card (#wpcpm-import); a build that folds it opens the fold first.
  const sum = page.locator('#wpcpm-import summary', { hasText: 'Enroll students' }).first();
  if (await sum.count()) { if (!(await sum.evaluate(s => s.parentElement.open))) await sum.click(); }
  const det = page.locator('#wpcpm-import');
  if (!(await det.count())) { await context.close(); throw new Error(`the Enroll students card (#wpcpm-import) is not on the page for ${schoolName}`); }
  await det.locator('select[name="program"]').selectOption({ label: program }, { force: true });
  await det.locator('input[name="start"]').fill(start);
  if (end) await det.locator('input[name="end"]').fill(end);
  await det.locator('input[name="notified"]').check({ force: true });
  await det.locator('textarea[name="paste"]').fill('name,email\n' + students.map(s => `${s.name},${s.email}`).join('\n'));
  await Promise.all([page.waitForLoadState('networkidle'), det.getByRole('button', { name: /^Check the list/ }).first().click()]);
  await page.getByRole('button', { name: /^Create these student records/ }).first().waitFor({ timeout: 20000 });
  if (!close) return { context, page };
  await context.close();
  return null;
}

/** The Administrator enrolls invented students for a school on its Institution Dashboard (Enroll students). */
export async function enrollStudents(newContext, BASE, schoolName, students, { program = 'WordPress Credits Program 150h', start, end } = {}) {
  const { context, page } = await checkList(newContext, BASE, schoolName, students, { program, start, end });
  const create = page.getByRole('button', { name: /^Create these student records/ }).first();
  page.once('dialog', d => d.accept()); // the screen's own question, on invented students
  await create.click();
  await page.getByText(/^Done: \d+ students? created/).first().waitFor({ timeout: 30000 });
  await page.waitForLoadState('networkidle');
  const said = (await page.locator('main').innerText()).replace(/\s+/g, ' ');
  await context.close();
  return said;
}

/** The Administrator drafts a semester report on Institutions > Semester reports, Draft now. */
export async function draftReport(newContext, BASE, schoolName, semester) {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/wp-admin/admin.php?page=wpcpm-institutions&tab=reports', { waitUntil: 'networkidle' });
  const form = page.locator('form', { has: page.getByRole('button', { name: 'Draft now' }) }).first();
  const box = form.getByRole('combobox').first();
  await box.click(); await box.fill(schoolName.split(' ').slice(0, 2).join(' '));
  await page.getByRole('option', { name: schoolName }).first().click();
  await form.locator('select:not(#wpcpm-draft-institution)').selectOption({ label: semester });
  await Promise.all([page.waitForLoadState('networkidle'), form.getByRole('button', { name: 'Draft now' }).click()]);
  const ok = await page.getByText('The draft is ready for review.', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!ok) throw new Error('the report was not drafted');
}

/** The Invite a colleague form, by the names it posts (the same on both builds): its address and its level. */
async function inviteForm(page) {
  const form = page.locator('form', { has: page.locator('input[name="email"]') }).filter({ has: page.locator('input[name="level"]') }).first();
  if (!(await form.count())) throw new Error('the Invite a colleague form is not on Your institution');
  await reveal(page, 'form:has(input[name="level"]) input[name="email"]');
  return form;
}

/** The Add a tutor form, by the names it posts (the same on both builds): the tutor's name and address. */
async function tutorForm(page) {
  const form = page.locator('form', { has: page.locator('input[name="name"]') }).filter({ has: page.locator('input[name="email"]') }).first();
  if (!(await form.count())) throw new Error('the Add a tutor form is not on Your institution');
  await reveal(page, 'form:has(input[name="name"]) input[name="email"]');
  return form;
}

/** The school's own account invites a colleague and adds a tutor on its Institution Dashboard. */
export async function inviteAndTutor(newContext, BASE, login) {
  const context = await newContext({ login });
  const page = await context.newPage();
  await page.goto(BASE + '/institution-dashboard/?wpcpm_view=institution', { waitUntil: 'networkidle' });
  const invite = await inviteForm(page);
  await invite.locator('input[name="email"]').fill('pat.example@example.org');
  await Promise.all([page.waitForLoadState('networkidle'), invite.getByRole('button', { name: /^Send invitation/ }).first().click()]);
  await page.getByText('The invitation is on its way', { exact: false }).first().waitFor({ timeout: 20000 });
  const tutor = await tutorForm(page);
  await tutor.locator('input[name="name"]').fill('Lee Example');
  await tutor.locator('input[name="email"]').fill('lee.example@example.org');
  await Promise.all([page.waitForLoadState('networkidle'), tutor.getByRole('button', { name: /^Add\b/ }).first().click()]);
  await page.getByText('The tutor was added to the list.', { exact: false }).first().waitFor({ timeout: 20000 });
  await context.close();
}

/**
 * A tutor listed through a school's own Add a tutor form, as its representative, on Your institution (the list holds no
 * account). Idempotent: an address already listed is not added again. Fails loudly when the form is not on the page.
 */
export async function addTutorAs(newContext, BASE, school, tutor) {
  const record = recordOf(school.name);
  const login = loginOf(school.email);
  if (!record || !login) throw new Error(`${school.name} and its representative must be seeded before its tutor`);
  if (php(`echo in_array( ${JSON.stringify(tutor.email)}, array_column( WPCPM_Institution_Tutors::list_for( ${JSON.stringify(record)} ), 'email' ), true ) ? 'yes' : 'no';`) === 'yes') return;
  const context = await newContext({ login });
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=institution`, { waitUntil: 'networkidle' });
  const form = await tutorForm(page).catch(async e => { await context.close(); throw e; });
  await form.locator('input[name="name"]').fill(tutor.name);
  await form.locator('input[name="email"]').fill(tutor.email);
  await Promise.all([page.waitForLoadState('networkidle'), form.getByRole('button', { name: /^Add\b/ }).first().click()]);
  const said = await page.getByText('The tutor was added to the list.', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!said) throw new Error('the Add a tutor form did not say the tutor was added');
}

/**
 * One invented tutor at the design school (TUTOR): listed through the school's own Add a tutor form, as its
 * representative, on Your institution; then given a tutor-level account, because the list holds no account and the
 * tutor scenes need somebody to sign in as. Idempotent: a second seed run finds the address listed and the account
 * attached and adds nothing. Fails loudly when the form is not on the page or the account cannot be attached.
 */
export async function addTutor(newContext, BASE) {
  const record = recordOf(DESIGN.name);
  const login = loginOf(DESIGN.email);
  if (!record || !login) throw new Error('the design school and its representative must be seeded before its tutor');
  await addTutorAs(newContext, BASE, DESIGN, TUTOR);
  // The account, end to end through the site's own forms: the representative invites the tutor at the Tutor level
  // (Invite a colleague), and the invitation's accept address, kept by the rig's must-use plugin from the swallowed mail,
  // is opened and Accept invitation pressed by a visitor with no account. Skipped when the membership is there already.
  const member = () => php(`$u = get_user_by( 'email', ${JSON.stringify(TUTOR.email)} ); echo ( $u && WPCPM_Institution_Members::institution_of( $u ) === ${JSON.stringify(record)} && WPCPM_Institution_Members::LEVEL_TUTOR === WPCPM_Institution_Members::level_of( $u ) ) ? 'yes' : 'no';`) === 'yes';
  if (member()) return;
  const routeSaid = await acceptTutorInvitation(newContext, BASE, login).then(() => '', e => String(e.message || e));
  if (!routeSaid && member()) { console.log('  seed: the tutor accepted a Tutor-level invitation through the accept address'); return; }
  console.log(`  seed: the invitation route did not make the tutor (${routeSaid || 'no membership after the press'}): the written route`);
  // The written route, as the accept route makes the account (WPCPM_Roles::insert_user with ROLE_INSTITUTION, then attach
  // with the inviter as actor): created when there is none, and attached at the tutor level to this school when it is not a
  // member of it at that level already. Never a copied role.
  const out = php(`if ( ! defined( 'WPCPM_Roles::ROLE_INSTITUTION' ) ) { echo 'error: no institution role'; return; } $rep = get_user_by( 'email', ${JSON.stringify(DESIGN.email)} ); if ( ! $rep ) { echo 'error: no representative'; return; } $record = ${JSON.stringify(record)}; $u = get_user_by( 'email', ${JSON.stringify(TUTOR.email)} ); if ( ! $u ) { $id = WPCPM_Roles::insert_user( array( 'user_login' => 'tutor-example', 'user_email' => ${JSON.stringify(TUTOR.email)}, 'user_pass' => wp_generate_password( 24, true, true ), 'display_name' => ${JSON.stringify(TUTOR.name)}, 'nickname' => 'tutor-example', 'role' => WPCPM_Roles::ROLE_INSTITUTION ) ); if ( is_wp_error( $id ) ) { echo 'error: ' . $id->get_error_message(); return; } $u = get_user_by( 'id', $id ); } if ( WPCPM_Institution_Members::institution_of( $u ) === $record && WPCPM_Institution_Members::LEVEL_TUTOR === WPCPM_Institution_Members::level_of( $u ) ) { echo 'already'; return; } $r = WPCPM_Institution_Members::attach( $u->ID, $record, WPCPM_Institution_Members::HOW_INVITED, $rep->ID, 0, WPCPM_Institution_Members::LEVEL_TUTOR ); echo is_wp_error( $r ) ? 'error: ' . $r->get_error_message() : 'attached';`);
  if (out !== 'attached' && out !== 'already') throw new Error(`the tutor's account was not made: ${out}`);
  const tutorLogin = loginOf(TUTOR.email);
  if (!tutorLogin) throw new Error('the tutor has no account after the seed');
}

/** The representative invites the tutor at the Tutor level; the tutor, signed out, opens the kept accept address and accepts. */
async function acceptTutorInvitation(newContext, BASE, login) {
  const context = await newContext({ login });
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=institution`, { waitUntil: 'networkidle' });
  const invite = await inviteForm(page).catch(async e => { await context.close(); throw e; });
  await invite.locator('input[name="email"]').fill(TUTOR.email);
  const tutorLevel = invite.locator('input[name="level"][value="tutor"]');
  if (!(await tutorLevel.count())) { await context.close(); throw new Error('the invitation has no Tutor level'); }
  await tutorLevel.check({ force: true });
  await Promise.all([page.waitForLoadState('networkidle'), invite.getByRole('button', { name: /^Send invitation/ }).first().click()]);
  const sent = await page.getByText('The invitation is on its way', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!sent) throw new Error('the invitation did not say it was on its way');
  const link = keptLink(l => l.includes('wpcpm_accept_invite'));
  if (!link.startsWith(BASE + '/')) throw new Error('no accept address was kept for the invitation');
  const visitor = await newContext({ signedIn: false });
  const p = await visitor.newPage();
  await p.goto(link.replace(/&amp;/g, '&'), { waitUntil: 'networkidle' });
  await Promise.all([p.waitForLoadState('networkidle'), p.getByRole('button', { name: 'Accept invitation' }).click()]);
  await visitor.close();
}

/**
 * Gate B: the rig's own Administrator follows the design school up, through Change who follows it up on Your institution
 * (the Assigned Administrator), so the Overview draws its contact card and the menu its Write to the program.
 */
export async function assignAdministrator(newContext, BASE) {
  const record = recordOf(DESIGN.name);
  const admin = php(`$u = get_user_by( 'login', ${JSON.stringify(ADMIN_LOGIN)} ); echo $u ? (int) $u->ID : 0;`);
  if (!record || !/^[1-9]\d*$/.test(admin)) throw new Error('the design school or the rig\'s Administrator is not on the copy');
  const stored = () => php(`echo (int) WPCPM_Institution_Assignment::stored_of( ${JSON.stringify(record)} );`);
  if (stored() === admin) return;
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=institution&wpcpm_institution_view=${record}`, { waitUntil: 'networkidle' });
  const select = await reveal(page, 'select[name="wpcpm_assigned"]');
  await select.selectOption(admin, { force: true });
  await post(page, page.locator('form', { has: page.locator('select[name="wpcpm_assigned"]') }).first().locator('[type="submit"]').first());
  await context.close();
  if (stored() !== admin) throw new Error('the Assigned Administrator was not saved');
}

/** The open request to correct a school's details, by its record, or ''. */
function openDetails(record) {
  const id = php(`echo (int) WPCPM_Institution_Request::open_details_for( ${JSON.stringify(record)} );`);
  return /^[1-9]\d*$/.test(id) ? id : '';
}

/** A school's representative asks to correct details (Ask to correct these): each box by the name it posts, then the form's button. */
async function askDetails(newContext, BASE, school, values) {
  const context = await newContext({ login: loginOf(school.email) });
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=institution`, { waitUntil: 'networkidle' });
  for (const [key, value] of Object.entries(values)) await (await reveal(page, `input[name="wpcpm_details_${key}"]`)).fill(value);
  const first = Object.keys(values)[0];
  await post(page, page.locator('form', { has: page.locator(`input[name="wpcpm_details_${first}"]`) }).first().locator('[type="submit"]').first());
  await context.close();
  if (!openDetails(recordOf(school.name))) throw new Error(`the request to correct the details of ${school.name} is not waiting`);
}

/**
 * Gate B: a request to correct the design school's details, asked by its representative (Ask to correct these, the City) and
 * decided by an Administrator (Decline, with a note, on the Overview's Requests card), so "Good to know" draws the decided
 * row and the school's second group offers its form again. Decline and not Approve: approving writes the record to the
 * program records, which the copy refuses. Run after states(), whose request of two changes at the design school
 * (seed-states.php) would hide both while it waits: the representative withdraws it first (Withdraw it), and Example
 * Institute of Technology's representative asks for the same two changes at that school, so the Requests card still holds
 * a request of two changes waiting, now asked through the form.
 */
export async function askAndDecline(newContext, BASE) {
  const record = recordOf(DESIGN.name);
  const outcome = () => php(`$o = WPCPM_Institution_Request::latest_details_outcome( ${JSON.stringify(record)} ); echo isset( $o['state'] ) ? (string) $o['state'] : '';`);
  if (outcome() === 'declined' && !openDetails(record)) return;
  const waiting = openDetails(record);
  if (waiting) {
    const withdraw = php('echo WPCPM_Institution_Request::ACTION_DETAILS_WITHDRAW;');
    const context = await newContext({ login: loginOf(DESIGN.email) });
    const page = await context.newPage();
    await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=institution`, { waitUntil: 'networkidle' });
    const form = page.locator('form', { has: page.locator(`input[name="action"][value="${withdraw}"]`) }).filter({ has: page.locator(`input[name="request"][value="${waiting}"]`) }).first();
    if (!(await form.count())) { await context.close(); throw new Error('the waiting request\'s Withdraw it is not on Your institution'); }
    await post(page, form.locator('[type="submit"]').first());
    await context.close();
    if (openDetails(record)) throw new Error('the waiting request was not withdrawn');
  }
  await askDetails(newContext, BASE, DESIGN, { city: 'Exampleside' });
  const id = openDetails(record);
  if (!/^[1-9]\d*$/.test(id)) throw new Error('the representative\'s request to correct the details is not waiting');
  const context = await newContext();
  const admin = await context.newPage();
  await admin.goto(BASE + '/administrator-dashboard/', { waitUntil: 'networkidle' });
  await openCard(admin, '#wpcpm-requests');
  const form = `form:has(input[name="request"][value="${id}"]):has(textarea[name="wpcpm_request_note"])`;
  const note = await reveal(admin, `${form} textarea[name="wpcpm_request_note"]`);
  await note.fill('Example note: the city stays as the application gave it, so nothing was changed on the record.');
  await post(admin, admin.locator(form).first().locator('button[name="decision"][value="decline"]').first());
  await context.close();
  if (outcome() !== 'declined') throw new Error('the request to correct the details was not declined');
}

/** Gate B: the request of two changes the Requests card holds waiting (its Decline panel is a scene), asked at another school. */
export async function askElsewhere(newContext, BASE) {
  const other = recordOf(INSTITUTE.name);
  if (!other) throw new Error(`${INSTITUTE.name} is not on the copy`);
  if (!openDetails(other)) await askDetails(newContext, BASE, INSTITUTE, { website: 'https://www.examplecollegeofdesignandappliedartsnows.example.org', city: 'Examplestad Bay' });
}

/** Gate B: the Administrator saves the drafted January to June 2026 report once in its editor, so it has an earlier version (Revisions). */
export async function saveReport(newContext, BASE) {
  const record = recordOf(DESIGN.name);
  const revisions = () => php(`$p = WPCPM_Semester_Report::find( ${JSON.stringify(record)}, '2026-H1' ); echo $p ? count( wp_get_post_revisions( $p->ID ) ) : -1;`);
  if (Number(revisions()) > 0) return;
  const context = await newContext();
  const page = await context.newPage();
  // The address before the frame, which both builds draw as the report's editor.
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_report=2026-H1&wpcpm_institution_view=${record}`, { waitUntil: 'networkidle' });
  const form = page.locator('#wpcpm-report form', { has: page.locator('input[name="modified"]') }).first();
  if (!(await form.count())) { await context.close(); throw new Error('the report\'s editor is not on the page'); }
  await form.locator('textarea').first().fill('Example words for the picture rig: the students met every week, and each of them shipped a first patch before the term ended.');
  await post(page, form.getByRole('button', { name: /^Save the report/ }).first());
  await context.close();
  if (!(Number(revisions()) > 0)) throw new Error('the report has no earlier version after its save');
}

/** The Administrator writes an update institutions read in the post editor: the Updates category, the institutions' level, published. */
export async function addInstitutionUpdate(newContext, BASE) {
  const term = Number(php(`$t = get_term_by( 'slug', 'updates', 'category' ); echo $t ? (int) $t->term_id : 0;`));
  if (!term) throw new Error('the copy has no Updates category');
  // Read straight from the posts table: the plugin's access filter hides a post at the institutions' level from WP-CLI,
  // which reads as nobody.
  const idOf = `(int) $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT ID FROM {$GLOBALS['wpdb']->posts} WHERE post_type = 'post' AND post_status = 'publish' AND post_title = %s ORDER BY ID DESC LIMIT 1", ${JSON.stringify(UPDATE.title)} ) )`;
  const have = php(`echo ${idOf};`);
  if (Number(have)) return;
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/wp-admin/post-new.php', { waitUntil: 'networkidle' });
  await page.waitForFunction(() => window.wp && wp.data && wp.data.select('core/editor') && wp.data.select('core/editor').getCurrentPostId(), null, { timeout: 30000 });
  // The welcome guide closed; the title, the words and the category typed into the editor's own store.
  await page.evaluate(({ title, body, term }) => {
    try { wp.data.dispatch('core/preferences').set('core/edit-post', 'welcomeGuide', false); } catch (e) {}
    wp.data.dispatch('core/editor').editPost({ title, categories: [term] });
    wp.data.dispatch('core/block-editor').resetBlocks([wp.blocks.createBlock('core/paragraph', { content: body })]);
  }, { title: UPDATE.title, body: UPDATE.body, term });
  // The plugin's own Program access box, under the editor.
  await page.locator('#wpcpm-access-level-field').selectOption('wpcpm_institution');
  await page.locator('.editor-post-publish-panel__toggle').click();
  await page.locator('.editor-post-publish-panel .editor-post-publish-button').click();
  await page.locator('.post-publish-panel__postpublish').waitFor({ timeout: 30000 });
  await page.waitForFunction(() => !wp.data.select('core/edit-post').isSavingMetaBoxes(), null, { timeout: 30000 });
  await page.waitForTimeout(1000);
  await context.close();
  const level = php(`$id = ${idOf}; echo $id ? get_post_meta( $id, '_wpcpm_access_level', true ) . ' ' . ( has_category( ${term}, $id ) ? 'updates' : 'no-category' ) : 'none';`);
  if (level !== 'wpcpm_institution updates') throw new Error(`the update was not published at the institutions' level in Updates: ${level}`);
}

/**
 * Everything the pictures show, made in order: a school settled (applied, approved, agreement uploaded by
 * its own account and accepted) with two students enrolled, a report drafted, an invitation waiting and a
 * tutor listed; a school whose signed agreement waits for review; an application waiting; a mentor
 * application waiting. Setting switched: enrollment lists from institutions, on.
 */
export async function seed(newContext, BASE) {
  refuseUnlessCopy(BASE);
  mkdirSync(WORK, { recursive: true });
  const before = new Set(privateFiles());
  try {
    await make(newContext, BASE);
  } finally {
    // Recorded even when a step failed half way, so restore still finds what was made.
    const made = privateFiles().filter(f => !before.has(f));
    if (made.length) appendFileSync(CREATED, made.join('\n') + '\n', { mode: 0o600 });
  }
}

async function make(newContext, BASE) {
  await applySchool(newContext, BASE, DESIGN);
  await approveSchool(newContext, BASE, DESIGN.name);
  const said = await uploadAgreement(newContext, BASE, loginOf(DESIGN.email), join(WORK, 'design-agreement.pdf'));
  if (!said.includes('The signed agreement is uploaded')) throw new Error('the agreement upload was not taken');
  await acceptAgreement(newContext, BASE, DESIGN.name);
  setSetting('import_enabled', true);
  const enrolled = await enrollStudents(newContext, BASE, DESIGN.name, [{ name: 'Drew Example', email: 'drew.example@example.org' }, { name: 'Sky Example', email: 'sky.example@example.org' }], { start: '2026-02-02', end: '2026-06-26' });
  if (!/Done: 2 students created/.test(enrolled)) throw new Error('the students were not enrolled');
  await draftReport(newContext, BASE, DESIGN.name, 'January to June 2026');
  await inviteAndTutor(newContext, BASE, loginOf(DESIGN.email));
  await addTutor(newContext, BASE);
  // Gate B, the holes of Gate A's seed, each through its real form: an Administrator follows the school up (the contact
  // card) and the draft is saved once (Revisions); the details request the school asked is decided after states(), below.
  await soft('assigned-administrator', () => assignAdministrator(newContext, BASE));
  await soft('report-revision', () => saveReport(newContext, BASE));
  // Two more schools with programs running, settled before any other agreement waits (Accept it takes the
  // first on the card): one with no end date, one with a range.
  for (const [s, students, dates] of [[INSTITUTE, [{ name: 'Ash Example', email: 'ash.example@example.org' }, { name: 'Blake Example', email: 'blake.example@example.org' }], { start: '2026-09-01' }], [COMMUNITY, [{ name: 'Emery Example', email: 'emery.example@example.org' }, { name: 'Finley Example', email: 'finley.example@example.org' }], { start: '2026-09-07', end: '2027-01-29' }]]) {
    await applySchool(newContext, BASE, s);
    await approveSchool(newContext, BASE, s.name);
    // The import enrolls only at a school whose agreement is settled: its own account uploads one, and it is accepted.
    const up = await uploadAgreement(newContext, BASE, loginOf(s.email), join(WORK, `${s.website.split('.')[0]}-agreement.pdf`));
    if (!up.includes('The signed agreement is uploaded')) throw new Error(`the agreement upload of ${s.name} was not taken`);
    await acceptAgreement(newContext, BASE, s.name);
    const said = await enrollStudents(newContext, BASE, s.name, students, dates);
    if (!/Done: 2 students created/.test(said)) throw new Error(`the students of ${s.name} were not enrolled`);
  }
  // Gate B: the long words on a school of their own, settled (Accept it pressed on its own row),
  // with a student and a tutor whose names hold them and a list of thirteen lines checked and left waiting.
  await soft('long-word-school', async () => {
    await applyAndUpload(newContext, BASE, LONGWORD);
    await acceptAgreement(newContext, BASE, LONGWORD.name);
    const said = await enrollStudents(newContext, BASE, LONGWORD.name, [LONG_STUDENT, LONG_OTHER], { start: '2026-09-01', end: '2027-01-29' });
    if (!/Done: 2 students created/.test(said)) throw new Error(`the students of ${LONGWORD.name} were not enrolled`);
    await addTutorAs(newContext, BASE, LONGWORD, LONG_TUTOR);
    await checkList(newContext, BASE, LONGWORD.name, CHECKED_LIST, { start: '2026-09-01', end: '2027-01-29', close: true });
  });
  // Gate B: a school in each agreement state the forms reach. Revoked: accepted, then revoked on Institutions > Agreements.
  // Returned: uploaded, then returned with a note. Generated: the template generated, nothing uploaded.
  await soft('agreement-revoked', async () => { await applyAndUpload(newContext, BASE, REVOKED); await acceptAgreement(newContext, BASE, REVOKED.name); await revokeAgreement(newContext, BASE, REVOKED.name); });
  await soft('agreement-returned', async () => { await applyAndUpload(newContext, BASE, RETURNED); await returnAgreement(newContext, BASE, RETURNED.name); });
  await soft('agreement-generated', async () => { clearCeiling(); await applySchool(newContext, BASE, GENERATED); await approveSchool(newContext, BASE, GENERATED.name); await generateOnly(newContext, BASE, loginOf(GENERATED.email)); });
  clearCeiling();
  await applySchool(newContext, BASE, UNIVERSITY);
  await approveSchool(newContext, BASE, UNIVERSITY.name);
  await uploadAgreement(newContext, BASE, loginOf(UNIVERSITY.email), join(WORK, 'university-agreement.pdf'));
  await applySchool(newContext, BASE, STATE_COLLEGE);
  // The sixth application from one address in an hour passes the form's ceiling of five (WPCPM_Form_Guard::PER_HOUR):
  // the copy's rate-limit rows are cleared first, so the sixth invented school is taken as a person's would be.
  if (php(`WPCPM_Ceiling::delete_all(); echo 'cleared';`) !== 'cleared') throw new Error('the rate-limit rows were not cleared');
  await applySchool(newContext, BASE, NOAGREEMENT);
  await approveSchool(newContext, BASE, NOAGREEMENT.name);
  // Gate B: a settled school's newer signed copy, uploaded by its own account, waits beside the one in force.
  await soft('agreement-newer-copy', () => uploadNewerCopy(newContext, BASE, INSTITUTE));
  await addInstitutionUpdate(newContext, BASE);
  await applyMentor(newContext, BASE, TAYLOR);
  states();
  applyAccounts();
  // Gate B: a details request the school asked, decided ("Good to know"), and one of two changes waiting at another school.
  await soft('details-request-declined', () => askAndDecline(newContext, BASE));
  await soft('details-request-waiting-elsewhere', () => askElsewhere(newContext, BASE));
  // 1.123.98: the Overview's facts and figures in every state, and the school whose only person is its contact.
  overviewMore();
}

/**
 * Gate B: two of the design school's students given accounts, Drew Example signed in and Sky Example invited and not signed
 * in (bin/ui/states.php, `accounts`). No form of the site makes a student's account: the program records' sync does, and
 * the rig starts no sync, so the accounts are written as states.php writes the pending students' (gate 7). Answers what
 * it made.
 */
export function applyAccounts() {
  const stage = join(DOCKER, 'release/ui-rig');
  mkdirSync(stage, { recursive: true });
  copyFileSync(join(dirname(fileURLToPath(import.meta.url)), 'states.php'), join(stage, 'states.php'));
  const out = wp(['eval-file', join(stage, 'states.php'), JSON.stringify({ accounts: true, schools: { [DESIGN.name]: recordOf(DESIGN.name) } })], { all: true }).trim().split('\n');
  for (const line of out.slice(0, -1)) console.log('  accounts: ' + line);
  const last = out[out.length - 1];
  if (!last.startsWith('{')) throw new Error(`the students' accounts were not made: ${last}`);
  return JSON.parse(last);
}

/**
 * 1.123.98: the design school's roster in every state the Overview's facts and figures read, and the settled school whose
 * only person is its contact (seed-states.php, `overview-more`), once the students' accounts are made. Throws when a state
 * was not made: the round's numbers would be of another roster.
 */
export function overviewMore() {
  const stage = join(DOCKER, 'release/ui-rig');
  mkdirSync(stage, { recursive: true });
  copyFileSync(join(dirname(fileURLToPath(import.meta.url)), 'seed-states.php'), join(stage, 'seed-states.php'));
  const out = wp(['eval-file', join(stage, 'seed-states.php'), JSON.stringify({ schools: { [DESIGN.name]: recordOf(DESIGN.name) }, admin: process.env.WPCPM_UI_LOGIN || 'wpcredits-local', only: ['overview-more'] })], { all: true }).trim().split('\n');
  for (const line of out.slice(0, -1)) console.log('  overview: ' + line);
  const missed = out.filter(l => l.startsWith('not made'));
  if (missed.length || !out[out.length - 1].startsWith('[')) throw new Error(`the Overview's states were not all made: ${missed.join('; ') || out[out.length - 1]}`);
  return JSON.parse(out[out.length - 1]);
}

/** Two small pictures for the sponsor application's logos, in colour and in white, recorded for restore. */
function writeLogos() {
  for (const [key, colour] of [['colour', '0x3858e9'], ['white', 'white']]) {
    const file = join(DOCKER, 'site/wp-content', LOGOS[key]);
    // A picture an earlier round of this prepared session wrote is the rig's own (it is in the list restore
    // reads); any other file of that name is not, and is never written over.
    const mine = existsSync(CREATED) && readFileSync(CREATED, 'utf8').split('\n').includes(LOGOS[key]);
    if (existsSync(file) && !mine) throw new Error(`refusing: ${LOGOS[key]} is on the copy already`);
    const tmp = join(WORK, `logo-${key}.png`);
    execFileSync(FFMPEG, ['-v', 'error', '-y', '-f', 'lavfi', '-i', `color=c=${colour}:s=320x96`, '-frames:v', '1', tmp]);
    copyFileSync(tmp, file);
    if (!mine) appendFileSync(CREATED, LOGOS[key] + '\n', { mode: 0o600 });
  }
}

/**
 * The picture the design school's site icon is (a plain square, as the logos are plain), recorded for restore. The
 * address that must fail is refused when a file of that name is on the copy: it would load.
 */
function writeIcon() {
  const file = join(DOCKER, 'site/wp-content', ICONS.loads);
  const mine = existsSync(CREATED) && readFileSync(CREATED, 'utf8').split('\n').includes(ICONS.loads);
  if (existsSync(file) && !mine) throw new Error(`refusing: ${ICONS.loads} is on the copy already`);
  if (existsSync(join(DOCKER, 'site/wp-content', ICONS.fails))) throw new Error(`refusing: ${ICONS.fails} is on the copy, so the icon that must not load would`);
  const tmp = join(WORK, 'school-icon.png');
  execFileSync(FFMPEG, ['-v', 'error', '-y', '-f', 'lavfi', '-i', 'color=c=0x3858e9:s=96x96', '-frames:v', '1', tmp]);
  copyFileSync(tmp, file);
  if (!mine) appendFileSync(CREATED, ICONS.loads + '\n', { mode: 0o600 });
}

/**
 * The states no form makes in one sitting (bin/ui/seed-states.php, through the plugin's own classes and post
 * meta copied from the invented items above). Prints what it made and what it could not; answers the list made.
 */
export function states() {
  writeLogos();
  writeIcon();
  const stage = join(DOCKER, 'release/ui-rig');
  mkdirSync(stage, { recursive: true });
  copyFileSync(join(dirname(fileURLToPath(import.meta.url)), 'seed-states.php'), join(stage, 'seed-states.php'));
  const schools = Object.fromEntries([DESIGN, UNIVERSITY, INSTITUTE, COMMUNITY].map(s => [s.name, recordOf(s.name)]));
  // The head beside a site icon (1.123.97): the design school's loads, the long-word school's does not (ICONS). The
  // long-word school is a soft step of the seed: with no record of it, its icon is said "not made", and its scenes skip.
  const icons = [{ school: DESIGN.name, record: schools[DESIGN.name], picture: ICONS.loads }, { school: LONGWORD.name, record: recordOf(LONGWORD.name), picture: ICONS.fails }];
  const input = { schools, apps: { [STATE_COLLEGE.name]: applicationId(STATE_COLLEGE.name) }, logos: LOGOS, icons, admin: process.env.WPCPM_UI_LOGIN || 'wpcredits-local', rep: loginOf(UNIVERSITY.email), joiner: 'drew.example@example.org' };
  // The events go in a process of their own: the sponsors' index keeps the rows it read in memory for the rest
  // of a request, so an event approved in the process that gave its sponsor a tier would read no tier.
  const made = [];
  for (const only of [['applications', 'agreements', 'requests', 'reports', 'sponsors', 'sponsor-apps', 'watch', 'icons'], ['events']]) {
    const out = wp(['eval-file', join(stage, 'seed-states.php'), JSON.stringify({ ...input, only })], { all: true }).trim().split('\n');
    for (const line of out.slice(0, -1)) console.log('  seed: ' + line);
    if (out[out.length - 1].startsWith('[')) made.push(...JSON.parse(out[out.length - 1]));
  }
  return made;
}

/**
 * Gate 7: put the copy, just restored to ui-ready, in one state of the events and one of the students
 * (bin/ui/states.php: `a`, `b` or `c`, and `p0`, `p2` or `p60`). Prints what it made; answers the values the
 * scenes need ({PENDING_STUDENT}, {PENDING_MENTOR}) and the state's own facts.
 */
export function applyState(spec) {
  const [events = 'a', students = 'p0'] = String(spec || '').split(',');
  if (!/^[abc]$/.test(events) || !/^p(0|2|60)$/.test(students)) throw new Error(`not a state: ${spec}`);
  const stage = join(DOCKER, 'release/ui-rig');
  mkdirSync(stage, { recursive: true });
  copyFileSync(join(dirname(fileURLToPath(import.meta.url)), 'states.php'), join(stage, 'states.php'));
  const schools = Object.fromEntries([DESIGN, UNIVERSITY, INSTITUTE, COMMUNITY].map(s => [s.name, recordOf(s.name)]));
  const out = wp(['eval-file', join(stage, 'states.php'), JSON.stringify({ events, students, schools })], { all: true }).trim().split('\n');
  for (const line of out.slice(0, -1)) console.log('  state: ' + line);
  const last = out[out.length - 1];
  if (!last.startsWith('{')) throw new Error(`the state ${spec} was not made: ${last}`);
  const facts = JSON.parse(last);
  return { facts, values: { PENDING_STUDENT: facts.pending_student ? String(facts.pending_student) : '', PENDING_MENTOR: facts.pending_mentor ? String(facts.pending_mentor) : '' } };
}
