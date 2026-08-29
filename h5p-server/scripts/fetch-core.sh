#!/usr/bin/env bash
#
# Fetches the H5P client-side core (player chrome) and editor chrome assets.
# These are the legacy jQuery-based JS/CSS bundles every H5P Node.js server
# needs to serve to the browser; there is no npm package for them, so every
# h5p-nodejs-library-based deployment obtains them the same way: cloning the
# official H5P Group repositories directly.
#
# Safe to re-run: skips a repo if it's already present.

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
H5P_DIR="$SCRIPT_DIR/../h5p"

CORE_REPO="https://github.com/h5p/h5p-php-library.git"
EDITOR_REPO="https://github.com/h5p/h5p-editor-php-library.git"

fetch() {
    local repo_url="$1"
    local target_dir="$2"

    if [ -d "$target_dir/.git" ]; then
        echo "Already present, skipping: $target_dir"
        return
    fi

    echo "Cloning $repo_url -> $target_dir"
    rm -rf "$target_dir"
    git clone --depth 1 "$repo_url" "$target_dir"
    rm -rf "$target_dir/.git"
}

fetch "$CORE_REPO" "$H5P_DIR/core"
fetch "$EDITOR_REPO" "$H5P_DIR/editor"

echo "H5P core and editor client assets are ready."
