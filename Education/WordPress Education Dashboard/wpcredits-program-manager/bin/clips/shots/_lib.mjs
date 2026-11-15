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
  await page.goto(BASE + '/institution-dashboard/', { waitUntil: 'networkidle' });
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
 * The Administrator enrolls invented students for a school on its Institution Dashboard (Enroll students), as the
 * guide's "Enroll students for a school" says: program, dates, the notified box, a pasted list, Check the list,
 * Create these student records.
 */
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
