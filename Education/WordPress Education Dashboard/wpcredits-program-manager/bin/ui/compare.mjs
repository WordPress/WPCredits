#!/usr/bin/env node
// Compares two picture sets file by file, by name: the same when their sizes match and ffmpeg's psnr reads
// average:inf (not a pixel differs). Needs no site and no browser.
//
//   node bin/ui/compare.mjs <before folder> <after folder>
//
// Prints `same <file>`, `DIFFERS <file> (psnr <value>)` or `MISSING <file>` for each name in either folder,
// then `N differ` (the files that differ or are missing), and exits 1 when any differs or is missing.
// ffmpeg and ffprobe are WPCPM_FFMPEG and WPCPM_FFPROBE, or the ones on the path.
import { execFileSync, spawnSync } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { join, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const FFMPEG = process.env.WPCPM_FFMPEG || 'ffmpeg';
const FFPROBE = process.env.WPCPM_FFPROBE || 'ffprobe';

/** The verdict on two images from ffmpeg's psnr line and their sizes (`<width>x<height>`). */
export function verdict(psnrLine, sizeA, sizeB) {
  if (!sizeA || sizeA !== sizeB) return 'differs';
  return /average:inf\b/.test(String(psnrLine)) ? 'same' : 'differs';
}

function size(file) {
  return execFileSync(FFPROBE, ['-v', 'error', '-select_streams', 'v:0', '-show_entries', 'stream=width,height', '-of', 'csv=s=x:p=0', file], { encoding: 'utf8' }).trim();
}

function psnr(a, b) {
  const run = spawnSync(FFMPEG, ['-hide_banner', '-i', a, '-i', b, '-lavfi', 'psnr', '-f', 'null', '-'], { encoding: 'utf8' });
  return (String(run.stderr || '').split('\n').find(l => l.includes('average:')) || '').trim();
}

function main(argv) {
  const [before, after] = argv.map(p => resolve(p));
  if (!before || !after || !existsSync(before) || !existsSync(after)) {
    console.error('usage: node bin/ui/compare.mjs <before folder> <after folder>');
    return 2;
  }
  const pngs = dir => readdirSync(dir).filter(f => f.endsWith('.png'));
  const names = [...new Set([...pngs(before), ...pngs(after)])].sort();
  let differ = 0;
  for (const name of names) {
    const a = join(before, name);
    const b = join(after, name);
    if (!existsSync(a) || !existsSync(b)) { differ++; console.log(`MISSING ${name}`); continue; }
    const sa = size(a);
    const sb = size(b);
    const line = sa === sb ? psnr(a, b) : '';
    if (verdict(line, sa, sb) === 'same') { console.log(`same ${name}`); continue; }
    differ++;
    const value = sa !== sb ? `n/a, sizes ${sa} and ${sb}` : ((line.match(/average:(\S+)/) || [])[1] || 'unread');
    console.log(`DIFFERS ${name} (psnr ${value})`);
  }
  console.log(`${differ} differ`);
  return differ ? 1 : 0;
}

if (process.argv[1] && import.meta.url === pathToFileURL(resolve(process.argv[1])).href) {
  process.exit(main(process.argv.slice(2)));
}
