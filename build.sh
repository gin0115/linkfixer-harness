#!/usr/bin/env bash
# Builds the plugin zips the blueprints install:
#   zips/core.zip    the shared helper plugin, in the folder linkfixer-harness/
#   zips/<site>.zip  each site's own plugin from sites/<site>/plugin/, in the folder lfh-site-<site>/
set -euo pipefail

ROOT="$(cd "$(dirname "$0")" && pwd)"
STAGE="$(mktemp -d)"
trap 'rm -rf "$STAGE"' EXIT

mkdir -p "$ROOT/zips"

# build <source folder> <plugin folder name> <zip file name>
build() {
	rm -f "$ROOT/zips/$3"
	cp -r "$1" "$STAGE/$2"
	( cd "$STAGE" && zip -qr -X "$ROOT/zips/$3" "$2" )
	rm -rf "${STAGE:?}/$2"
	echo "built zips/$3"
}

build "$ROOT/core" linkfixer-harness core.zip

for site in "$ROOT"/sites/*/; do
	name="$(basename "$site")"
	build "${site}plugin" "lfh-site-$name" "$name.zip"
done
