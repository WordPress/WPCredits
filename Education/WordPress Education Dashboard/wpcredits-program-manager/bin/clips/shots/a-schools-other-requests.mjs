// Clip: "A school's other requests (a student to add, a mentor wanted)" (docs/sections/61-admin-steps-institutions.md).
// Off camera: an invented school applies, is approved, uploads its signed agreement and has it accepted; enrollment
// lists are switched on for the clip (Enrollment lists from institutions, off on the copy); the Administrator enrolls
// one invented student for it, which raises "A student to add" on the Requests from institutions card.
import { join } from 'node:path';
import { settledSchool, setSetting, workDir, enrollStudents } from './_lib.mjs';

export const COMMUNITY = { name: 'Example Community College', city: 'Maplewood', website: 'community.example.org', contact: 'Sasha Example', email: 'sasha.example@example.org' };
const CARD = { css: 'section.wpcpm-administrator__card', hasText: 'Requests from institutions' };

export default {
  slug: 'a-schools-other-requests',
  caption: 'A school\'s other requests: read a student to add, then mark it as handled.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await settledSchool(newContext, BASE, COMMUNITY, join(workDir(), 'community-agreement.pdf'));
    setSetting('import_enabled', true);
    const said = await enrollStudents(newContext, BASE, COMMUNITY.name, [{ name: 'Charlie Example', email: 'charlie.example@example.org' }], { start: '2026-11-16', end: '2027-03-12' });
    if (!/Done: 1 student/.test(said)) throw new Error('the student was not enrolled');
  },
  steps: [
    // 1. Open the Requests from institutions card and find the request.
    { caption: 'Open the Requests from institutions card and find the request.', go: '/administrator-dashboard/', hold: 0.8 },
    { scrollToControl: { role: 'heading', name: 'Waiting for you', exact: false }, offset: 70, hold: 0.5 },
    { press: { within: { css: 'summary', hasText: 'Requests from institutions' }, text: 'Review', exact: true }, hold: 0.8 },
    { pointerTo: { within: CARD, text: 'A student to add', exact: true }, hold: 1.6 },
    // 2. Read "The student is {name}. Raised by {who}."
    { caption: 'Read "The student is {name}. Raised by {who}."', pointerTo: { within: CARD, text: /^The student is Charlie Example/ }, hold: 3.0, poster: true },
    // 3. Done in Airtable, not on this screen: the line stays on the screen's own sentence about it.
    { caption: 'Do the work in Airtable, such as assigning the student a mentor.', pointerTo: { within: CARD, text: /^Assigning a mentor happens in Airtable/ }, hold: 3.0 },
    // 4. Press Mark as handled, or Decline. It opens the panel under the Decide row.
    { caption: 'Press Mark as handled, or Decline.', press: { within: CARD, role: 'button', name: 'Mark as handled' }, hold: 1.4 },
    // 5. The note, optional.
    { caption: 'If you like, write what you did in What you did about it. It is for the log and is not sent to the school.', type: { within: CARD, label: 'What you did about it', exact: false, value: 'Mentor assigned in Airtable.' }, hold: 1.2 },
    // 6. The confirming press.
    { caption: 'Press Mark the request as handled, or Decline the request.', press: { within: CARD, role: 'button', name: 'Mark the request as handled' }, hold: 0.6 },
    // What happens next.
    { caption: 'The request closes and moves to Recently closed, as Handled or Declined.', waitFor: { text: 'That request is closed', exact: false, scrollIntoView: true, offset: 200 }, hold: 1.4 },
    { press: { css: 'summary', hasText: 'Recently closed' }, hold: 0.3 },
    { park: 'clear', hold: 3.6 },
  ],
};
