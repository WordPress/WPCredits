// Off-camera helpers the shots' prepare steps share. Everything goes through the site's own forms, as a
// visitor or as the local Administrator would, on the copy only. Invented schools and people only.
import { readFileSync, existsSync, mkdirSync } from 'node:fs';
import { join } from 'node:path';
import { DOCKER, wp } from '../copy.mjs';

/** Run a little PHP on the copy and answer its last line. Never prints anything itself. */
export function php(code) { return wp(['eval', code]); }

/** The newest application post ID with this title, or 0. */
export function applicationId(title) {
  return Number(php(`$ids = get_posts( array( 'post_type' => 'wpcpm_inst_app', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'title' => ${JSON.stringify(title)}, 'suppress_filters' => false ) ); echo $ids ? (int) $ids[0] : 0;`)) || 0;
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
  const log = join(DOCKER, 'site/wp-content/wpcpm-filming-links.log');
  const link = existsSync(log) && readFileSync(log, 'utf8').trim().split('\n').reverse().find(l => l.includes('wpcpm_apply_verify') && l.includes(`app=${id}`));
  if (!link || !link.startsWith(BASE + '/')) throw new Error('no confirmation link was kept for the application');
  await page.goto(link.replace(/&amp;/g, '&'), { waitUntil: 'networkidle' });
  await context.close();
  const confirmed = php(`echo get_post_meta( ${id}, WPCPM_Institution_Application::META_VERIFIED, true ) ? 'yes' : 'no';`);
  if (confirmed !== 'yes') throw new Error('the address is still not confirmed');
  return id;
}

/** The Administrator approves an application on the Overview, as in the a-school-applied clip. */
export async function approveSchool(newContext, BASE, name) {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/administrator-dashboard/', { waitUntil: 'networkidle' });
  await page.locator('summary', { hasText: 'Institution applications' }).first().click();
  const item = page.locator('article', { hasText: name }).first();
  page.once('dialog', d => d.accept());
  await Promise.all([page.waitForLoadState('networkidle'), item.getByRole('button', { name: 'Approve', exact: true }).click()]);
  const said = await page.getByText('The application is approved.', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!said) throw new Error(`approving ${name} did not say it was approved`);
  return recordOf(name);
}

/** The Airtable record ID (invented by the stand-in) of an approved invented school. */
export function recordOf(name) {
  const id = applicationId(name);
  return php(`echo (string) get_post_meta( ${id}, WPCPM_Institution_Application::META_RECORD, true );`);
}

/**
 * The school's own account makes the program's template agreement, saves it as a PDF the way a browser's
 * "Save as PDF" does, and uploads it as signed, on its Institution Dashboard. The PDF is kept in the work folder.
 */
export async function uploadAgreement(newContext, BASE, login, pdfPath, nameOnDocument = '') {
  const context = await newContext({ login });
  // The agreement page opens the browser's print dialog by itself; here the PDF is saved directly instead.
  await context.addInitScript(() => { window.print = () => {}; });
  const page = await context.newPage();
  // The generate form and the upload form are on the Agreement view, not on the bare page (the Overview).
  await page.goto(BASE + '/institution-dashboard/?wpcpm_view=agreement', { waitUntil: 'networkidle' });
  // The institution's name as it is to be printed on the document, typed in the generate form's own box.
  if (nameOnDocument) {
    const box = page.locator('form', { has: page.getByRole('button', { name: 'Generate the agreement', exact: true }) }).locator('input[name="wpcpm_agreement_name"]').filter({ visible: true });
    if (!(await box.count())) throw new Error('the generate form shows no name box');
    await box.first().fill(nameOnDocument);
  }
  const gen = page.getByRole('button', { name: 'Generate the agreement', exact: true });
  const [doc] = await Promise.all([
    context.waitForEvent('page', { timeout: 8000 }).catch(() => null),
    gen.click(),
  ]);
  const agreement = doc || page;
  await agreement.waitForLoadState('networkidle');
  await agreement.pdf({ path: pdfPath, format: 'A4', printBackground: true });
  if (doc) await doc.close(); else await page.goto(BASE + '/institution-dashboard/?wpcpm_view=agreement', { waitUntil: 'networkidle' });
  await page.locator('input[type=file]').first().setInputFiles(pdfPath);
  await page.getByLabel('The program\'s template, signed', { exact: false }).check();
  await page.getByLabel('This document is signed by somebody who can commit the institution.', { exact: false }).check();
  await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: 'Upload the signed agreement', exact: true }).click()]);
  await page.waitForLoadState('networkidle');
  const said = (await page.locator('main').innerText()).replace(/\s+/g, ' ');
  await context.close();
  return said;
}

/** The login of the account holding an invented address. */
export function loginOf(email) {
  return php(`$u = get_user_by( 'email', ${JSON.stringify(email)} ); echo $u ? $u->user_login : '';`);
}

/**
 * Switch one of the plugin's settings on the copy, off camera. The stored option is read with the filming
 * plugin's in-memory token taken off first, so the placeholder token is never written.
 */
export function setSetting(key, value) {
  if (!/^[a-z0-9_]+$/.test(key)) throw new Error('bad setting key');
  return php(`remove_all_filters( 'option_wpcpm_settings' ); $o = get_option( 'wpcpm_settings', array() ); if ( ! is_array( $o ) ) { $o = array(); } $o[${JSON.stringify(key)}] = ${JSON.stringify(value)}; update_option( 'wpcpm_settings', $o ); echo 'set';`);
}

/** A person applies to mentor through the public form, and confirms the address with the link in the acknowledgement. */
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
  for (const l of m.languages || ['English']) await page.locator('input[name="wpcpm_mentor_application[languages][]"]').filter({ has: page.locator(`xpath=self::*[@value="${l}"]`) }).check();
  for (const e of m.expertise || ['Accessibility', 'Core']) await page.locator(`input[name="wpcpm_mentor_application[expertise][]"][value="${e}"]`).check();
  await f('Available hours per week to mentor students', String(m.hours || 4));
  await page.locator('input[name="wpcpm_mentor_application[sponsored]"][value="No"]').check();
  const wants = page.locator('input[name="wpcpm_mentor_application[wants_sponsor]"][value="No"]');
  if (await wants.count()) await wants.check();
  await page.locator('input[name="wpcpm_mentor_application[gpl]"]').check();
  await page.locator('input[name="wpcpm_mentor_application[consent]"]').check();
  await page.waitForTimeout(7000);
  await Promise.all([page.waitForLoadState('networkidle'), page.locator('main form').getByRole('button').last().click()]);
  const said = (await page.locator('main').innerText()).replace(/\s+/g, ' ').slice(0, 300);
  const id = Number(php(`$ids = get_posts( array( 'post_type' => 'wpcpm_ment_app', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'title' => ${JSON.stringify(m.name)}, 'suppress_filters' => false ) ); echo $ids ? (int) $ids[0] : 0;`));
  if (!id) throw new Error('the mentor application was not stored: ' + said);
  const log = join(DOCKER, 'site/wp-content/wpcpm-filming-links.log');
  const link = existsSync(log) && readFileSync(log, 'utf8').trim().split('\n').reverse().find(l => l.includes('wpcpm_mentor_apply_verify'));
  if (!link || !link.startsWith(BASE + '/')) throw new Error('no confirmation link was kept for the mentor application');
  await page.goto(link.replace(/&amp;/g, '&'), { waitUntil: 'networkidle' });
  await context.close();
  return id;
}

/** The Administrator accepts the school's uploaded agreement on the Overview's Collaboration Agreements card. */
export async function acceptAgreement(newContext, BASE) {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/administrator-dashboard/', { waitUntil: 'networkidle' });
  await page.locator('summary', { hasText: 'Collaboration Agreements' }).first().click();
  page.once('dialog', d => d.accept());
  await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: 'Accept it', exact: true }).first().click()]);
  const ok = await page.getByText('The agreement is accepted.', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!ok) throw new Error('the agreement was not accepted');
}

/** A school with an approved application, a signed agreement uploaded by its own account, and accepted. */
export async function settledSchool(newContext, BASE, school, pdfPath) {
  await applySchool(newContext, BASE, school);
  const record = await approveSchool(newContext, BASE, school.name);
  const said = await uploadAgreement(newContext, BASE, loginOf(school.email), pdfPath, school.name);
  if (!said.includes('The signed agreement is uploaded')) throw new Error('the agreement upload was not taken');
  await acceptAgreement(newContext, BASE);
  return record;
}

export function workDir() {
  const dir = new URL('../../../.superpowers/guides/clips/work/', import.meta.url).pathname;
  mkdirSync(dir, { recursive: true });
  return dir;
}

/**
 * The Administrator enrolls invented students for a school on its Institution Dashboard's Enroll students view, as the
 * guide's "Enroll students for a school" says: program, dates, the notified box, a pasted list, Check the list,
 * Create these student records. The view is the form itself (no fold of its own).
 */
export async function enrollStudents(newContext, BASE, schoolName, students, { program = 'WordPress Credits Program 150h', start, end } = {}) {
  const record = recordOf(schoolName);
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=enroll&wpcpm_institution_view=${record}`, { waitUntil: 'networkidle' });
  const form = page.locator('main form', { has: page.getByRole('button', { name: 'Check the list', exact: true }) }).first();
  if (!(await form.count())) throw new Error('the Enroll students view shows no form with Check the list');
  await form.getByLabel('Program', { exact: false }).first().selectOption({ label: program });
  await form.getByLabel('Start date', { exact: false }).fill(start);
  if (end) await form.getByLabel('End date', { exact: false }).fill(end);
  await form.getByLabel('These students have been notified', { exact: false }).check();
  await form.getByLabel('Or paste the same thing here', { exact: false }).fill('name,email\n' + students.map(s => `${s.name},${s.email}`).join('\n'));
  await Promise.all([page.waitForLoadState('networkidle'), form.getByRole('button', { name: 'Check the list', exact: true }).click()]);
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

/** The Administrator drafts a semester report on Institutions > Semester reports, Draft any semester, Draft now. */
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

/**
 * The school's own account invites a colleague and adds a tutor on its Institution Dashboard's view Your institution,
 * in the card Representatives and tutors: Invite a colleague and Add a tutor are folds there, each opened first.
 */
export async function inviteAndTutor(newContext, BASE, login) {
  const context = await newContext({ login });
  const page = await context.newPage();
  const view = BASE + '/institution-dashboard/?wpcpm_view=institution';
  const open = async title => {
    const fold = page.locator('details', { has: page.locator(':scope > summary', { hasText: title }) }).last();
    if (!(await fold.count())) throw new Error(`the view Your institution shows no ${title}`);
    if (!(await fold.evaluate(d => d.open))) await fold.locator(':scope > summary').click();
    return fold;
  };
  await page.goto(view, { waitUntil: 'networkidle' });
  const invite = await open('Invite a colleague');
  await invite.getByLabel('Email address', { exact: false }).fill('pat.example@example.org');
  await Promise.all([page.waitForLoadState('networkidle'), invite.getByRole('button', { name: 'Send invitation' }).click()]);
  await page.getByText('The invitation is on its way', { exact: false }).first().waitFor({ timeout: 20000 });
  await page.goto(view, { waitUntil: 'networkidle' });
  const tutor = await open('Add a tutor');
  await tutor.getByLabel('Name', { exact: false }).fill('Lee Example');
  await tutor.getByLabel('Email', { exact: false }).fill('lee.example@example.org');
  await Promise.all([page.waitForLoadState('networkidle'), tutor.getByRole('button', { name: 'Add', exact: true }).click()]);
  await page.getByText('The tutor was added to the list.', { exact: false }).first().waitFor({ timeout: 20000 });
  await context.close();
}

/*
 * The talks with institutions (Institutions off Airtable). The five talks clips share one invented world, made off
 * camera by talksWorld(): three invented schools apply and are approved while Airtable keeps the record (the filming
 * plugin answers Airtable in memory), the institutions sync reads the copy's own list back (the filming plugin answers
 * the sync's whole reads of the Institutions and Countries tables with what the copy holds), the record moves to the
 * site through the plugin's own command, which asks the switch's three questions, and then the talks are written
 * through the Talks card and the Talks view themselves.
 */

export const TALKS_SCHOOLS = {
  polytechnic: { name: 'Example Polytechnic College', city: 'Riverbend', website: 'polytechnic.example.org', contact: 'Robin Example', email: 'robin.example@example.org' },
  lakeside: { name: 'Example Lakeside College', city: 'Lakeside', website: 'lakeside.example.org', contact: 'Casey Example', email: 'casey.example@example.org' },
  valley: { name: 'Example Valley College', city: 'Greenvale', website: 'valley.example.org', contact: 'Jordan Example', email: 'jordan.example@example.org' },
};

export const TALKS_LEADS = {
  hillside: { name: 'Example Hillside College', city: 'Hillside', next: 'Find the right person to write to' },
  riverside: { name: 'Example Riverside Institute', city: 'Riverside', country: 'Canada', next: 'Book a first call', note: 'Met their dean at a regional WordPress meetup. Interested in a pilot next year.' },
};

/** A day `n` days from today (UTC, negative for the past) as the site keeps one, Y-m-d. */
export function dayFrom(n) {
  return new Date(Date.now() + n * 86400000).toISOString().slice(0, 10);
}


/** Whether the copy's site keeps the record of institutions now. */
export function onSite() {
  return php(`echo WPCPM_Institutions_Index::on_site() ? 'yes' : 'no';`) === 'yes';
}

/** The local Administrator's user ID, for the Assigned Administrator box. */
export function localAdminId(login = 'wpcredits-local') {
  return Number(php(`$u = get_user_by( 'login', ${JSON.stringify(login)} ); echo $u ? (int) $u->ID : 0;`)) || 0;
}

/**
 * The site keeps the record of institutions: one institutions sync run to its end (start, then ticks), which reads
 * the Institutions table within the switch's 15 minutes, then `wp wpcredits setting set institutions_on_site on`,
 * which asks the switch's three questions itself and stores nothing when one refuses. Never a write of the option.
 */
export function siteKeepsRecord() {
  if (onSite()) return;
  const ran = php(`WPCPM_Institutions_Sync::start( true ); $n = 0; while ( WPCPM_Institutions_Sync::is_running() && $n < 400 ) { WPCPM_Institutions_Sync::tick(); ++$n; } echo WPCPM_Institutions_Sync::is_running() ? 'running' : 'done';`);
  if (ran !== 'done') throw new Error('the institutions sync did not finish off camera');
  let said = '';
  try { said = wp(['wpcredits', 'setting', 'set', 'institutions_on_site', 'on']); } catch (e) { said = 'the command failed'; }
  if (!onSite()) throw new Error('the switch was refused: ' + said);
}

/**
 * The card's address of an invented school: its Institution Dashboard, For Administrators, whose first card is the Talks
 * card. No anchor: the page opens at its top, so no heading is half under the toolbar in the first frame.
 */
export function talksCardPath(name) {
  return `/institution-dashboard/?wpcpm_view=administrators&wpcpm_institution_view=${recordOf(name)}`;
}

/**
 * On an invented school's Talks card, as the Administrator: the fields given, then Save. `f` holds any of stage (the
 * option's words), next, followUp and lastContact (Y-m-d), helpDesk (an https address), and assigned (a user ID).
 */
export async function saveTalks(newContext, BASE, name, f) {
  return saveTalksAt(newContext, BASE, recordOf(name), f, name);
}

/** The same, for a school by its record ID. */
export async function saveTalksAt(newContext, BASE, record, f, name = record) {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(`${BASE}/institution-dashboard/?wpcpm_view=administrators&wpcpm_institution_view=${record}`, { waitUntil: 'networkidle' });
  const form = page.locator('#wpcpm-talks form').first();
  if (!(await form.count())) throw new Error(`${name} shows no Talks card`);
  if (f.stage) await form.locator('select[name="stage"]').selectOption({ label: f.stage });
  if (f.next) await form.locator('input[name="next_step"]').fill(f.next);
  if (f.followUp) await form.locator('input[name="follow_up"]').fill(f.followUp);
  if (f.lastContact) await form.locator('input[name="last_contact"]').fill(f.lastContact);
  if (f.helpDesk) await form.locator('input[name="help_desk"]').fill(f.helpDesk);
  // The one field to type and pick keeps its select under the box; the pick is made on it, as the box does.
  if (f.assigned) await form.locator('select[name="wpcpm_talks_assigned"]').selectOption(String(f.assigned), { force: true });
  await Promise.all([page.waitForLoadState('networkidle'), form.getByRole('button', { name: 'Save', exact: true }).click()]);
  const ok = await page.getByText('The talks are saved', { exact: false }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!ok) throw new Error(`the talks of ${name} were not saved`);
}

/** A note on a Talks card, through Add a note and Add the note. `path` is the card's address. */
export async function addTalksNote(newContext, BASE, path, text) {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + path, { waitUntil: 'networkidle' });
  await page.getByLabel('Add a note', { exact: true }).fill(text);
  await Promise.all([page.waitForLoadState('networkidle'), page.getByRole('button', { name: 'Add the note', exact: true }).click()]);
  const ok = await page.getByText('The note is added.', { exact: true }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  await context.close();
  if (!ok) throw new Error('the note was not added');
}

/** A lead through the Talks view's Add a lead; answers the address of its page. */
export async function addLead(newContext, BASE, lead, followUp = '') {
  const context = await newContext();
  const page = await context.newPage();
  await page.goto(BASE + '/administrator-dashboard/?wpcpm_view=talks', { waitUntil: 'networkidle' });
  const fold = page.locator('#wpcpm-add-lead');
  if (!(await fold.locator('details').evaluate(d => d.open))) await fold.locator('summary').first().click();
  await fold.locator('input[name="name"]').fill(lead.name);
  if (lead.city) await fold.locator('input[name="city"]').fill(lead.city);
  if (lead.country) await fold.locator('select[name="country"]').selectOption({ label: lead.country }, { force: true });
  if (lead.next) await fold.locator('input[name="next_step"]').fill(lead.next);
  if (followUp) await fold.locator('input[name="follow_up"]').fill(followUp);
  if (lead.note) await fold.locator('textarea[name="note"]').fill(lead.note);
  await Promise.all([page.waitForLoadState('networkidle'), fold.getByRole('button', { name: 'Add the lead', exact: true }).click()]);
  const ok = await page.getByText('The lead is added.', { exact: true }).first().waitFor({ timeout: 20000 }).then(() => 1, () => 0);
  const url = page.url();
  await context.close();
  if (!ok) throw new Error(`the lead ${lead.name} was not added`);
  return url.replace(BASE, '');
}

/** The address of an invented lead's page, by its name, as the Talks view links it. */
export function leadPathOf(name) {
  const key = php(`echo WPCPM_Institution_Leads::name_taken( ${JSON.stringify(name)} );`);
  if (!/^lead-\d+$/.test(key)) throw new Error(`no lead is named ${name}`);
  return `/administrator-dashboard/?wpcpm_view=talks&wpcpm_lead=${key}`;
}

/**
 * The invented world of the talks clips. Polytechnic is due since a week, Lakeside since two days and holds two notes,
 * Valley has nothing typed yet (the stage clip fills it); each is assigned to the local Administrator, so the view's
 * "your schools" shows them. Two leads: Hillside, due tomorrow; Riverside, in Canada, with a note, due since three days.
 */
/**
 * The copy's own schools that nobody is assigned to are every Administrator's "your schools", so the Talks view would
 * draw them (under stand-in names) beside the invented ones. Off camera, each is assigned to another Administrator
 * through its own Talks card, so the view the clips film holds the invented schools and leads alone. Restore undoes it.
 */
export async function tidyYourSchools(newContext, BASE, me) {
  const out = php(`$inv = function_exists( 'wpcpm_filming_invented_schools' ) ? wpcpm_filming_invented_schools() : array(); $other = 0; foreach ( array_keys( WPCPM_Institution_Assignment::choices() ) as $id ) { if ( (int) $id > 0 && (int) $id !== ${me} ) { $other = (int) $id; break; } } $keys = array(); foreach ( WPCPM_Institution_Talks::all_facts() as $k => $f ) { if ( empty( $f['lead'] ) && ! in_array( trim( (string) $f['name'] ), $inv, true ) && in_array( (int) $f['assigned'], array( 0, ${me} ), true ) ) { $keys[] = $k; } } echo $other . '|' . implode( ',', $keys );`);
  const [other, list] = out.split('|');
  if (!Number(other)) throw new Error('no other Administrator to assign the copy\'s own schools to');
  for (const key of (list || '').split(',').filter(Boolean)) await saveTalksAt(newContext, BASE, key, { assigned: Number(other) });
}

export async function talksWorld(newContext, BASE) {
  for (const s of Object.values(TALKS_SCHOOLS)) {
    await applySchool(newContext, BASE, s);
    await approveSchool(newContext, BASE, s.name);
  }
  siteKeepsRecord();
  const me = localAdminId();
  if (!me) throw new Error('the local Administrator was not found');
  await tidyYourSchools(newContext, BASE, me);
  await saveTalks(newContext, BASE, TALKS_SCHOOLS.polytechnic.name, { stage: 'Call Scheduled', next: 'Call the dean about the semester dates', followUp: dayFrom(-7), lastContact: dayFrom(-21), assigned: me });
  await saveTalks(newContext, BASE, TALKS_SCHOOLS.lakeside.name, { stage: 'Info Sent', next: 'Ask whether the brochure arrived', followUp: dayFrom(-2), lastContact: dayFrom(-9), assigned: me });
  await saveTalks(newContext, BASE, TALKS_SCHOOLS.valley.name, { assigned: me });
  await addTalksNote(newContext, BASE, talksCardPath(TALKS_SCHOOLS.lakeside.name), 'First call: they run a final-year internship course and want students to contribute to an open source project.');
  await addTalksNote(newContext, BASE, talksCardPath(TALKS_SCHOOLS.lakeside.name), 'Sent the program brochure and the sample agreement to the internship coordinator.');
  await addLead(newContext, BASE, TALKS_LEADS.hillside, dayFrom(1));
  await addLead(newContext, BASE, TALKS_LEADS.riverside, dayFrom(-3));
}
