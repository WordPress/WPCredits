// Clip: "Add a note to a school's talks" (docs/sections/61-admin-steps-institutions.md). The lines of text are the
// job's own steps. Off camera: the talks clips' invented world (_lib.mjs, talksWorld()); Example Lakeside College
// holds two notes already, so the new one is seen first in a list.
import { talksWorld, talksCardPath, TALKS_SCHOOLS } from './_lib.mjs';

const NOTE = 'Called the coordinator: the senate decides in two weeks.';

export default {
  slug: 'add-a-note-to-a-schools-talks',
  caption: 'Add a note to a school\'s talks.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await talksWorld(newContext, BASE);
  },
  steps: [
    { caption: 'Open the school\'s Talks card.', go: () => talksCardPath(TALKS_SCHOOLS.lakeside.name), hold: 0.4 },
    // The view's head, its first row whole with 24px of air under the toolbar.
    { scrollToControl: { css: 'header.wpcpm-head' }, offset: 56, hold: 1.2 },
    { caption: 'Under Notes, type in Add a note.', scrollToControl: { role: 'heading', name: 'On record' }, offset: 56, hold: 1.2 },
    { type: { label: 'Add a note', value: NOTE }, hold: 0.8 },
    { caption: 'Press Add the note.', press: { role: 'button', name: 'Add the note' }, land: { role: 'heading', name: 'On record' }, hold: 0.4 },
    { caption: 'The card says "The note is added." The note is first under Notes, with your name and the moment.', waitFor: { text: 'The note is added.', exact: true, scrollIntoView: true, offset: 140 }, hold: 1.2 },
    { pointerTo: { text: NOTE }, hold: 3.6, poster: true },
    { park: 'clear', hold: 2.0 },
  ],
};
