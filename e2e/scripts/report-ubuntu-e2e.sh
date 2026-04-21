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

print_section() {
  printf '\n## %s\n' "$1"
}

safe_run() {
  local description="$1"
  shift

  printf '$ %s\n' "$description"
  if "$@"; then
    return 0
  fi

  local exit_code=$?
  printf '[command exited with %s]\n' "$exit_code"
  return 0
}

load_node
cd "$ROOT_DIR"

echo '# Ubuntu E2E Report'
echo
echo "- Generated at: $(date -Iseconds)"
echo "- Host: $(hostname)"
echo "- Project root: $ROOT_DIR"

print_section 'Versions'
safe_run 'uname -a' uname -a
safe_run 'node --version' node --version
safe_run 'pnpm --version' pnpm --version

print_section 'Apt Sources'
safe_run "grep -R '^URIs:\\|^deb ' /etc/apt/sources.list /etc/apt/sources.list.d" \
  grep -R '^URIs:\|^deb ' /etc/apt/sources.list /etc/apt/sources.list.d

print_section 'Doctor'
safe_run 'pnpm --dir e2e run doctor' pnpm --dir e2e run doctor

print_section 'Chromium Libraries'
safe_run "bash -lc 'ldd ~/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome | grep \"not found\" || true'" \
  bash -lc 'ldd ~/.cache/ms-playwright/chromium-1217/chrome-linux64/chrome | grep "not found" || true'

print_section 'Stack Readiness'
safe_run 'pnpm --dir e2e run wait:stack' pnpm --dir e2e run wait:stack
