// Clip: "A signed Collaboration Agreement arrived" (docs/sections/61-admin-steps-institutions.md).
// Off camera: an invented school applies, confirms its address and is approved (as in a-school-applied); its own
// account then generates the program's template on its Institution Dashboard, saves it as a PDF and uploads it
// as signed. The clip is the Administrator's review and Accept it.
import { join } from 'node:path';
import { mkdirSync } from 'node:fs';
import { applySchool, approveSchool, uploadAgreement, loginOf } from './_lib.mjs';

export const EXAMPLE_U = { name: 'Example University', city: 'Exampleton', website: 'university.example.org', contact: 'Jordan Example', email: 'jordan.example@example.org', signer: 'Robin Example' };
const CARD = { css: 'section', hasText: 'Collaboration Agreements' };

export default {
  slug: 'a-signed-agreement-arrived',
  caption: 'A signed Collaboration Agreement arrived: review it on the Overview and accept it.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) {
    await applySchool(newContext, BASE, EXAMPLE_U);
    await approveSchool(newContext, BASE, EXAMPLE_U.name);
    const dir = new URL('../../../.superpowers/guides/clips/work/', import.meta.url).pathname;
    mkdirSync(dir, { recursive: true });
    const said = await uploadAgreement(newContext, BASE, loginOf(EXAMPLE_U.email), join(dir, 'example-university-agreement.pdf'), EXAMPLE_U.name);
    if (!said.includes('The signed agreement is uploaded')) throw new Error('the upload was not taken');
  },
  steps: [
    { caption: 'Open the Collaboration Agreements card and find the school tagged Awaiting review.', go: '/administrator-dashboard/', hold: 0.8 },
    { scrollToControl: { role: 'heading', name: 'Waiting for you', exact: false }, offset: 70, hold: 0.5 },
    { press: { within: { css: 'summary', hasText: 'Collaboration Agreements' }, text: 'Review', exact: true }, hold: 0.8 },
    { pointerTo: { within: CARD, text: 'Awaiting review', exact: true }, hold: 1.6 },
    { pointerTo: { within: CARD, text: 'Example University', exact: true }, hold: 1.8 },
    { caption: 'Press Download the signed agreement and open the file in your own PDF viewer.', scrollToControl: { within: CARD, css: 'a, button', hasText: 'Download the signed agreement' }, offset: 300, hold: 0.3 },
    { press: { within: CARD, css: 'a, button', hasText: 'Download the signed agreement' }, hold: 2.2 },
    { caption: 'Check it against the list under "Review the signed agreement from Example University".', scrollToControl: { within: CARD, text: 'Review the signed agreement from Example University', exact: false }, offset: 50,
      // The review must print the name the school typed while generating; the shot stops if it does not.
      waitFor: { text: /^The name on the document is Example University\. Airtable.s Name for this institution is Example University\.$/ },
      hold: 4.2, poster: true },
    { caption: 'If it is right, press Accept it and confirm.', press: { within: CARD, role: 'button', name: 'Accept it' }, confirm: { read: 3.4 }, moveMs: 800, hold: 0.8 },
    { caption: 'Accept it sets Agreement Status to Accepted in Airtable, and Current Stage to Confirmed when the stage was earlier. The school\'s account opens.',
      waitFor: { text: 'accepted', exact: false, scrollIntoView: true, offset: 120 }, park: 'clear', hold: 5 },
  ],
};
