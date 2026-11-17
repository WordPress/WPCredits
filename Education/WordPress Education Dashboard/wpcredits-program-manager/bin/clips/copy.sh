#!/bin/bash
# Puts the Docker copy (http://localhost:8900, ~/Projects/wpcredits-docker) in the state clips are filmed in,
# and puts it back exactly. Nothing here touches any other site.
#
#   bin/clips/copy.sh prepare <plugin.zip>   snapshot, save the plugin folder, list the real names, install the
#                                            zip, scrub people, put the filming plugin in place, write the guide
#   bin/clips/copy.sh theme <folder>|own     on a prepared copy: the theme folder wpcredits-theme replaced by another
#                                            build of it (an unpacked `git archive`), the copy's own saved first,
#                                            once; `own` puts the copy's own back (restore does too). Run it before
#                                            any filming: clips-base and every shot's prepare then see that theme.
#   bin/clips/copy.sh restore                take all of that away again and check the copy
#
# The plugin folder on the copy holds a .worktrees folder (worktrees, and a symlink the container cannot follow,
# which makes WP-CLI's install fail): the zip goes in beside it, as bin/ui/copy.sh installs one, and .worktrees is
# never touched; restore compares its listing with the one taken before.
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; REPO="$(cd "$HERE/../.." && pwd)"
DOCKER="$HOME/Projects/wpcredits-docker"; WP="$DOCKER/bin/wp"
WORK="$REPO/.superpowers/guides/clips/work"; STAGE="$DOCKER/release/clips"
PLUGINS="$DOCKER/site/wp-content/plugins"; MU="$DOCKER/site/wp-content/mu-plugins"; THEMES="$DOCKER/site/wp-content/themes"
COPY="http://localhost:8900"
# A relative zip or theme path is resolved before the cd into the Docker folder.
if [ "${1:-}" = prepare ] && [ -n "${2:-}" ]; then set -- prepare "$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"; fi
if [ "${1:-}" = theme ] && [ -n "${2:-}" ] && [ "$2" != own ]; then set -- theme "$(cd "$2" && pwd)"; fi
# Every entry of the plugin folder's .worktrees, with its size, time, mode and link target.
worktrees_listing() { (cd "$PLUGINS/wpcredits-program-manager" && [ -e .worktrees ] && find .worktrees -print0 | sort -z | xargs -0 stat -f '%N %z %m %Lp %Y') || true; }
# The copy's answer on its home page; 000 when it does not answer at all.
copy_code() { curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$COPY/" || true; }
# A database restore restarts WordPress, which can come back at a new address on the inner network: the proxy keeps
# the old one and answers 502 until it is restarted too. Wait for 200, restarting the copy's own proxy if need be.
wait_for_copy() {
  local i code=000
  for i in $(seq 1 30); do
    code=$(copy_code)
    [ "$code" = 200 ] && return 0
    if [ "$i" = 3 ] || [ "$i" = 15 ]; then echo "the copy answers $code: restarting its proxy" >&2; (docker compose --progress quiet restart proxy >/dev/null 2>&1 || true); fi
    sleep 2
  done
  echo "stopped: the copy still answers $code" >&2; exit 1
}
cd "$DOCKER"
SITEURL=$("$WP" option get siteurl 2>/dev/null | tail -1)
[ "$SITEURL" = "$COPY" ] || { echo "refusing: the copy's siteurl is not $COPY" >&2; exit 1; }

case "${1:-}" in
prepare)
  ZIP="${2:?the plugin zip to film}"
  [ ! -e snapshots/before-clips.tgz ] || { echo "refusing: snapshots/before-clips.tgz exists (a filming is under way; restore first)" >&2; exit 1; }
  ENTRIES=$(unzip -Z1 "$ZIP")
  [ -n "$ENTRIES" ] && ! grep -v -q '^wpcredits-program-manager/' <<<"$ENTRIES" && ! grep -q '^wpcredits-program-manager/\.worktrees' <<<"$ENTRIES" || { echo "refusing: the zip holds more than the wpcredits-program-manager folder, or a .worktrees folder" >&2; exit 1; }
  mkdir -p "$WORK" "$STAGE"; chmod 700 "$WORK"
  "$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1 > "$WORK/version-before.txt"
  "$WP" theme list --name=wpcredits-theme --field=version 2>/dev/null | tail -1 > "$WORK/theme-version-before.txt"
  bin/snapshot before-clips
  tar -czf "$WORK/plugin-before.tgz" -C "$PLUGINS" --exclude 'wpcredits-program-manager/.worktrees' wpcredits-program-manager
  worktrees_listing > "$WORK/worktrees-before.txt"
  cp "$HERE/real-names.php" "$STAGE/real-names.php"
  "$WP" eval-file "$STAGE/real-names.php" 2>/dev/null | tail -1 > "$WORK/real-names.json"; chmod 600 "$WORK/real-names.json"
  echo "real names to refuse: $(python3 -c "import json,sys;print(len(json.load(open(sys.argv[1]))))" "$WORK/real-names.json")"
  cp "$ZIP" "$STAGE/plugin.zip"
  find "$PLUGINS/wpcredits-program-manager" -mindepth 1 -maxdepth 1 ! -name .worktrees -exec rm -rf {} +
  unzip -q -o "$STAGE/plugin.zip" -d "$PLUGINS"
  "$WP" plugin activate wpcredits-program-manager 2>&1 | tail -1
  echo "installed: $("$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1)"
  wait_for_copy
  bin/scrub-people --yes | tail -1
  bin/check-scrubbed | tail -1
  cp "$HERE/wpcpm-filming.php" "$MU/wpcpm-filming.php"
  cp "$REPO/docs/build/administrators.html" "$STAGE/administrators.html"
  GUIDE=$("$WP" post list --post_type=page --name=administrator-guide --field=ID 2>/dev/null | tail -1)
  "$WP" post update "$GUIDE" "$STAGE/administrators.html" 2>&1 | tail -1
  bin/snapshot clips-base   # every shot's prepare starts from this state (film.mjs restores it)
  wait_for_copy
  echo "ready: node bin/clips/film.mjs <slug>"
  ;;
theme)
  SRC="${2:?a theme folder (an unpacked git archive of wpcredits-theme), or own}"
  [ -e snapshots/before-clips.tgz ] && [ -f "$MU/wpcpm-filming.php" ] || { echo "refusing: the copy is not prepared (bin/clips/copy.sh prepare first)" >&2; exit 1; }
  [ -s "$WORK/theme-before.tgz" ] || tar -czf "$WORK/theme-before.tgz" -C "$THEMES" wpcredits-theme
  if [ "$SRC" = own ]; then
    rm -rf "$THEMES/wpcredits-theme" && tar -xzf "$WORK/theme-before.tgz" -C "$THEMES"
  else
    [ -f "$SRC/style.css" ] && grep -q '^Theme Name:' "$SRC/style.css" || { echo "refusing: $SRC holds no theme (no style.css with a Theme Name)" >&2; exit 1; }
    rm -rf "$THEMES/wpcredits-theme" && mkdir "$THEMES/wpcredits-theme" && cp -R "$SRC/." "$THEMES/wpcredits-theme/"
  fi
  echo "theme: $("$WP" theme list --name=wpcredits-theme --field=version 2>/dev/null | tail -1) ($([ "$SRC" = own ] && echo "the copy's own" || echo "$SRC"))"
  ;;
restore)
  [ -s "$WORK/plugin-before.tgz" ] && [ -e snapshots/before-clips.tgz ] || { echo "refusing: there is nothing to restore from (plugin-before.tgz or snapshots/before-clips.tgz missing); nothing was changed" >&2; exit 1; }
  rm -f "$MU/wpcpm-filming.php" "$DOCKER/site/wp-content/wpcpm-filming.log" "$DOCKER/site/wp-content/wpcpm-filming-links.log"
  rm -rf "$MU/wpcpm-proto.php" "$MU/wpcpm-proto"   # the UI audit's prototype round (ui-audit/proto/wpcpm-proto.php)
  rm -rf "$STAGE"
  bin/unscrub | tail -1
  if [ -s "$WORK/theme-before.tgz" ]; then rm -rf "$THEMES/wpcredits-theme" && tar -xzf "$WORK/theme-before.tgz" -C "$THEMES" && echo "theme: the copy's own put back"; fi
  # The plugin the snapshot was taken under goes back first, then the database (the copy's README: install that
  # line's plugin first, then restore). .worktrees stays as it is.
  find "$PLUGINS/wpcredits-program-manager" -mindepth 1 -maxdepth 1 ! -name .worktrees -exec rm -rf {} +
  tar -xzf "$WORK/plugin-before.tgz" -C "$PLUGINS" --exclude 'wpcredits-program-manager/.worktrees'
  bin/restore before-clips
  wait_for_copy
  echo "the copy answers 200"
  if [ -f "$WORK/worktrees-before.txt" ]; then
    if worktrees_listing | cmp -s - "$WORK/worktrees-before.txt"; then echo ".worktrees: as it was"; else echo ".worktrees: CHANGED" >&2; fi
  fi
  echo "version: $("$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1) (was $(cat "$WORK/version-before.txt" 2>/dev/null || echo unknown))"
  echo "theme: $("$WP" theme list --name=wpcredits-theme --field=version 2>/dev/null | tail -1) (was $(cat "$WORK/theme-version-before.txt" 2>/dev/null || echo unknown))"
  bin/check | tail -1
  rm -f snapshots/clips-ready.tgz snapshots/clips-base.tgz snapshots/before-clips.tgz snapshots/audit-ready.tgz
  rm -f "$WORK/real-names.json" "$WORK/theme-before.tgz" "$WORK/plugin-before.tgz" "$WORK/worktrees-before.txt" "$WORK/version-before.txt" "$WORK/theme-version-before.txt"
  ;;
*) echo "usage: bin/clips/copy.sh prepare <plugin.zip> | theme <folder>|own | restore" >&2; exit 2 ;;
esac
