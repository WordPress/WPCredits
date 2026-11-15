// Clip: "Invite a representative or a tutor for a school, and keep its tutor list" (docs/sections/61-admin-steps-institutions.md).
// Off camera: an invented school applies, confirms and is approved. The clip starts on its Institution Dashboard,
// opened with Viewing as institution (an Administrator's first view of that page is another school's).
import { applySchool, approveSchool, recordOf } from './_lib.mjs';

export const STATE_COLLEGE = { name: 'Example State College', city: 'Lakeside', website: 'state.example.org', contact: 'Morgan Example', email: 'morgan.example@example.org' };
const CARD = { css: 'details', hasText: 'Invite a colleague' };

export default {
  slug: 'invite-a-representative',
  caption: 'Invite a colleague of a school, and add a tutor to its list.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await applySchool(newContext, BASE, STATE_COLLEGE);
    await approveSchool(newContext, BASE, STATE_COLLEGE.name);
  },
  steps: [
    { caption: 'Open the school\'s Institution Dashboard and find Representatives and tutors.', go: () => `/institution-dashboard/?wpcpm_institution_view=${recordOf(STATE_COLLEGE.name)}`, hold: 1.2 },
    { scrollToControl: { css: 'summary', hasText: 'Representatives and tutors' }, offset: 120, hold: 0.4 },
    { pointerTo: { css: 'summary', hasText: 'Representatives and tutors' }, hold: 1.4 },
    { caption: 'Under Invite a colleague, type the address in Their email address.', scrollToControl: { text: 'Invite a colleague', exact: true }, offset: 140, hold: 0.4 },
    { type: { label: 'Their email address', value: 'pat.example@example.org' }, hold: 0.8 },
    { caption: 'Under Level, choose Representative or Tutor.', press: { role: 'radio', name: 'Tutor', exact: false }, hold: 1.2 },
    { caption: 'Press Send invitation.', press: { role: 'button', name: 'Send invitation' }, hold: 0.4 },
    { waitFor: { text: 'invitation', exact: false }, hold: 0.2 },
    { scrollToControl: { text: /invitation .*(sent|queued)|is invited/i }, offset: 200, hold: 2.6, poster: true },
    { caption: 'For the tutor list: under Tutors, in Add a tutor, type the Name and Email, then press Add.', scrollToControl: { text: 'Add a tutor', exact: true }, offset: 220, hold: 0.4 },
    { type: { label: 'Name', value: 'Lee Example' }, hold: 0.3 },
    { type: { label: 'Email', value: 'lee.example@example.org' }, hold: 0.4 },
    { press: { role: 'button', name: 'Add' }, hold: 0.4 },
    { waitFor: { text: 'The tutor was added to the list.', exact: false, scrollIntoView: true, offset: 220 }, park: 'clear', hold: 4.5 },
  ],
};
