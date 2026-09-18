#!/usr/bin/env bash
#
# Build the distributable plugin zip.
#
# Ships only what a site needs to run: the generators and tests in .dev/, the
# git history and the hidden files stay behind. Plugin Check must be run
# against THIS output rather than the working tree, or it reports on the
# tooling too -- .dev/export-starter.php alone accounts for a dozen findings
# about globals and mkdir() that no user will ever load.
#
# Usage:  bash .dev/build-zip.sh [outdir]
set -euo pipefail

here="$( cd "$( dirname "$0" )/.." && pwd )"
out="${1:-/tmp/tyche-companion-build}"
name="tyche-companion"

rm -rf "$out"
mkdir -p "$out/$name"

cd "$here"
rsync -a \
	--exclude '.git' \
	--exclude '.github' \
	--exclude '.dev' \
	--exclude 'CLAUDE.md' \
	--exclude '.gitignore' \
	--exclude '.DS_Store' \
	--exclude 'node_modules' \
	./ "$out/$name/"

cd "$out"
zip -rq "$name.zip" "$name"

echo "$out/$name.zip"
du -sh "$out/$name" "$out/$name.zip" | sed 's/^/  /'
