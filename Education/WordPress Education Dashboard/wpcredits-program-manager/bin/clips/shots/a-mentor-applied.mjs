// Clip: "A mentor applied" (docs/sections/63-admin-steps-mentors.md).
// Off camera: an invented person applies through the public Become a mentor form and confirms the address with
// the link in the acknowledgement. Setting switched for the clip: Mentors' course invitation link, which is empty
// on the copy and without which Select is refused, is set to an invented page on the copy.
import { applyMentor, setSetting } from './_lib.mjs';

export const TAYLOR = { name: 'Taylor Example', email: 'taylor.example@example.org', profile: 'https://profiles.wordpress.org/taylor-example/', hours: 4 };

export default {
  slug: 'a-mentor-applied',
  caption: 'A mentor applied: read the application in the Waiting view and select the mentor.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    setSetting('mentor_course_link', 'http://localhost:8900/example-mentor-course/');
    await applyMentor(newContext, BASE, TAYLOR);
  },
  steps: [
    { caption: 'Press Review on the Mentor applications row, or open WPCredits Program > Mentors.', go: '/administrator-dashboard/', hold: 0.8 },
    { scrollToControl: { role: 'heading', name: 'Waiting for you', exact: false }, offset: 70, hold: 0.5 },
    { pointerTo: { text: 'Mentor applications', exact: false }, hold: 1.2 },
    { press: { within: { css: 'section', hasText: 'Mentor applications' }, role: 'link', name: 'Review', exact: false }, hold: 1.0 },
    { caption: 'In the Waiting view, read the oldest application: the line under the name, its marks, its answers and its History.', pointerTo: { role: 'link', name: 'Waiting (1)', exact: false }, hold: 1.4 },
    { scrollToControl: { text: 'Taylor Example', exact: true }, offset: 120, hold: 0.4 },
    { pointerTo: { text: /^Waiting .+, since /, exact: false }, hold: 2.0, poster: true },
    { scrollToControl: { text: 'Languages in which you could mentor students', exact: false }, offset: 200, hold: 2.2 },
    { scrollToControl: { role: 'heading', name: 'History' }, offset: 160, hold: 2.2 },
    { caption: 'Select sends the selection email with the mentors\' course link.', scrollToControl: { role: 'button', name: 'Select' }, offset: 260, hold: 0.8 },
    { press: { role: 'button', name: 'Select' }, confirm: { read: 3.0, caption: 'For Select, Decline and Mark as spam, accept the question the browser asks.' }, hold: 0.8 },
    { caption: 'Read the notice at the top: it names the applicant and the reference and says what was sent.', waitFor: { text: 'MAPP-2026', exact: false, scrollIntoView: true, offset: 150 }, park: 'clear', hold: 5 },
  ],
};
