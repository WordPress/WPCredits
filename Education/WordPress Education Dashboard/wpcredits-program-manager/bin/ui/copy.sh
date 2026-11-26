#!/bin/bash
# Puts the local Docker copy (http://localhost:8900) in the state the UI rig photographs and measures, and
# puts it back exactly. Nothing here touches any other site.
#
#   bin/ui/copy.sh prepare <plugin.zip>   snapshot the copy, save the plugin folder, collect the real names to
#                                         refuse (into the work folder, never into a committed file), install
#                                         the zip, scrub people, put the rig's must-use plugin in place, write
#                                         the Administrator guide, snapshot ui-base
#   bin/ui/copy.sh swap <plugin.zip>      on a prepared copy: the database back to ui-ready, then another build in
#   bin/ui/copy.sh theme <folder>|own     on a prepared copy: the theme folder wpcredits-theme replaced by another
#                                         build of it (an unpacked `git archive`), the copy's own saved first, once;
#                                         `own` puts the copy's own back (restore does too)
#   bin/ui/copy.sh restore                take all of that away again and check the copy (after any swaps too)
#
# The Docker folder is $WPCPM_DOCKER (default ~/Projects/wpcredits-docker); the work folder, which holds the
# names to refuse and the invented agreements' PDFs, is $WPCPM_UI_WORK (default .superpowers/ui-rig/work in
# this repository, which git ignores).
set -euo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; REPO="$(cd "$HERE/../.." && pwd)"
DOCKER="${WPCPM_DOCKER:-$HOME/Projects/wpcredits-docker}"; WP="$DOCKER/bin/wp"
WORK="${WPCPM_UI_WORK:-$REPO/.superpowers/ui-rig/work}"; STAGE="$DOCKER/release/ui-rig"
PLUGINS="$DOCKER/site/wp-content/plugins"; MU="$DOCKER/site/wp-content/mu-plugins"; THEMES="$DOCKER/site/wp-content/themes"
COPY="http://localhost:8900"
# A relative zip path is resolved before the cd into the Docker folder.
if { [ "${1:-}" = prepare ] || [ "${1:-}" = swap ]; } && [ -n "${2:-}" ]; then set -- "$1" "$(cd "$(dirname "$2")" && pwd)/$(basename "$2")"; fi
if [ "${1:-}" = theme ] && [ -n "${2:-}" ] && [ "$2" != own ]; then set -- "$1" "$(cd "$2" && pwd)"; fi
# Every entry of the plugin folder's .worktrees, with its size, time, mode and link target.
worktrees_listing() { (cd "$PLUGINS/wpcredits-program-manager" && [ -e .worktrees ] && find .worktrees -print0 | sort -z | xargs -0 sh -c 'if stat -c %n / >/dev/null 2>&1; then stat -c "%n %s %Y %a %N" "$@"; else stat -f "%N %z %m %Lp %Y" "$@"; fi' _) || true; }
# Refuse a zip that holds anything but the wpcredits-program-manager folder, or a .worktrees folder of its own.
check_zip() {
  local entries
  entries=$(unzip -Z1 "$1")
  [ -n "$entries" ] && ! grep -v -q '^wpcredits-program-manager/' <<<"$entries" || { echo "refusing: the zip is empty or holds more than the wpcredits-program-manager folder" >&2; exit 1; }
  ! grep -q '^wpcredits-program-manager/\.worktrees' <<<"$entries" || { echo "refusing: the zip holds a .worktrees folder" >&2; exit 1; }
}
# The zip goes in beside the plugin folder's old .worktrees (worktrees and a symlink the container cannot
# follow, which make WP-CLI's install fail): every other entry is taken away, the zip unpacked over the
# folder, and .worktrees is never touched.
install_zip() {
  check_zip "$1"
  cp "$1" "$STAGE/plugin.zip"
  find "$PLUGINS/wpcredits-program-manager" -mindepth 1 -maxdepth 1 ! -name .worktrees -exec rm -rf {} +
  unzip -q -o "$STAGE/plugin.zip" -d "$PLUGINS"
  "$WP" plugin activate wpcredits-program-manager 2>&1 | tail -1
  echo "installed: $("$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1)"
}
# The guide's pictures, as the built guide names them (images/<file>, beside the repository's docs), pointed at the
# copy's own uploads where the published guide's attachments live (docs/README.md: the base for the pages), so the
# guide's page draws them instead of nine broken pictures (which snap.mjs refuses to save). Reads stdin.
guide_images() { sed 's#src="images/#src="http://localhost:8900/wp-content/uploads/2026/08/#g'; }
# The copy's answer on a known page (its home page), 000 when it does not answer at all.
copy_code() { curl -s -o /dev/null -w '%{http_code}' --max-time 10 "$COPY/" || true; }
# The copy's own proxy service restarted (compose.yaml's "proxy"), nothing else.
restart_proxy() { (cd "$DOCKER" && docker compose --progress quiet restart proxy) >/dev/null 2>&1 || true; }
# A database restore restarts WordPress, which can come back at a new address on the inner network: the proxy
# keeps the old one and answers 502 until it is restarted too. Wait until the copy answers 200 on its home page,
# restarting the proxy when it does not; stop with exit 1 when it never does.
wait_for_copy() {
  local i code=000
  for i in $(seq 1 "${WAIT_TRIES:-30}"); do
    code=$(copy_code)
    [ "$code" = 200 ] && return 0
    if [ "$i" = 3 ] || [ "$i" = 15 ]; then echo "the copy answers $code: restarting its proxy" >&2; restart_proxy; fi
    sleep "${WAIT_STEP:-2}"
  done
  echo "refusing to go on: the copy still answers $code after its proxy was restarted" >&2
  exit 1
}
cd "$DOCKER"
SITEURL=$("$WP" option get siteurl 2>/dev/null | tail -1)
HOMEURL=$("$WP" option get home 2>/dev/null | tail -1)
[ "$SITEURL" = "$COPY" ] && [ "$HOMEURL" = "$COPY" ] || { echo "refusing: the copy's siteurl and home are not both $COPY" >&2; exit 1; }

case "${1:-}" in
prepare)
  ZIP="${2:?the plugin zip to photograph}"
  [ ! -e snapshots/before-ui-rig.tgz ] || { echo "refusing: snapshots/before-ui-rig.tgz exists (a round is under way; restore first)" >&2; exit 1; }
  mkdir -p "$WORK" "$STAGE"; chmod 700 "$WORK"
  : > "$WORK/created-files.txt"   # seed.mjs adds every file it makes on the copy; restore removes exactly those
  "$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1 > "$WORK/version-before.txt"
  bin/snapshot before-ui-rig
  tar -czf "$WORK/plugin-before.tgz" -C "$PLUGINS" wpcredits-program-manager
  # The names to refuse are read from the copy itself, before the scrub, and kept in the work folder only.
  cp "$HERE/real-names.php" "$STAGE/real-names.php"
  "$WP" eval-file "$STAGE/real-names.php" 2>/dev/null | tail -1 > "$WORK/real-names.json"; chmod 600 "$WORK/real-names.json"
  echo "real names to refuse: $(python3 -c "import json,sys;print(len(json.load(open(sys.argv[1]))))" "$WORK/real-names.json")"
  check_zip "$ZIP"
  worktrees_listing > "$WORK/worktrees-before.txt"
  install_zip "$ZIP"
  wait_for_copy
  bin/scrub-people --yes | tail -1
  bin/check-scrubbed | tail -1
  cp "$HERE/wpcpm-ui-rig.php" "$MU/wpcpm-ui-rig.php"
  if [ -f "$REPO/docs/build/administrators.html" ]; then
    guide_images < "$REPO/docs/build/administrators.html" > "$STAGE/administrators.html"
    GUIDE=$("$WP" post list --post_type=page --name=administrator-guide --field=ID 2>/dev/null | tail -1)
    [ -n "$GUIDE" ] && "$WP" post update "$GUIDE" "$STAGE/administrators.html" 2>&1 | tail -1
  fi
  bin/snapshot ui-base   # every snap.mjs round that seeds starts from this state
  echo "ready: node bin/ui/snap.mjs --out <folder>"
  ;;
swap)
  # Another build on the prepared copy: the database back to ui-ready (the same invented items and frozen
  # moment the first build was photographed with), then the other zip in, as prepare installs one. The pseudonyms,
  # the rig's must-use plugin, the work folder and the snapshots stay as prepare left them, so restore puts
  # the copy back after any number of swaps.
  ZIP="${2:?the plugin zip to swap in}"
  [ -e snapshots/before-ui-rig.tgz ] && [ -s "$WORK/plugin-before.tgz" ] && [ -f "$MU/wpcpm-ui-rig.php" ] || { echo "refusing: the copy is not prepared (bin/ui/copy.sh prepare first)" >&2; exit 1; }
  [ -e snapshots/ui-ready.tgz ] || { echo "refusing: there is no snapshot ui-ready (run snap.mjs once on the first build)" >&2; exit 1; }
  check_zip "$ZIP"
  bin/restore ui-ready
  wait_for_copy
  install_zip "$ZIP"
  wait_for_copy
  echo "ready: node bin/ui/snap.mjs --skip-seed --out <folder>"
  ;;
theme)
  # Another build of the theme beside the plugin's (a frame the theme must name): the copy's own folder is saved once,
  # in the work folder, and put back by `theme own` or by restore. Only the wpcredits-theme folder is touched.
  SRC="${2:?a theme folder (an unpacked git archive of wpcredits-theme), or own}"
  [ -e snapshots/before-ui-rig.tgz ] && [ -f "$MU/wpcpm-ui-rig.php" ] || { echo "refusing: the copy is not prepared (bin/ui/copy.sh prepare first)" >&2; exit 1; }
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
  # Nothing is taken away unless everything needed to put the copy back is there.
  [ -s "$WORK/plugin-before.tgz" ] && [ -e snapshots/before-ui-rig.tgz ] || { echo "refusing: there is nothing to restore from (the saved plugin folder $WORK/plugin-before.tgz or the snapshot before-ui-rig is missing); nothing was changed" >&2; exit 1; }
  rm -f "$MU/wpcpm-ui-rig.php" "$DOCKER/site/wp-content/wpcpm-ui-rig.log" "$DOCKER/site/wp-content/wpcpm-ui-rig-links.log"
  rm -rf "$STAGE"
  # The files the seed made on the copy (the uploaded agreements), each by the name seed.mjs recorded, and
  # nothing else: a line that is not a plain path under uploads/ is passed over.
  n=0
  if [ -f "$WORK/created-files.txt" ]; then
    while IFS= read -r rel || [ -n "$rel" ]; do
      case "$rel" in uploads/*) ;; *) continue ;; esac
      case "$rel" in *..*|*'*'*|*'?'*|*'['*) continue ;; esac
      if [ -f "$DOCKER/site/wp-content/$rel" ]; then
        rm -f -- "$DOCKER/site/wp-content/$rel"; n=$((n+1))
      fi
    done < "$WORK/created-files.txt"
  fi
  echo "files the seed made, removed: $n"
  bin/unscrub | tail -1
  # The copy's own theme, when `theme` put another build in its place.
  if [ -s "$WORK/theme-before.tgz" ]; then rm -rf "$THEMES/wpcredits-theme" && tar -xzf "$WORK/theme-before.tgz" -C "$THEMES" && echo "theme: the copy's own put back"; fi
  # The plugin the snapshot was taken under goes back first, then the database (the copy's README: install
  # that line's plugin first, then restore). .worktrees stays as it is: the tarball's copy of it is not unpacked.
  find "$PLUGINS/wpcredits-program-manager" -mindepth 1 -maxdepth 1 ! -name .worktrees -exec rm -rf {} +
  tar -xzf "$WORK/plugin-before.tgz" -C "$PLUGINS" --exclude 'wpcredits-program-manager/.worktrees'
  bin/restore before-ui-rig
  wait_for_copy
  echo "the copy answers 200"
  if [ -f "$WORK/worktrees-before.txt" ]; then
    if worktrees_listing | cmp -s - "$WORK/worktrees-before.txt"; then echo ".worktrees: as it was"; else echo ".worktrees: CHANGED" >&2; fi
  fi
  echo "version: $("$WP" plugin list --name=wpcredits-program-manager --field=version 2>/dev/null | tail -1) (was $(cat "$WORK/version-before.txt" 2>/dev/null || echo unknown))"
  bin/check | tail -1
  rm -f snapshots/ui-ready.tgz snapshots/ui-base.tgz snapshots/before-ui-rig.tgz
  # The copy is back and checked: the rig's work files go too, so a later round cannot restore a stale tarball.
  rm -f "$WORK/theme-before.tgz"
  rm -f "$WORK/plugin-before.tgz" "$WORK/version-before.txt" "$WORK/real-names.json" "$WORK/created-files.txt" "$WORK/worktrees-before.txt"
  ;;
*) echo "usage: bin/ui/copy.sh prepare <plugin.zip> | swap <plugin.zip> | theme <folder>|own | restore" >&2; exit 2 ;;
esac
