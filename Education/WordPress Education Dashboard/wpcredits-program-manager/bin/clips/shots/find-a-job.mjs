// Clip: find a job in the Administrator guide, from a dashboard card and with the guide's search
// (docs/sections/60-admin-start.md). Needs one waiting institution application, so the card is drawn:
// film it on the copy as a-school-applied's prepare leaves it, before that clip's Approve.
import { applySchool } from './_lib.mjs';
import { NORTHFIELD } from './a-school-applied.mjs';

export default {
  slug: 'find-a-job',
  caption: 'Find a job in the guide: from a card\'s How to do this link, or with Search the guide.',
  login: 'wpcredits-local',
  async prepare({ newContext, BASE }) { await applySchool(newContext, BASE, NORTHFIELD); },
  steps: [
    { caption: 'Each card that waits for a decision has a How to do this link, which opens that job in this guide.', go: '/administrator-dashboard/', hold: 1.6 },
    { scrollToControl: { role: 'heading', name: 'Waiting for you', exact: false }, offset: 70, hold: 0.6 },
    { press: { role: 'link', name: /^How to do this\W+Institution applications$/, expectUrl: '/administrator-guide/#a-school-applied$' }, moveMs: 900, hold: 0.4 },
    { caption: 'The guide opens at the job A school applied.', waitFor: { role: 'heading', name: 'A school applied' }, park: 'clear', hold: 3.6, poster: true },
    { caption: 'To find a job in this guide, use Search the guide (press / to reach it), or the list On this page beside the guide.', key: '/', hold: 0.6 },
    { type: { role: 'searchbox', name: 'Search the guide', exact: false, value: 'agreement', focused: true }, hold: 2.8 },
    { caption: 'Press the first result. The guide goes to that job and marks the words.', press: { css: 'a', hasText: 'A signed Collaboration Agreement arrived', exact: false }, moveMs: 800, hold: 0.3 },
    { waitFor: { role: 'heading', name: 'A signed Collaboration Agreement arrived' }, park: 'clear', hold: 4.2 },
  ],
};
