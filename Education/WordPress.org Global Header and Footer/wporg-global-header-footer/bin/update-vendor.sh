#!/usr/bin/env bash
#
# Re-vendor the global header and footer from WordPress/wporg-mu-plugins.
#
# Worth running periodically. That repository is the wordpress.org network's own code and its
# README says changes there "must be tested on all sites" — it carries no compatibility promise to
# anybody outside the network, so a copy taken once will drift away from what wordpress.org itself
# is serving.
#
# The compiled CSS is not in the repository (it is built from `postcss/` by the repo's own npm
# tooling), so it is taken from the build wordpress.org publishes — the same artifact, from the
# same source, without needing a node toolchain here.
#
# Usage:  bin/update-vendor.sh
set -euo pipefail

root="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
tmp="$( mtemp=$(mktemp -d); echo "$mtemp" )"
trap 'rm -rf "$tmp"' EXIT

echo "Fetching WordPress/wporg-mu-plugins@trunk…"
gh api repos/WordPress/wporg-mu-plugins/tarball/trunk > "$tmp/src.tar.gz"
mkdir -p "$tmp/src"
tar xzf "$tmp/src.tar.gz" -C "$tmp/src" --strip-components=1

src="$tmp/src/mu-plugins/blocks/global-header-footer"
[ -d "$src" ] || { echo "The block is not where it used to be in the repository — check the layout before continuing." >&2; exit 1; }

rm -rf "$root/vendor/global-header-footer"
cp -R "$src" "$root/vendor/global-header-footer"

# The build holds more than the stylesheet: `register_block_types()` registers the blocks from
# `build/header/block.json` and `build/footer/block.json`, so without those the plugin loads and
# registers nothing at all — which is exactly how it fails, silently and with no error.
echo "Fetching the published build…"
pub="https://s.w.org/wp-content/mu-plugins/pub-sync/blocks/global-header-footer/build"
mkdir -p "$root/vendor/global-header-footer/build/header" "$root/vendor/global-header-footer/build/footer"
for f in style.css style-rtl.css header/block.json header/index.js footer/block.json footer/index.js; do
  curl -fsS -o "$root/vendor/global-header-footer/build/$f" "$pub/$f"
done

gh api repos/WordPress/wporg-mu-plugins/commits/trunk --jq '.sha' > "$root/vendor/UPSTREAM_SHA"

echo "Vendored at $(cat "$root/vendor/UPSTREAM_SHA")"
echo
echo "Now check the diff before committing — this is somebody else's code and it can change shape:"
echo "  git -C \"$root\" diff --stat vendor/"
