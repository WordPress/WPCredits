// The local copy, and nothing else: its address, its WP-CLI, sign-in cookies made in memory.
// Every helper here refuses any site but http://localhost:8900.
import { execFileSync } from 'node:child_process';
import { homedir } from 'node:os';
import { join } from 'node:path';

export const BASE = 'http://localhost:8900';
export const DOCKER = join(homedir(), 'Projects/wpcredits-docker');
const WP = join(DOCKER, 'bin/wp');

/** Run WP-CLI on the copy; returns stdout's last non-empty line unless all is asked for. */
export function wp(args, { all = false } = {}) {
  const out = execFileSync(WP, args, { cwd: DOCKER, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], maxBuffer: 64 * 1024 * 1024 });
  if (all) return out;
  const lines = out.split('\n').map(s => s.trim()).filter(Boolean);
  return lines.length ? lines[lines.length - 1] : '';
}

/** Stop unless the copy answers as http://localhost:8900, and the filming plugin is loaded. */
export function refuseUnlessCopy(base = BASE) {
  if (base !== BASE) throw new Error(`refusing: ${base} is not ${BASE}`);
  const siteurl = wp(['option', 'get', 'siteurl']);
  const home = wp(['option', 'get', 'home']);
  if (siteurl !== BASE || home !== BASE) throw new Error(`refusing: the copy's address is not ${BASE}`);
}

export function filmingLoaded() {
  return wp(['eval', 'echo defined("WPCPM_FILMING") ? "yes" : "no";']) === 'yes';
}

/** Sign-in cookies for one account of the copy, by login, made by WordPress itself; kept in memory only. */
export function cookiesFor(login) {
  if (!/^[a-z0-9._@+-]+$/i.test(login)) throw new Error('bad login');
  const php = `$u = get_user_by( 'login', '${login}' ); if ( ! $u ) { echo 'none'; return; } $e = time() + 7200; echo wp_json_encode( array( array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $u->ID, $e, 'logged_in' ), 'path' => '/' ), array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $u->ID, $e, 'auth' ), 'path' => '/wp-admin' ), array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $u->ID, $e, 'auth' ), 'path' => '/wp-content/plugins' ) ) );`;
  const out = wp(['eval', php]);
  if (out === 'none' || !out.startsWith('[')) throw new Error(`no account ${login} on the copy`);
  return JSON.parse(out).map(c => ({ ...c, domain: 'localhost', httpOnly: true, sameSite: 'Lax', expires: Math.floor(Date.now() / 1000) + 7200 }));
}

/**
 * Words that name the person running the rig, read from this machine (git's user name and address), so no file
 * holds them: a frame that shows one is refused like a real name. Extra words may come in CLIPS_REFUSE_EXTRA,
 * comma-separated.
 */
export function ownerWords() {
  const git = k => { try { return execFileSync('git', ['config', k], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim(); } catch { return ''; } };
  const name = git('user.name'), mail = git('user.email');
  const words = [...name.split(/\s+/), ...(mail.includes('@') ? mail.split('@') : [])];
  words.push(...String(process.env.CLIPS_REFUSE_EXTRA || '').split(','));
  return [...new Set(words.map(w => w.trim()).filter(w => w.length >= 3))];
}
