#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

target="$TMP_DIR/ubuntu.sources"

cat >"$target" <<'EOF'
Types: deb
URIs: http://archive.ubuntu.com/ubuntu/
Suites: noble noble-updates noble-backports
Components: main restricted universe multiverse
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg

Types: deb
URIs: http://security.ubuntu.com/ubuntu/
Suites: noble-security
Components: main restricted universe multiverse
Signed-By: /usr/share/keyrings/ubuntu-archive-keyring.gpg
EOF

cd "$ROOT_DIR"

echo '[e2e] selfcheck: running recover:ubuntu against a temp ubuntu.sources fixture.'
E2E_UBUNTU_SOURCE_FILES="$target" \
E2E_UBUNTU_SOURCE_TARGET="$target" \
pnpm --dir e2e run recover:ubuntu

if grep -q 'http://archive.ubuntu.com/ubuntu/' "$target" || grep -q 'http://security.ubuntu.com/ubuntu/' "$target"; then
  echo '[e2e] selfcheck failed: fixture still contains http Ubuntu apt sources.' >&2
  exit 1
fi

if ! grep -q 'https://archive.ubuntu.com/ubuntu/' "$target" || ! grep -q 'https://security.ubuntu.com/ubuntu/' "$target"; then
  echo '[e2e] selfcheck failed: fixture was not rewritten to https Ubuntu apt sources.' >&2
  exit 1
fi

echo '[e2e] selfcheck passed: recover:ubuntu rewrote the temp apt source fixture to https and completed verification.'
