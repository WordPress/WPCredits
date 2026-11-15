// The invented items the pictures show, made through the site's own forms, as a visitor or as the local
// Administrator would, on the copy only. Invented schools and people at example.org only. snap.mjs calls
// seed() on a copy restored to ui-base (copy.sh prepare), then saves the result as the snapshot ui-ready.
//
// The work folder (agreement PDFs) is WPCPM_UI_WORK, or .superpowers/ui-rig/work in this repository.
import { execFileSync } from 'node:child_process';
import { appendFileSync, copyFileSync, existsSync, mkdirSync, readFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DOCKER, refuseUnlessCopy, wp } from './copy.mjs';

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
// Every invented school and person the seed makes, the students, colleague and tutor included.
export const INVENTED = { STATE_COLLEGE, UNIVERSITY, DESIGN, INSTITUTE, COMMUNITY, TAYLOR, OTHERS: { names: 'Drew Example, Sky Example, Pat Example, Lee Example, Ash Example, Blake Example, Emery Example, Finley Example, Example Polytechnic, Example Academy of Arts, Example Technical School, Example Hosting Co, Example Plugins Ltd, Jamie Example, Taylor Sample, Casey Example, Morgan Example, Riley Example' }, GATE_7: { names: 'Riley Example, Examplegraduatewaitingonthefinalquizpass Example, Sage Example, TEST Example Academy, Example Academy of Arts, Harper Example, Exampleinstituteforappliedwebengineering, Christopherexample Wojciechowskaexample, Unlisted Example, TEST Example Academy of Design Media and the Applied Arts, Rowan Mentor Example, Indigo Example, Marlowe Example, Learner Example' } };
// The two pictures the invented sponsor application's logos are, written into a folder the copy has already.
export const LOGOS = { colour: 'uploads/2026/10/ui-rig-logo-colour.png', white: 'uploads/2026/10/ui-rig-logo-white.png' };
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

/** The values the scenes' tokens stand for: {DESIGN_RECORD}, {DESIGN_LOGIN}, {UNIVERSITY_LOGIN}, {UPDATES_PATH}. */
export function lookups() {
  return { DESIGN_RECORD: recordOf(DESIGN.name), DESIGN_LOGIN: loginOf(DESIGN.email), UNIVERSITY_LOGIN: loginOf(UNIVERSITY.email), UPDATES_PATH: updatesPath() };
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
  await page.goto(BASE + '/institution-dashboard/', { waitUntil: 'networkidle' });
  const [doc] = await Promise.all([
    context.waitForEvent('page', { timeout: 8000 }).catch(() => null),
    page.getByRole('button', { name: 'Generate the agreement', exact: true }).click(),
  ]);
  const agreement = doc || page;
  await agreement.waitForLoadState('networkidle');
  await agreement.pdf({ path: pdfPath, format: 'A4', printBackground: true });
  if (doc) await doc.close(); else await page.goto(BASE + '/institution-dashboard/', { waitUntil: 'networkidle' });
  await page.locator('input[type=file]').first().setInputFiles(pdfPath);
  await page.getByLabel('The program\'s template, signed', { exact: false }).check();
  await page.getByLabel('This document is signed by somebody who can commit the institution.', { exact: false }).check();
  await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: 'Upload the signed agreement', exact: true }).click()]);
  await page.waitForLoadState('networkidle');
  const said = (await page.locator('main').innerText()).replace(/\s+/g, ' ');
  await context.close();
  return said;
}

/** The Administrator accepts the school's uploaded agreement on the Overview's Collaboration Agreements card. */
export async function acceptAgreement(newContext, BASE) {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/administrator-dashboard/', { waitUntil: 'networkidle' });
  await openCard(page, '#wpcpm-agreements');
  page.once('dialog', d => d.accept());
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('#wpcpm-agreements').getByRole('button', { name: 'Accept it', exact: true }).first().click()]);
  const ok = await page.getByText('The agreement is accepted.', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!ok) throw new Error('the agreement was not accepted');
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

/** The Administrator enrolls invented students for a school on its Institution Dashboard (Enroll students). */
export async function enrollStudents(newContext, BASE, schoolName, students, { program = 'WordPress Credits Program 150h', start, end } = {}) {
  const record = recordOf(schoolName);
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_institution_view=${record}`, { waitUntil: 'networkidle' });
  const sum = page.locator('summary', { hasText: 'Enroll students' }).first();
  const det = page.locator('details', { has: sum }).last();
  if (!(await det.evaluate(d => d.open))) await sum.click();
  await det.getByLabel('Program', { exact: true }).selectOption({ label: program });
  await det.locator('input[type=date]').first().fill(start);
  if (end) await det.locator('input[type=date]').nth(1).fill(end);
  await det.getByLabel('These students have been notified', { exact: false }).check();
  await det.getByLabel('Or paste the same thing here').fill('name,email\n' + students.map(s => `${s.name},${s.email}`).join('\n'));
  await Promise.all([page.waitForLoadState('networkidle'), det.getByRole('button', { name: 'Check the list' }).click()]);
  const create = page.getByRole('button', { name: 'Create these student records' }).first();
  await create.waitFor({ timeout: 20000 });
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

/** The school's own account invites a colleague and adds a tutor on its Institution Dashboard. */
export async function inviteAndTutor(newContext, BASE, login) {
  const context = await newContext({ login });
  const page = await context.newPage();
  await page.goto(BASE + '/institution-dashboard/', { waitUntil: 'networkidle' });
  const invite = page.locator('form', { has: page.getByRole('button', { name: 'Send invitation' }) }).first();
  await invite.getByLabel('Their email address').fill('pat.example@example.org');
  await Promise.all([page.waitForLoadState('networkidle'), invite.getByRole('button', { name: 'Send invitation' }).click()]);
  await page.getByText('The invitation is on its way', { exact: false }).first().waitFor({ timeout: 20000 });
  const tutor = page.locator('form', { has: page.getByRole('button', { name: 'Add', exact: true }) }).first();
  await tutor.getByLabel('Name', { exact: true }).fill('Lee Example');
  await tutor.getByLabel('Email', { exact: true }).fill('lee.example@example.org');
  await Promise.all([page.waitForLoadState('networkidle'), tutor.getByRole('button', { name: 'Add', exact: true }).click()]);
  await page.getByText('The tutor was added to the list.', { exact: false }).first().waitFor({ timeout: 20000 });
  await context.close();
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
  await acceptAgreement(newContext, BASE);
  setSetting('import_enabled', true);
  const enrolled = await enrollStudents(newContext, BASE, DESIGN.name, [{ name: 'Drew Example', email: 'drew.example@example.org' }, { name: 'Sky Example', email: 'sky.example@example.org' }], { start: '2026-02-02', end: '2026-06-26' });
  if (!/Done: 2 students created/.test(enrolled)) throw new Error('the students were not enrolled');
  await draftReport(newContext, BASE, DESIGN.name, 'January to June 2026');
  await inviteAndTutor(newContext, BASE, loginOf(DESIGN.email));
  // Two more schools with programs running, settled before any other agreement waits (Accept it takes the
  // first on the card): one with no end date, one with a range.
  for (const [s, students, dates] of [[INSTITUTE, [{ name: 'Ash Example', email: 'ash.example@example.org' }, { name: 'Blake Example', email: 'blake.example@example.org' }], { start: '2026-09-01' }], [COMMUNITY, [{ name: 'Emery Example', email: 'emery.example@example.org' }, { name: 'Finley Example', email: 'finley.example@example.org' }], { start: '2026-09-07', end: '2027-01-29' }]]) {
    await applySchool(newContext, BASE, s);
    await approveSchool(newContext, BASE, s.name);
    // The import enrolls only at a school whose agreement is settled: its own account uploads one, and it is accepted.
    const up = await uploadAgreement(newContext, BASE, loginOf(s.email), join(WORK, `${s.website.split('.')[0]}-agreement.pdf`));
    if (!up.includes('The signed agreement is uploaded')) throw new Error(`the agreement upload of ${s.name} was not taken`);
    await acceptAgreement(newContext, BASE);
    const said = await enrollStudents(newContext, BASE, s.name, students, dates);
    if (!/Done: 2 students created/.test(said)) throw new Error(`the students of ${s.name} were not enrolled`);
  }
  await applySchool(newContext, BASE, UNIVERSITY);
  await approveSchool(newContext, BASE, UNIVERSITY.name);
  await uploadAgreement(newContext, BASE, loginOf(UNIVERSITY.email), join(WORK, 'university-agreement.pdf'));
  await applySchool(newContext, BASE, STATE_COLLEGE);
  await applyMentor(newContext, BASE, TAYLOR);
  states();
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
 * The states no form makes in one sitting (bin/ui/seed-states.php, through the plugin's own classes and post
 * meta copied from the invented items above). Prints what it made and what it could not; answers the list made.
 */
export function states() {
  writeLogos();
  const stage = join(DOCKER, 'release/ui-rig');
  mkdirSync(stage, { recursive: true });
  copyFileSync(join(dirname(fileURLToPath(import.meta.url)), 'seed-states.php'), join(stage, 'seed-states.php'));
  const schools = Object.fromEntries([DESIGN, UNIVERSITY, INSTITUTE, COMMUNITY].map(s => [s.name, recordOf(s.name)]));
  const input = { schools, apps: { [STATE_COLLEGE.name]: applicationId(STATE_COLLEGE.name) }, logos: LOGOS, admin: process.env.WPCPM_UI_LOGIN || 'wpcredits-local', rep: loginOf(UNIVERSITY.email), joiner: 'drew.example@example.org' };
  // The events go in a process of their own: the sponsors' index keeps the rows it read in memory for the rest
  // of a request, so an event approved in the process that gave its sponsor a tier would read no tier.
  const made = [];
  for (const only of [['applications', 'agreements', 'requests', 'reports', 'sponsors', 'sponsor-apps', 'watch'], ['events']]) {
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
