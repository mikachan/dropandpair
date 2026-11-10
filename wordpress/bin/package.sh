#!/usr/bin/env bash
# Builds the deploy artefact: one zip for the plugin and one for the theme, each holding a
# drop-and-pair/ folder, ready for Plugins > Add New > Upload and Themes > Add New > Upload
# (or to unzip into wp-content/plugins and wp-content/themes over SFTP).
# Usage: wordpress/bin/package.sh [output dir, default wordpress/dist]
#
# Development files stay out: vendor/ (the plugin has its own autoloader), tests/, the
# Composer and PHPUnit files, and every dotfile. It then checks the result: none of those
# made it in, the files WordPress needs are there, and every PHP file lints.
set -euo pipefail

here="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$here/dist}"
mkdir -p "$out"
out="$(cd "$out" && pwd)"
stage="$(mktemp -d)"
trap 'rm -rf "$stage"' EXIT

excludes=(
	--exclude='.*'
	--exclude='/vendor/'
	--exclude='/tests/'
	--exclude='/composer.json'
	--exclude='/composer.lock'
	--exclude='/phpunit.xml.dist'
	--exclude='/dist/'
)

fail() { echo "package: $*" >&2; exit 1; }

build() { # kind (plugin|theme), source dir, files that must be there...
	local kind="$1" src="$2"; shift 2
	local dir="$stage/$kind/drop-and-pair"
	mkdir -p "$dir"
	rsync -a "${excludes[@]}" "$src/" "$dir/"

	local bad
	bad="$(cd "$dir" && find . -mindepth 1 \( -name '.*' -o -name vendor -o -name tests -o -name 'composer.*' -o -name 'phpunit.xml*' \) -print)"
	[ -z "$bad" ] || fail "$kind has development files: $bad"
	for need in "$@"; do
		[ -e "$dir/$need" ] || fail "$kind is missing $need"
	done
	while IFS= read -r -d '' php; do
		php -l "$php" >/dev/null || fail "$kind: $php doesn't lint"
	done < <(find "$dir" -name '*.php' -print0)

	rm -f "$out/drop-and-pair-$kind.zip"
	(cd "$stage/$kind" && zip -qr "$out/drop-and-pair-$kind.zip" drop-and-pair)
	echo "$out/drop-and-pair-$kind.zip ($(cd "$dir" && find . -type f | wc -l | tr -d ' ') files)"
}

build plugin "$here/plugins/drop-and-pair" drop-and-pair.php src/ContentTypes.php blocks editor
build theme "$here/themes/drop-and-pair" style.css theme.json functions.php templates parts assets
