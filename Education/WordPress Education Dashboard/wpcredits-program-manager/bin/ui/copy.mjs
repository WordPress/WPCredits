// The local Docker copy, and nothing else: its address, its WP-CLI, sign-in cookies made in memory.
// Every helper here refuses any site but BASE. The Docker folder is WPCPM_DOCKER, or
// ~/Projects/wpcredits-docker when it is not set.
import { execFileSync } from 'node:child_process';
import { copyFileSync, existsSync, readFileSync } from 'node:fs';
import { homedir } from 'node:os';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

export const BASE = 'http://localhost:8900';
export const DOCKER = process.env.WPCPM_DOCKER || join(homedir(), 'Projects/wpcredits-docker');
const WP = DOCKER + '/bin/wp';

/** Run WP-CLI on the copy; answers stdout's last non-empty line, or all of it when `all` is asked for. */
export function wp(args, { all = false } = {}) {
  const out = execFileSync(WP, args, { cwd: DOCKER, encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'], maxBuffer: 64 * 1024 * 1024 });
  if (all) return out;
  const lines = out.split('\n').map(s => s.trim()).filter(Boolean);
  return lines.length ? lines[lines.length - 1] : '';
}

/**
 * Stop unless the base asked for is BASE and the copy answers with BASE as both its siteurl and its home.
 * `ask` is how the copy is asked, WP-CLI by default; the suite hands it a stand-in.
 */
export function refuseUnlessCopy(base = BASE, ask = wp) {
  if (base !== BASE) throw new Error(`refusing: ${base} is not ${BASE}`);
  const siteurl = ask(['option', 'get', 'siteurl']);
  const home = ask(['option', 'get', 'home']);
  if (siteurl !== BASE || home !== BASE) throw new Error(`refusing: the copy's siteurl and home are not both ${BASE}`);
}

/** Whether the rig's must-use plugin (wpcpm-ui-rig.php, put in place by copy.sh prepare) is loaded. */
export function rigLoaded() {
  return wp(['eval', 'echo defined("WPCPM_UI_RIG") ? "yes" : "no";']) === 'yes';
}

/** Sign-in cookies for one account of the copy, by login, made by WordPress itself; kept in memory only. */
export function cookiesFor(login) {
  if (!/^[a-z0-9._@+-]+$/i.test(login)) throw new Error('bad login');
  const php = `$u = get_user_by( 'login', '${login}' ); if ( ! $u ) { echo 'none'; return; } $e = time() + 7200; echo wp_json_encode( array( array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $u->ID, $e, 'logged_in' ), 'path' => '/' ), array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $u->ID, $e, 'auth' ), 'path' => '/wp-admin' ), array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $u->ID, $e, 'auth' ), 'path' => '/wp-content/plugins' ) ) );`;
  const out = wp(['eval', php]);
  if (out === 'none' || !out.startsWith('[')) throw new Error(`no account ${login} on the copy`);
  return JSON.parse(out).map(c => ({ ...c, domain: 'localhost', httpOnly: true, sameSite: 'Lax', expires: Math.floor(Date.now() / 1000) + 7200 }));
}

/** The copy's own Administrator account, by login: WPCPM_UI_LOGIN, or the copy's wpcredits-local. */
export const ADMIN_LOGIN = process.env.WPCPM_UI_LOGIN || 'wpcredits-local';

/**
 * Put this repository's must-use plugin in place of the copy's when they differ (an edit made after
 * copy.sh prepare), once the copy has been prepared; answers whether it was refreshed. Only the rig's own file.
 */
export function refreshRig() {
  const mine = join(dirname(fileURLToPath(import.meta.url)), 'wpcpm-ui-rig.php');
  const theirs = join(DOCKER, 'site/wp-content/mu-plugins/wpcpm-ui-rig.php');
  if (!existsSync(theirs) || readFileSync(mine, 'utf8') === readFileSync(theirs, 'utf8')) return false;
  copyFileSync(mine, theirs);
  return true;
}

/** The rig's frozen moment (seconds) stored on the copy, or 0. */
export function frozenNow() {
  try { return Number(wp(['option', 'get', 'wpcpm_ui_rig_now']).trim()) || 0; } catch { return 0; } // WP-CLI exits 1 when it is not stored
}

/** The copy's answer on a known page (its home page): the status, or 0 when it does not answer. */
export async function copyCode() {
  try { return (await fetch(BASE + '/', { redirect: 'manual', signal: AbortSignal.timeout(10000) })).status; } catch { return 0; }
}

/** The copy's own proxy service restarted (compose.yaml's "proxy"), nothing else. */
export function restartProxy() {
  execFileSync('docker', ['compose', '--progress', 'quiet', 'restart', 'proxy'], { cwd: DOCKER, stdio: 'ignore' });
}

/**
 * After a database restore (bin/restore restarts WordPress, which can come back at a new address on the inner
 * network, and the proxy keeps the old one and answers 502): wait until the copy answers 200 on its home page,
 * restarting the proxy on the third and fifteenth failed try; throw "refusing: ..." when it never does. `code`,
 * `restart` and `pause` are stand-ins in the suite.
 */
export async function waitForCopy({ code = copyCode, restart = restartProxy, tries = 30, pause = ms => new Promise(r => setTimeout(r, ms)), step = 2000, say = m => console.log(m) } = {}) {
  let last = 0;
  for (let i = 1; i <= tries; i++) {
    last = await code();
    if (last === 200) return i;
    if (i === 3 || i === 15) { say(`the copy answers ${last}: restarting its proxy`); restart(); }
    await pause(step);
  }
  throw new Error(`refusing: the copy still answers ${last} after its proxy was restarted`);
}
