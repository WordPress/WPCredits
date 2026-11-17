// Clip: "Invite a representative or a tutor for a school, and keep its tutor list" (docs/sections/61-admin-steps-institutions.md).
// Off camera: an invented school applies, confirms and is approved. The clip starts on its Institution Dashboard,
// opened with Viewing as institution (an Administrator's first view of that page is another school's), and goes to
// the view Your institution, where the card Representatives and tutors holds Invite a colleague and Tutors.
import { applySchool, approveSchool, recordOf } from './_lib.mjs';

export const STATE_COLLEGE = { name: 'Example State College', city: 'Lakeside', website: 'state.example.org', contact: 'Morgan Example', email: 'morgan.example@example.org' };
// The two inner folds of the people card, each holding one form; their own anchors scope the boxes, whose labels
// (Name, Email, Email address) would otherwise match in both.
const INVITE = { css: '#wpcpm-people-invite' };
const TUTOR = { css: '#wpcpm-tutors-add' };

export default {
  slug: 'invite-a-representative',
  caption: 'Invite a colleague of a school, and add a tutor to its list.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await applySchool(newContext, BASE, STATE_COLLEGE);
    await approveSchool(newContext, BASE, STATE_COLLEGE.name);
  },
  steps: [
    { caption: 'Open the school\'s Institution Dashboard, and in the menu choose Your institution.', go: () => `/institution-dashboard/?wpcpm_institution_view=${recordOf(STATE_COLLEGE.name)}`, hold: 1.0 },
    { press: { role: 'link', name: 'Your institution', expectUrl: 'wpcpm_view=institution' }, hold: 1.0 },
    { caption: 'Find the card Representatives and tutors.', scrollToControl: { css: 'summary', hasText: 'Representatives and tutors' }, offset: 72, hold: 0.4 },
    { pointerTo: { css: 'summary', hasText: 'Representatives and tutors' }, hold: 1.4 },
    { caption: 'Open Invite a colleague, and type the address in Email address.', press: { css: 'summary', hasText: 'Invite a colleague' }, hold: 0.6 },
    { type: { within: INVITE, label: 'Email address', exact: false, value: 'pat.example@example.org' }, hold: 0.8 },
    { caption: 'Under Level, choose Representative or Tutor.', press: { within: INVITE, role: 'radio', name: 'Tutor', exact: false }, hold: 1.2 },
    { caption: 'Press Send invitation.', press: { within: INVITE, role: 'button', name: 'Send invitation' }, hold: 0.4 },
    { waitFor: { text: 'The invitation is on its way', exact: false, scrollIntoView: true, offset: 160 }, hold: 2.8, poster: true },
    { caption: 'For the tutor list: under Tutors, open Add a tutor, type the Name and Email, then press Add.', press: { css: 'summary', hasText: 'Add a tutor' }, hold: 0.6 },
    { type: { within: TUTOR, label: 'Name', exact: false, value: 'Lee Example' }, hold: 0.3 },
    { type: { within: TUTOR, label: 'Email', exact: false, value: 'lee.example@example.org' }, hold: 0.4 },
    { press: { within: TUTOR, role: 'button', name: 'Add' }, hold: 0.4 },
    // The page comes back at the tutor list; the message is brought to a spot where no line is half under the toolbar.
    { scrollToControl: { text: 'The tutor was added to the list.', exact: false }, offset: 150, waitFor: { text: 'The tutor was added to the list.', exact: false }, park: 'clear', hold: 4.5 },
  ],
};
