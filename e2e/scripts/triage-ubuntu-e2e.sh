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

echo '[e2e] step 1/2: report:ubuntu'
pnpm --dir e2e run report:ubuntu

echo '[e2e] step 2/2: decide next action from doctor'
if pnpm --dir e2e run doctor 2>&1 | tee "$DOCTOR_LOG"; then
  echo '[e2e] doctor passed. Proceeding to verify:ubuntu.'
  pnpm --dir e2e run verify:ubuntu
  exit 0
fi

if grep -q 'apt:ubuntu-sources' "$DOCTOR_LOG"; then
  echo '[e2e] doctor found apt source http configuration. Proceeding to recover:ubuntu.'
  pnpm --dir e2e run recover:ubuntu
  exit 0
fi

echo '[e2e] doctor failed for a reason that needs manual follow-up. Share the report above and continue from that output.'
exit 1
