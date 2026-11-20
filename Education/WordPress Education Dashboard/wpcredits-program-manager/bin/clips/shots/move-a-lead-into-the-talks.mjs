// Clip: "Move a lead into the talks as an institution" (docs/sections/61-admin-steps-institutions.md). The lines of
// text are the job's own steps. Off camera: the talks clips' invented world (_lib.mjs, talksWorld()); the lead filmed,
// Example Riverside Institute, has its country and a note, so the move needs no country chosen and the note is seen
// going with it. The filming plugin answers the new record's create and the copy after it.
import { talksWorld, leadPathOf, TALKS_LEADS } from './_lib.mjs';

const CARD = { css: '#wpcpm-talks form' };

export default {
  slug: 'move-a-lead-into-the-talks',
  caption: 'Move a lead into the talks as an institution.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await talksWorld(newContext, BASE);
  },
  steps: [
    { caption: 'Open the lead\'s page.', go: () => leadPathOf(TALKS_LEADS.riverside.name), hold: 1.6 },
    { caption: 'In Stage, choose where the talks are: First Contact Made to Agreement Sent.', choose: { within: CARD, css: 'select[name="stage"]', option: 'First Contact Made' }, hold: 1.4 },
    { caption: 'Press Save.', press: { within: CARD, role: 'button', name: 'Save' }, land: { css: 'header.wpcpm-head' }, hold: 0.4 },
    { caption: 'The card says "The lead is an institution now. Its talks go on here."', waitFor: { text: 'The lead is an institution now. Its talks go on here.', exact: true }, scrollToControl: { css: 'header.wpcpm-head' }, offset: 56, hold: 3.2, poster: true },
    { caption: 'The lead\'s fields, notes and history go with it.', scrollToControl: { role: 'heading', name: 'On record' }, offset: 56, hold: 0.4 },
    { pointerTo: { text: TALKS_LEADS.riverside.note }, hold: 3.6 },
    { park: 'clear', hold: 1.6 },
  ],
};
