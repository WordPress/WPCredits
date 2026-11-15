#!/bin/bash
# Puts the Docker copy (http://localhost:8900, ~/Projects/wpcredits-docker) in the state clips are filmed in,
# and puts it back exactly. Nothing here touches any other site.
#
#   bin/clips/copy.sh prepare <plugin.zip>   snapshot, save the plugin folder, list the real names, install the
#                                            zip, scrub people, put the filming plugin in place, write the guide
#   bin/clips/copy.sh restore                take all of that away again and check the copy
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; REPO="$(cd "$HERE/../.." && pwd)"
DOCKER="$HOME/Projects/wpcredits-docker"; WP="$DOCKER/bin/wp"
WORK="$REPO/.superpowers/guides/clips/work"; STAGE="$DOCKER/release/clips"
PLUGINS="$DOCKER/site/wp-content/plugins"; MU="$DOCKER/site/wp-content/mu-plugins"
# A relative zip path is resolved before the cd into the Docker folder.
if [ "${1:-}" = prepare ] && [ -n "${2:-}" ]; then set -- prepare "$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"; fi
cd "$DOCKER"
SITEURL=$("$WP" option get siteurl 2>/dev/null | tail -1)
[ "$SITEURL" = "http://localhost:8900" ] || { echo "refusing: the copy's siteurl is not http://localhost:8900" >&2; exit 1; }

case "${1:-}" in
prepare)
  ZIP="${2:?the plugin zip to film}"
  [ ! -e snapshots/before-clips.tgz ] || { echo "refusing: snapshots/before-clips.tgz exists (a filming is under way; restore first)" >&2; exit 1; }
  mkdir -p "$WORK" "$STAGE"; chmod 700 "$WORK"
  "$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1 > "$WORK/version-before.txt"
  bin/snapshot before-clips
  tar -czf "$WORK/plugin-before.tgz" -C "$PLUGINS" wpcredits-program-manager
  cp "$HERE/real-names.php" "$STAGE/real-names.php"
  "$WP" eval-file "$STAGE/real-names.php" 2>/dev/null | tail -1 > "$WORK/real-names.json"; chmod 600 "$WORK/real-names.json"
  echo "real names to refuse: $(python3 -c "import json,sys;print(len(json.load(open(sys.argv[1]))))" "$WORK/real-names.json")"
  cp "$ZIP" "$STAGE/plugin.zip"
  rm -rf "$PLUGINS/wpcredits-program-manager"   # the folder holds a symlink the container cannot follow; saved above
  "$WP" plugin install "$STAGE/plugin.zip" --force --activate 2>&1 | tail -1
  bin/scrub-people --yes | tail -1
  bin/check-scrubbed | tail -1
  cp "$HERE/wpcpm-filming.php" "$MU/wpcpm-filming.php"
  cp "$REPO/docs/build/administrators.html" "$STAGE/administrators.html"
  GUIDE=$("$WP" post list --post_type=page --name=administrator-guide --field=ID 2>/dev/null | tail -1)
  "$WP" post update "$GUIDE" "$STAGE/administrators.html" 2>&1 | tail -1
  bin/snapshot clips-base   # every shot's prepare starts from this state (film.mjs restores it)
  echo "ready: node bin/clips/film.mjs <slug>"
  ;;
restore)
  rm -f "$MU/wpcpm-filming.php" "$DOCKER/site/wp-content/wpcpm-filming.log" "$DOCKER/site/wp-content/wpcpm-filming-links.log"
  rm -rf "$MU/wpcpm-proto.php" "$MU/wpcpm-proto"   # the UI audit's prototype round (ui-audit/proto/wpcpm-proto.php)
  rm -rf "$STAGE"
  bin/unscrub | tail -1
  bin/restore before-clips
  rm -rf "$PLUGINS/wpcredits-program-manager"
  tar -xzf "$WORK/plugin-before.tgz" -C "$PLUGINS"
  echo "version: $("$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1) (was $(cat "$WORK/version-before.txt" 2>/dev/null || echo unknown))"
  bin/check | tail -1
  rm -f snapshots/clips-ready.tgz snapshots/clips-base.tgz snapshots/before-clips.tgz
  rm -f "$WORK/real-names.json"
  ;;
*) echo "usage: bin/clips/copy.sh prepare <plugin.zip> | restore" >&2; exit 2 ;;
esac
