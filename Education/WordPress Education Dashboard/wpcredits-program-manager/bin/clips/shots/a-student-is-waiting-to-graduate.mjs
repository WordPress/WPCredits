// Clip: "A student is waiting to graduate" (docs/sections/62-admin-steps-students.md). Only the Administrator
// Dashboard: the Overview's row, the card opened, the pointer on a student's name and on the track (neither is
// followed: they open the Student Report Card and the course on Learn), and How to do this. Nothing is pressed that
// changes anything; the card has no button. The job's later steps (the Student Report Card, the quiz on Learn, the
// graduation date in Airtable) are not filmed: the clip ends on the guide's words for them.
//
// Off camera, with no sync: the copy's own pending roster rows move to Graduate, and three invented students at
// Pending graduation in two invented schools are written into the roster index with their stamps
// (bin/clips/pending-students.php).
import { copyFileSync, mkdirSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';
import { DOCKER, wp } from '../copy.mjs';

const CARD = { css: 'section', hasText: 'Students waiting to graduate' };

export default {
  slug: 'a-student-is-waiting-to-graduate',
  caption: 'A student is waiting to graduate: open the card, read who waits, and find the Student Report Card and the course.',
  login: 'wpcredits-local',
  async prepare() {
    const stage = join(DOCKER, 'release/clips');
    mkdirSync(stage, { recursive: true });
    const file = join(stage, 'pending-students.php');
    copyFileSync(join(dirname(fileURLToPath(import.meta.url)), '..', 'pending-students.php'), file);
    const said = wp(['eval-file', file], { all: true });
    process.stdout.write(said);
    if (/refusing|NOT made/.test(said) || (said.match(/^pending student /gm) || []).length !== 3) throw new Error('the pending students were not made');
  },
  steps: [
    // 1. Open the Administrator Dashboard and open the card Students waiting to graduate. The top row has waited
    //    longest. Each student's institution is under their name.
    { caption: 'Open the Administrator Dashboard and open the card Students waiting to graduate. The top row has waited longest. Each student\'s institution is under their name.',
      go: '/administrator-dashboard/', hold: 1.2 },
    { scrollToControl: { role: 'heading', name: 'Waiting for you', exact: false }, offset: 70, hold: 0.5 },
    { pointerTo: { within: { css: 'summary', hasText: 'Students waiting to graduate' }, text: /days? waiting$/i }, hold: 1.6 },
    { press: { within: { css: 'summary', hasText: 'Students waiting to graduate' }, text: 'Review', exact: true }, hold: 1.0 },
    { pointerTo: { within: CARD, text: /^These students have finished/ }, hold: 2.6 },
    { pointerTo: { within: CARD, role: 'link', name: 'Morgan Example' }, hold: 1.4 },
    { pointerTo: { within: CARD, text: 'Example University', exact: true }, hold: 1.8, poster: true },
    // 2. The name opens the Student Report Card: pointed at, not followed.
    { caption: 'Press the student\'s name to open their Student Report Card, and read their report and hours.', pointerTo: { within: CARD, role: 'link', name: 'Avery Example' }, hold: 2.0 },
    { pointerTo: { within: CARD, text: '152 of 150', exact: true }, hold: 1.6 },
    // 3. The mentor.
    { caption: 'If something is unclear, ask the mentor named in Mentor.', pointerTo: { within: CARD, text: 'Harper Example', exact: true }, hold: 2.6 },
    // 4. The track opens the course on Learn: pointed at, not followed.
    { caption: 'Press the course in Track to open it on Learn, and approve the student\'s quiz there, so the certificate goes out.', pointerTo: { within: CARD, role: 'link', name: 'WordPress Credits Program 150h', nth: 1 }, hold: 3.0 },
    // 5. Where the job goes on: Airtable, not filmed.
    { caption: 'In Airtable, set the Graduation date on the student\'s Students Reports row.', park: 'clear', hold: 3.0 },
    // How to do this.
    { caption: 'Each card that waits for a decision has a How to do this link, which opens that job in this guide.', pointerTo: { role: 'link', name: /^How to do this\W+Students waiting to graduate$/, onWords: true }, hold: 3.2 },
  ],
};
