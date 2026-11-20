// Clip: "Change a school's stage, next step and follow-up date" (docs/sections/61-admin-steps-institutions.md). The
// lines of text are the job's own steps. Off camera: the talks clips' invented world (_lib.mjs, talksWorld()); the
// school filmed, Example Valley College, has nothing typed yet, so each box is typed into empty.
import { talksWorld, talksCardPath, TALKS_SCHOOLS } from './_lib.mjs';

const CARD = { css: '#wpcpm-talks form' };

export default {
  slug: 'change-a-schools-stage',
  caption: 'Change a school\'s stage, next step and follow-up date on its Talks card.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await talksWorld(newContext, BASE);
  },
  steps: [
    { caption: 'Open the school\'s Talks card.', go: () => talksCardPath(TALKS_SCHOOLS.valley.name), hold: 0.4 },
    // The view's head, its first row whole with 24px of air under the toolbar.
    { scrollToControl: { css: 'header.wpcpm-head' }, offset: 56, hold: 1.2 },
    { caption: 'Change what moved on: the Stage,', choose: { within: CARD, css: 'select[name="stage"]', option: 'Call Scheduled' }, hold: 1.2 },
    { caption: 'the Next step,', type: { within: CARD, label: 'Next step', value: 'Send the sample agreement' }, hold: 0.8 },
    { caption: 'the day to Follow up on,', type: { within: CARD, label: 'Follow up on', day: 7 }, hold: 0.8 },
    { caption: 'and the day of the Last contact.', type: { within: CARD, label: 'Last contact', day: -1 }, hold: 1.0 },
    { caption: 'Press Save.', press: { within: CARD, role: 'button', name: 'Save' }, land: { css: 'header.wpcpm-head' }, hold: 0.4 },
    // The save comes back at the card's anchor; the view's head is brought down whole, 24px under the toolbar.
    { caption: 'The card says "The talks are saved."', waitFor: { text: 'The talks are saved.' }, scrollToControl: { css: 'header.wpcpm-head' }, offset: 56, hold: 3.0, poster: true },
    { caption: 'Under Stage, a line says when the stage last changed and who changed it.', pointerTo: { css: '#wpcpm-talks-stage-hint' }, hold: 3.6 },
    { park: 'clear', hold: 2.0 },
  ],
};
