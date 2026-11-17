#!/usr/bin/env node
// Refreshes the layout harness's global styles fixture from the theme folder it is pointed at.
//
//   WPCPM_THEME_DIR=<theme folder> node bin/ui/layout/refresh-global-styles.mjs
//
// WordPress prints what a dashboard page's head carries from theme.json; this asks WordPress itself, on the
// Docker copy, to print it for that theme folder's theme.json, and writes the result beside the harness:
//
//   global-styles.css   the global styles block (variables, presets, element and layout styles, the custom CSS
//                       and the block nodes of the blocks a dashboard page renders), as the front end prints it
//   block-library.css   the block library's sheets for those blocks and its shared sheet (common.css)
//   global-styles.json  what both were made from: the theme.json's sha256, the theme's version, WordPress's and
//                       Gutenberg's versions, the blocks, the files read
//
// The copy is only read: global-styles.php is piped to the copy's WP-CLI (`bin/wp eval-file -`) with the
// copy's theme and every plugin but Gutenberg left out, and stops itself on any database query that is not a
// read. Nothing is written on the copy, and the copy's own theme is never asked: the theme.json printed is the
// one in WPCPM_THEME_DIR. The copy's WP-CLI is found through WPCPM_DOCKER, with the home folder's default.
import { createHash } from 'node:crypto';
import { existsSync, readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
// The core blocks a dashboard page's template renders around the plugin's own block: the template parts
// (wporg-header, wporg-footer), the main group and the post content (templates/page-*-dashboard.html).
export const PAGE_BLOCKS = ['core/template-part', 'core/group', 'core/post-content'];

export function sha256(file) {
	return createHash('sha256').update(readFileSync(file)).digest('hex');
}

function themeVersion(themeDir) {
	const m = /^\s*Version:\s*(\S+)/m.exec(readFileSync(join(themeDir, 'style.css'), 'utf8'));
	return m ? m[1] : '';
}

function main() {
	const themeDir = resolve(process.env.WPCPM_THEME_DIR || join(homedir(), 'GitHub/wpcredits-theme'));
	const docker = resolve(process.env.WPCPM_DOCKER || join(homedir(), 'Projects/wpcredits-docker'));
	const themeJson = join(themeDir, 'theme.json');
	if (!existsSync(themeJson)) throw new Error('no theme.json in the theme folder: ' + themeDir);
	// Style variation partials (styles/*.json) are read from the active theme's folder on the copy, not from
	// this one: a theme that has them would be printed wrong, so the refresh stops.
	if (existsSync(join(themeDir, 'styles'))) throw new Error('the theme has a styles/ folder of variations, which this refresh does not cover');
	const wp = join(docker, 'bin/wp');
	if (!existsSync(wp)) throw new Error('no WP-CLI for the copy at ' + wp + ' (set WPCPM_DOCKER)');

	const plugins = readdirSync(join(docker, 'site/wp-content/plugins'), { withFileTypes: true })
		.map(d => d.name.replace(/\.php$/, ''))
		.filter(n => n !== 'gutenberg' && n !== 'index');
	const input = { theme_json: readFileSync(themeJson, 'utf8'), blocks: PAGE_BLOCKS };
	const script = '<?php $wpcpm_gs_input = json_decode( base64_decode( \'' + Buffer.from(JSON.stringify(input)).toString('base64') + '\' ), true ); ?>'
		+ readFileSync(join(HERE, 'global-styles.php'), 'utf8');
	const run = spawnSync(wp, ['eval-file', '-', '--skip-themes', '--skip-plugins=' + plugins.join(',')], { input: script, encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
	const line = (run.stdout || '').split('\n').find(l => l.startsWith('WPCPM-GS '));
	if (run.status !== 0 || !line) throw new Error('the copy did not print the global styles (exit ' + run.status + '):\n' + (run.stderr || '') + (run.stdout || '').slice(0, 2000));
	const out = JSON.parse(Buffer.from(line.slice('WPCPM-GS '.length), 'base64').toString('utf8'));

	const library = out.library.map(s => `/* ${s.handle}: ${s.file} */\n${s.css.trim()}\n`).join('\n');
	const head = '/* Written by bin/ui/layout/refresh-global-styles.mjs from the theme.json named in global-styles.json; never edit by hand. */\n';
	const files = { 'block-library.css': head + library, 'global-styles.css': head + out.global_styles.trim() + '\n' };
	for (const [name, text] of Object.entries(files)) {
		// The harness holds no site address, no home folder and no long dash; WordPress's sheets must not either.
		if (/https?:\/\/(?!www\.w3\.org)/.test(text) || /\/Users\/|\/home\/[a-z]/.test(text) || /[\u2013\u2014]/.test(text)) throw new Error(name + ' would hold a site address, a home folder or a long dash; not written');
	}
	const source = {
		made_by: 'WPCPM_THEME_DIR=<theme folder> node bin/ui/layout/refresh-global-styles.mjs',
		theme_json_sha256: sha256(themeJson),
		theme_version: themeVersion(themeDir),
		wordpress: out.wordpress,
		gutenberg: out.gutenberg,
		blocks: out.blocks,
		block_nodes: out.block_nodes,
		block_library: out.library.map(s => ({ handle: s.handle, file: s.file })),
		custom_css_bytes: out.custom_css,
		made: new Date().toISOString().slice(0, 10),
	};
	for (const [name, text] of Object.entries(files)) writeFileSync(join(HERE, name), text);
	writeFileSync(join(HERE, 'global-styles.json'), JSON.stringify(source, null, '\t') + '\n');
	console.log(`global styles written for theme ${source.theme_version} (theme.json ${source.theme_json_sha256.slice(0, 12)}), WordPress ${source.wordpress}, Gutenberg ${source.gutenberg || 'none'}: ${files['global-styles.css'].length} + ${files['block-library.css'].length} bytes`);
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	try { main(); } catch (e) { console.error(String(e && e.message || e)); process.exit(2); }
}
