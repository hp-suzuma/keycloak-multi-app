#!/usr/bin/env bash

set -euo pipefail

target="/etc/apt/sources.list.d/ubuntu.sources"
backup="${target}.bak"

if [ ! -f "$target" ]; then
  echo "[e2e] ${target} was not found."
  echo '[e2e] Update your Ubuntu apt sources manually so archive/security URIs use https.'
  exit 1
fi

echo "[e2e] backing up ${target} to ${backup}"
sudo cp "$target" "$backup"

echo "[e2e] switching Ubuntu archive/security URIs to https"
sudo sed -i \
  -e 's|http://archive.ubuntu.com/ubuntu/|https://archive.ubuntu.com/ubuntu/|g' \
  -e 's|http://security.ubuntu.com/ubuntu/|https://security.ubuntu.com/ubuntu/|g' \
  "$target"

echo '[e2e] current URIs:'
grep '^URIs:' "$target" || true

echo '[e2e] next steps:'
echo '  sudo apt-get update'
echo '  pnpm --dir e2e run doctor'
echo '  pnpm --dir e2e run install:ubuntu-libs'
