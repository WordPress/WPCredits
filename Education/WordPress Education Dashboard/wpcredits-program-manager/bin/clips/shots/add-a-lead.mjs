// Clip: "Add a lead" (docs/sections/61-admin-steps-institutions.md). The lines of text are the job's own steps. Off
// camera: the talks clips' invented world (_lib.mjs, talksWorld()). The lead filmed is invented here.
import { talksWorld } from './_lib.mjs';

const FOLD = { css: '#wpcpm-add-lead' };
const LEAD = 'Example Harbor College';

export default {
  slug: 'add-a-lead',
  caption: 'Add a lead: a school the program has not talked to yet.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await talksWorld(newContext, BASE);
  },
  steps: [
    { caption: 'Open Administrator Dashboard > Talks.', go: '/administrator-dashboard/?wpcpm_view=talks', hold: 1.2 },
    { caption: 'Press Add a lead.', press: { role: 'link', name: 'Add a lead' }, land: { css: '#wpcpm-add-lead summary' }, hold: 1.0 },
    { caption: 'Type the school\'s Name. It is required.', type: { within: FOLD, label: 'Name', exact: false, value: LEAD }, hold: 0.6 },
    { caption: 'Fill in what you know, such as the City, the Next step and the day to Follow up on.', type: { within: FOLD, label: 'City', value: 'Harborview' }, hold: 0.3 },
    { type: { within: FOLD, label: 'Next step', value: 'Write to the internship office' }, hold: 0.3 },
    { type: { within: FOLD, label: 'Follow up on', day: 5 }, hold: 0.8 },
    { caption: 'Press Add the lead.', press: { within: FOLD, role: 'button', name: 'Add the lead' }, land: { css: 'header.wpcpm-head' }, hold: 0.4 },
    { caption: 'The lead\'s page opens with "The lead is added." It is at the stage Lead.', waitFor: { text: 'The lead is added.', exact: true, scrollIntoView: true, offset: 140 }, hold: 3.0, poster: true },
    { caption: 'On the Talks view, it is in the first list, Lead.', go: '/administrator-dashboard/?wpcpm_view=talks', hold: 0.4 },
    { scrollToControl: { css: 'h2', hasText: 'Lead:' }, offset: 90, hold: 0.4 },
    { pointerTo: { role: 'link', name: LEAD }, hold: 3.4 },
  ],
};
