#!/usr/bin/env node
// Writes ui-audit/index.md from the results.json snap.mjs leaves: one row per file, then "Could not capture".
//   node bin/clips/snap-index.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const OUT = join(resolve(dirname(fileURLToPath(import.meta.url)), '../..'), '.superpowers/guides/ui-audit');
const rows = JSON.parse(readFileSync(join(OUT, 'results.json'), 'utf8'));
const cell = s => String(s || '').replace(/\|/g, '\\|').replace(/\n/g, ' ');
const kept = rows.filter(r => !r.skipped);
const lost = rows.filter(r => r.skipped);

let md = `# UI audit screenshots\n\n`;
md += `Plugin 1.123.88 (md5 5259d9bfcb1d4f07102ac4fe03bfc1fc) on the Docker copy at http://localhost:8900, scrubbed. Every invented school and person is at example.org. Real institutions' and sponsors' names and school websites are replaced in the page text by the filming plugin ("Sample College A" to "J", "sample-college.example.org"). The copy's two banners are left on the page. Nothing is drawn over the page.\n\n`;
md += `Signed in as the local Administrator unless a row says otherwise. Two sizes per screen: 1280 wide at scale 1, and 390 wide at scale 2 (phone). Full page height; a page taller than 16,000 pixels is cut into parts.\n\n`;
md += `Prepared through the site's own forms (\`bin/clips/snap.mjs\`; see the next section).\n\n`;
md += `| File | Path | What is open | State prepared or found | How it was reached |\n|---|---|---|---|---|\n`;
for (const r of kept) md += `| ${cell(r.file)} | \`${cell(r.path)}\` | ${cell(r.open)} | ${cell(r.prepared)} | ${cell(r.how)} |\n`;
md += `\n## Notes on what is on the pages\n\n`;
md += `- **"all-open" shots:** every closed fold on the page was pressed, including the Administrator account menu in the site header, so its "Log out" list shows open.\n`;
md += `- **Mentor applications:** the Overview draws them as one row with no fold (Review leads to wp-admin). That row is in the Overview shots; the application itself is in \`wpadmin-mentors-queue-application-open-*\`.\n`;
md += `- **Settings > Connection:** the Personal Access Token field shows dots. The copy stores no token; the dots are the filming plugin's invented placeholder, which it adds in memory only.\n`;
md += `- **Prepared state:**\n`;
md += `  - Example School of Design: applied, approved, agreement uploaded by its own account and accepted, two students enrolled (each raised "A student to add"), the January to June 2026 report drafted, an invitation waiting, a tutor listed.\n`;
md += `  - Example University: approved, signed agreement uploaded and waiting.\n`;
md += `  - Northfield College: application waiting, address confirmed.\n`;
md += `  - Taylor Example: mentor application waiting, address confirmed.\n`;
md += `  - Setting switched: Enrollment lists from institutions, on.\n`;
md += `  - Everything else (sponsors, students, mentors, the program's figures) is the copy's own data, scrubbed and with names replaced.\n`;
md += `\n## Could not capture\n\n`;
md += `- **Cards the dashboard does not draw on this copy, and why:**\n`;
md += `  - Sponsor applications, Sponsor offers to review, Events, Sponsor Collaboration Agreements, Sponsor reports: the Overview draws a card only while something waits, and on the copy these queues are empty. The brief said not to prepare new sponsor data. Their empty state is the sentence "Nothing waits in the N other queues" in the Overview shots.\n`;
md += `  - Students gone quiet: not drawn on Watch on this copy (no student is quiet, or the list is off).\n`;
md += `- **Not prepared, so not shown with an item:** a student registration (the form stays closed on the copy: the languages are never read from Airtable) and a student interest. The Registrations and Interests tabs are captured empty or as the copy holds them.\n`;
md += `- **The guide:** the whole page is too long for one image. Each shot is a part of the page: the first screen, and about two screens from the job "A school applied".\n`;
if (!lost.length) md += `- No other screen was refused by the name check after the fixes.\n`;
for (const r of lost) md += `- ${cell(r.file)} (\`${cell(r.path)}\`): ${cell(r.skipped)}\n`;
writeFileSync(join(OUT, 'index.md'), md);
console.log(`index.md: ${kept.length} rows, ${lost.length} not captured`);
