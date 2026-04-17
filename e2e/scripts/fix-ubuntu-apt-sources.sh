#!/usr/bin/env bash

set -euo pipefail

target="${E2E_UBUNTU_SOURCE_TARGET:-/etc/apt/sources.list.d/ubuntu.sources}"
backup="${target}.bak"

run_maybe_sudo() {
  if [ -w "$target" ] || [ ! -e "$target" ]; then
    "$@"
    return
  fi

  sudo "$@"
}

if [ ! -f "$target" ]; then
  echo "[e2e] ${target} was not found."
  echo '[e2e] Update your Ubuntu apt sources manually so archive/security URIs use https.'
  exit 1
fi

echo "[e2e] backing up ${target} to ${backup}"
run_maybe_sudo cp "$target" "$backup"

echo "[e2e] switching Ubuntu archive/security URIs to https"
run_maybe_sudo sed -i \
  -e 's|http://archive.ubuntu.com/ubuntu/|https://archive.ubuntu.com/ubuntu/|g' \
  -e 's|http://security.ubuntu.com/ubuntu/|https://security.ubuntu.com/ubuntu/|g' \
  "$target"

echo '[e2e] current URIs:'
grep '^URIs:' "$target" || true

echo '[e2e] next steps:'
echo '  sudo apt-get update'
echo '  pnpm --dir e2e run doctor'
echo '  pnpm --dir e2e run install:ubuntu-libs'
