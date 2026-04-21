#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"

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

echo '[e2e] step 1/3: doctor'
pnpm --dir e2e run doctor

echo '[e2e] step 2/3: wait:stack'
pnpm --dir e2e run wait:stack

echo '[e2e] step 3/3: test:sso:auto'
pnpm --dir e2e run test:sso:auto
