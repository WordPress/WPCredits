// Clip: "A school applied" (docs/sections/61-admin-steps-institutions.md). The lines of text are the job's own steps.
import { applySchool } from './_lib.mjs';

export const NORTHFIELD = { name: 'Northfield College', city: 'Northfield', website: 'northfield.example.org', contact: 'Alex Example', email: 'alex.example@example.org', signer: 'Sam Example' };

export default {
  slug: 'a-school-applied',
  caption: 'A school applied: read the application on the Overview, then approve it.',
  login: 'wpcredits-local',

  // Off camera: the invented school applies through the public form, and confirms its address with the
  // link in the acknowledgement (the mail is swallowed by the copy; the filming plugin keeps the link).
  async prepare({ newContext, BASE }) {
    await applySchool(newContext, BASE, NORTHFIELD);
  },

  steps: [
    // 1. Open Administrator Dashboard > Overview and find the school on the Institution applications card.
    { caption: 'Open Administrator Dashboard > Overview and find the school on the Institution applications card.', go: '/administrator-dashboard/', hold: 1.0 },
    { pointerTo: { css: 'a', hasText: 'Overview' }, hold: 1.6 },
    { scrollToControl: { role: 'heading', name: 'Waiting for you', exact: false }, offset: 70, hold: 0.5 },
    { press: { within: { css: 'summary', hasText: 'Institution applications' }, text: 'Review', exact: true }, hold: 0.8 },
    { pointerTo: { role: 'link', name: 'Northfield College' }, hold: 2.6 },
    // 2. Press Read the application and read the answers.
    { caption: 'Press Read the application and read the answers.', press: { css: 'summary', hasText: 'Read the application' }, hold: 1.2 },
    { scrollToControl: { role: 'rowheader', name: 'Name of the person we should contact', exact: false }, offset: 150, hold: 2.6, poster: true },
    { scrollToControl: { role: 'rowheader', name: 'Why are you interested in offering WordPress Credits to your students?', exact: false }, offset: 150, hold: 2.6 },
    // 4. Once you have talked with the school and the program wants to take it, press Approve and confirm.
    { caption: 'Once you have talked with the school and the program wants to take it, press Approve and confirm.',
      press: { role: 'button', name: 'Approve' },
      confirm: { read: 3.6 }, moveMs: 900, hold: 0.8 },
    // The result: the message at the top of the page.
    { caption: 'Approve creates the school in Airtable, at the stage set in Institution starting stage (First Contact Made unless changed), with Agreement Status Not started.',
      waitFor: { text: 'The application is approved.', scrollIntoView: true, offset: 90 }, hold: 0.2 },
    { park: 'clear', hold: 4.5 },
  ],
};
