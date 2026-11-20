// Clip: "Read the follow-ups due and open a school's talks" (docs/sections/61-admin-steps-institutions.md). The lines of
// text are the job's own steps. Off camera: the talks clips' invented world (_lib.mjs, talksWorld()).
import { talksWorld, TALKS_SCHOOLS } from './_lib.mjs';

export default {
  slug: 'read-the-follow-ups-due',
  caption: 'Read the follow-ups due and open a school\'s talks.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await talksWorld(newContext, BASE);
  },
  steps: [
    { caption: 'Open Administrator Dashboard > Talks.', go: '/administrator-dashboard/?wpcpm_view=talks', hold: 1.2 },
    { caption: 'Read the head. It says how many follow-ups are due, and which one is the oldest.', pointerTo: { css: '.wpcpm-head__title' }, hold: 2.4 },
    { pointerTo: { css: '.wpcpm-head__lede' }, hold: 2.0 },
    { caption: 'Read the table Follow-ups due: School, Next step and Follow up on, oldest first.', scrollToControl: { role: 'heading', name: 'Follow-ups due' }, offset: 56, hold: 0.4 },
    { pointerTo: { role: 'link', name: TALKS_SCHOOLS.polytechnic.name }, hold: 3.0, poster: true },
    // The stages are read where the table left the page: Lead, First Contact Made and Call Scheduled, the invented schools'.
    // Scrolled further, the copy's own schools (drawn under stand-in names) would come into the frame.
    { caption: 'Read the schools by stage under it, from Lead to Confirmed, each stage with its count.', pointerTo: { css: 'h2', hasText: 'First Contact Made:' }, hold: 3.0 },
    { caption: 'To see every Administrator\'s schools, press Show all schools. Show your schools goes back.', scrollToControl: { role: 'link', name: 'Show all schools' }, offset: 60, hold: 0.4 },
    { press: { role: 'link', name: 'Show all schools', expectUrl: 'wpcpm_talks_who=all' }, hold: 2.0 },
    { press: { role: 'link', name: 'Show your schools' }, hold: 1.4 },
    { caption: 'Press a school\'s name to open its Talks card.', scrollToControl: { role: 'heading', name: 'Follow-ups due' }, offset: 56, hold: 0.4 },
    { press: { role: 'link', name: TALKS_SCHOOLS.lakeside.name, expectUrl: 'wpcpm_view=administrators' }, land: { css: 'header.wpcpm-head' }, hold: 0.4 },
    // The view's head, its first row whole with 24px of air under the toolbar.
    { scrollToControl: { css: 'header.wpcpm-head' }, offset: 56, hold: 4.0 },
  ],
};
