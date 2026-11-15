// Clip: "A semester report: draft, review, approve, reopen" (docs/sections/61-admin-steps-institutions.md), the
// draft, review and approve part.
import { join } from 'node:path';
import { settledSchool, setSetting, workDir, enrollStudents } from './_lib.mjs';

export const DESIGN = { name: 'Example School of Design', city: 'Hillford', website: 'design.example.org', contact: 'Quinn Example', email: 'quinn.example@example.org' };

export default {
  slug: 'a-semester-report',
  caption: 'A semester report: draft it, review it and approve it.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await settledSchool(newContext, BASE, DESIGN, join(workDir(), 'design-agreement.pdf'));
    // Enrollment lists are switched on for the clip (Enrollment lists from institutions, off on the copy), and two
    // invented students are enrolled for the semester January to June 2026, so the semester has students to report.
    setSetting('import_enabled', true);
    const said = await enrollStudents(newContext, BASE, DESIGN.name, [{ name: 'Drew Example', email: 'drew.example@example.org' }, { name: 'Sky Example', email: 'sky.example@example.org' }], { start: '2026-02-02', end: '2026-06-26' });
    if (!/Done: 2 students created/.test(said)) throw new Error('the students were not enrolled');
  },
  steps: [
    { caption: 'For any other semester: open Institutions > Semester reports, under Draft any semester pick the Institution and the semester, and press Draft now.',
      go: '/wp-admin/admin.php?page=wpcpm-institutions&tab=reports', hold: 1.0 },
    { scrollToControl: { role: 'heading', name: 'Draft any semester' }, offset: 140, hold: 0.6 },
    { type: { role: 'combobox', name: 'Institution', value: 'Example School' }, hold: 0.6 },
    { press: { role: 'option', name: 'Example School of Design' }, hold: 0.6 },
    { choose: { within: { css: 'form', hasText: 'Draft now' }, css: 'select:not(#wpcpm-draft-institution)', option: 'January to June 2026' }, hold: 0.8 },
    { press: { role: 'button', name: 'Draft now' }, hold: 0.4 },
    { caption: 'A new draft says "The draft is ready for review." The school cannot see it.', waitFor: { text: 'The draft is ready for review.', exact: false, scrollIntoView: true, offset: 140 }, hold: 3.2, poster: true },
    { caption: 'Write what you want in the sections and press Save the report.', scrollToControl: { text: 'Participation', exact: true }, offset: 160, hold: 0.4 },
    { type: { label: 'What you want to say here', nth: 1, value: 'Two students of the School of Design joined the program this semester and contributed to WordPress.' }, hold: 1.0 },
    { scrollToControl: { role: 'button', name: 'Save the report' }, offset: 420, hold: 0.4 },
    { press: { role: 'button', name: 'Save the report' }, hold: 1.6 },
    { caption: 'Press Approve this report.', scrollToControl: { role: 'button', name: 'Approve this report' }, offset: 420, hold: 0.4 },
    { press: { role: 'button', name: 'Approve this report' }, hold: 0.4 },
    { caption: 'Approve this report: the school can read and download it, and its accounts are emailed.', waitFor: { text: 'approved', exact: false, scrollIntoView: true, offset: 140 }, park: 'clear', hold: 5 },
  ],
};
