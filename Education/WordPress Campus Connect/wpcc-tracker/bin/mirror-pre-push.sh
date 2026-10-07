#!/bin/sh
#
# Refuse to publish Campus Connect data that WordCamp Central does not.
#
# WordPress/WPCredits is public. The Campus Connect test fixtures are exports of
# the whole report, and most of the rows in them are applications that were
# declined, cancelled, put on hold or are still being vetted. Central does not
# list those, so publishing the fixtures would name the institution and city
# behind each one. data/seed.json is subject to the same rule and does ship.
#
# Mirroring this folder is a standing habit, so the exclusion cannot rest on
# somebody remembering it. This hook checks what is ACTUALLY being pushed, by
# extracting the pushed commit rather than trusting the working tree, which may
# differ from it.
#
# This is the master copy. The live hook is a copy of it at
# <mirror-clone>/.git/hooks/pre-push, because .git/hooks is not version
# controlled and a tracked hooks directory would impose this on everyone who
# clones a shared WordPress repository. Re-install after re-cloning the mirror:
#
#   install -m 755 bin/mirror-pre-push.sh \
#     ~/GitHub/Plugins/WPCredits-Tracker-mirror/.git/hooks/pre-push
#
# Verify it is live by committing a fixture into the mirror folder on a scratch
# branch and running `git push --dry-run`; it must refuse. Installed 2026-10-07.
#
# --no-verify skips it. If you are reaching for that, you are about to publish
# somebody's declined application.

set -u

FOLDER='Education/WordPress Campus Connect'
GATE="$HOME/GitHub/Plugins/wpcc-tracker/bin/check-mirror-safe.php"

fail() {
	printf '\n  PUSH REFUSED\n\n%s\n\n' "$1" >&2
	printf '  Nothing was pushed. Fix it, or read bin/check-mirror-safe.php to\n' >&2
	printf '  see what it objected to.\n\n' >&2
	exit 1
}

# The refs being pushed arrive on stdin as: <local ref> <local sha> <remote ref> <remote sha>
while read -r _local_ref local_sha _remote_ref _remote_sha; do
	# A branch being deleted publishes nothing.
	case "$local_sha" in
		*[!0]*) ;;
		*) continue ;;
	esac

	# Does the commit being pushed even contain the folder?
	if ! git cat-file -e "$local_sha:$FOLDER" 2>/dev/null; then
		continue
	fi

	if [ ! -r "$GATE" ]; then
		fail "  The safety check is missing:
    $GATE

  It lives in the wpcc-tracker source repository, because it needs the
  fixtures to know which names belong to non-public applications. Without
  it this push cannot be shown to be safe, so it is refused rather than
  waved through."
	fi

	TMP=$(mktemp -d) || fail '  Could not create a temporary directory.'
	trap 'rm -rf "$TMP"' EXIT INT TERM

	if ! git archive "$local_sha" "$FOLDER" | tar -x -C "$TMP" 2>/dev/null; then
		fail "  Could not extract '$FOLDER' from the commit being pushed."
	fi

	printf '\n  Checking what this push would publish...\n\n'

	if ! php "$GATE" "$TMP/$FOLDER"; then
		fail "  The commit being pushed contains Campus Connect data that
  WordCamp Central does not publish. See the failures above."
	fi

	rm -rf "$TMP"
	trap - EXIT INT TERM
done

exit 0
