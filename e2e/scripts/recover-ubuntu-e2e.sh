#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
DOCTOR_LOG="$(mktemp)"
trap 'rm -f "$DOCTOR_LOG"' EXIT

load_node() {
  if command -v node >/dev/null 2>&1; then
    return
  fi

  if [ -s "$HOME/.nvm/nvm.sh" ]; then
    # shellcheck disable=SC1090
    . "$HOME/.nvm/nvm.sh" --no-use
    nvm use 22 >/dev/null
  fi
}

load_node
cd "$ROOT_DIR"

if pnpm --dir e2e run doctor 2>&1 | tee "$DOCTOR_LOG"; then
  echo '[e2e] doctor already passed. Proceeding to verify:ubuntu.'
  pnpm --dir e2e run verify:ubuntu
  exit 0
fi

if grep -q 'apt:ubuntu-sources' "$DOCTOR_LOG"; then
  echo '[e2e] doctor detected Ubuntu apt sources still using http. Running fixer.'
  pnpm --dir e2e run fix:ubuntu-apt-sources
  echo '[e2e] rerunning doctor after apt source fix.'
  pnpm --dir e2e run doctor
  echo '[e2e] continuing to verify:ubuntu.'
  pnpm --dir e2e run verify:ubuntu
  exit 0
fi

echo '[e2e] doctor failed for a reason other than Ubuntu apt sources. Stopping here.'
exit 1
