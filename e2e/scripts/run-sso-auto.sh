#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOCAL_LOG="$(mktemp)"
trap 'rm -f "$LOCAL_LOG"' EXIT

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

run_local() {
  load_node
  cd "$ROOT_DIR"
  pnpm --dir e2e run test:sso 2>&1 | tee "$LOCAL_LOG"
}

run_container() {
  printf '[e2e] falling back to Playwright container run.\n'
  bash "$ROOT_DIR/e2e/scripts/run-sso-container.sh"
}

if run_local; then
  exit 0
fi

if grep -Eq 'error while loading shared libraries|libatk-1\.0\.so\.0|browserType\.launch' "$LOCAL_LOG"; then
  run_container
  exit 0
fi

printf '[e2e] local run failed for a non-library reason; not auto-falling back.\n' >&2
exit 1
