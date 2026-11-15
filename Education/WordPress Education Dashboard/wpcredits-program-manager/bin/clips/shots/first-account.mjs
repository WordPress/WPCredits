// Clip: "Give a school its first account" (docs/sections/61-admin-steps-institutions.md).
// Off camera: an invented school applies, confirms and is approved; then the Administrator removes its only
// member on Institutions > Accounts (Manage members, Remove, as the guide's own screens do), so the school has
// no account. The clip gives it one.
import { applySchool, approveSchool, recordOf } from './_lib.mjs';

export const STATE_COLLEGE = { name: 'Example State College', city: 'Lakeside', website: 'state.example.org', contact: 'Morgan Example', email: 'morgan.example@example.org' };

export default {
  slug: 'first-account',
  caption: 'Give a school its first account, from its Institution Dashboard.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await applySchool(newContext, BASE, STATE_COLLEGE);
    await approveSchool(newContext, BASE, STATE_COLLEGE.name);
    // Remove its only member, on Institutions > Accounts > Manage members, accepting the screen's own question.
    const context = await newContext();
    const page = await context.newPage();
    await page.goto(BASE + '/wp-admin/admin.php?page=wpcpm-institutions&tab=accounts', { waitUntil: 'networkidle' });
    const row = page.locator('tr', { hasText: STATE_COLLEGE.contact }).first();
    await row.hover();
    await Promise.all([page.waitForLoadState('networkidle'), row.getByRole('link', { name: 'Manage members' }).click()]);
    page.once('dialog', d => d.accept());
    await Promise.all([page.waitForLoadState('networkidle'), page.locator('li, tr, div', { hasText: STATE_COLLEGE.email }).last().getByRole('button', { name: 'Remove', exact: true }).click()]);
    const left = await page.locator('#wpbody-content').innerText();
    await context.close();
    if (!left.includes('Nobody can act for this institution on this site.')) throw new Error('the member was not removed');
  },
  steps: [
    // The clip starts on the school's Institution Dashboard, opened with Viewing as institution: an Administrator's
    // first view of the page is another school's, which holds real details, so that part is off camera.
    { caption: 'Open the school\'s Institution Dashboard.', go: () => `/institution-dashboard/?wpcpm_institution_view=${recordOf(STATE_COLLEGE.name)}`, hold: 1.0 },
    { pointerTo: { role: 'combobox', name: 'Viewing as institution' }, hold: 1.2 },
    { pointerTo: { text: 'Example State College', exact: true }, hold: 1.6 },
    { caption: 'In Representatives and tutors, press Add or re-add an account on the Institutions screen.', scrollToControl: { css: 'summary', hasText: 'Representatives and tutors' }, offset: 160, hold: 0.4 },
    { pointerTo: { css: 'summary', hasText: 'Representatives and tutors' }, hold: 1.0 },
    { scrollToControl: { role: 'link', name: 'Add or re-add an account on the Institutions screen', exact: false }, offset: 380, hold: 1.4 },
    { press: { role: 'link', name: 'Add or re-add an account on the Institutions screen', exact: false }, hold: 0.6 },
    { caption: 'Under Add an account, type the person\'s Name and Email address.', scrollToControl: { role: 'heading', name: 'Add an account' }, offset: 200, hold: 0.8 },
    { type: { within: { css: 'form', hasText: 'Add account' }, label: 'Name', value: 'Casey Example' }, hold: 0.4 },
    { type: { within: { css: 'form', hasText: 'Add account' }, label: 'Email address', value: 'casey.example@example.org' }, hold: 1.0 },
    { caption: 'Press Add account.', press: { within: { css: 'form', hasText: 'Add account' }, role: 'button', name: 'Add account' }, hold: 0.4 },
    { caption: 'The site makes the account and joins it to the school.', waitFor: { text: 'The account was added', exact: false, scrollIntoView: true, offset: 140 }, hold: 4.5, poster: true },
  ],
};
