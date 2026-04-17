#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
E2E_DIR="$ROOT_DIR/e2e"
NVM_VERSION="${NVM_VERSION:-v0.40.3}"
NODE_MAJOR="${NODE_MAJOR:-22}"
NODE_VERSION="${NODE_VERSION:-22}"
PROFILE_FILE="${PROFILE_FILE:-$HOME/.bashrc}"

log() {
  printf '[bootstrap] %s\n' "$1"
}

warn() {
  printf '[warn] %s\n' "$1"
}

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    printf '[error] required command not found: %s\n' "$1" >&2
    exit 1
  fi
}

require_command curl
require_command bash

install_nvm_if_needed() {
  if [ -s "$HOME/.nvm/nvm.sh" ]; then
    log "nvm is already installed."
    return
  fi

  log "installing nvm ${NVM_VERSION}..."
  curl -fsSL "https://raw.githubusercontent.com/nvm-sh/nvm/${NVM_VERSION}/install.sh" | bash
}

load_nvm() {
  # shellcheck disable=SC1090
  . "$HOME/.nvm/nvm.sh"
}

ensure_node() {
  if command -v node >/dev/null 2>&1; then
    local current_major
    current_major="$(node -p "process.versions.node.split('.')[0]")"
    if [ "$current_major" = "$NODE_MAJOR" ]; then
      log "Node $(node --version) is already available."
      return
    fi

    warn "existing Node $(node --version) found; switching to ${NODE_VERSION} via nvm."
  fi

  log "installing Node ${NODE_VERSION} via nvm..."
  nvm install "${NODE_VERSION}"
  nvm alias default "${NODE_VERSION}"
  nvm use "${NODE_VERSION}"
}

ensure_profile_snippet() {
  if grep -Fq 'NVM_DIR="$HOME/.nvm"' "$PROFILE_FILE" 2>/dev/null; then
    log "nvm profile snippet already exists in ${PROFILE_FILE}."
    return
  fi

  log "adding nvm profile snippet to ${PROFILE_FILE}."
  cat >>"$PROFILE_FILE" <<'EOF'

export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"
EOF
}

prepare_pnpm() {
  log "enabling corepack and activating pnpm..."
  corepack enable
  corepack prepare pnpm@latest --activate
}

install_e2e_deps() {
  log "installing e2e workspace dependencies..."
  pnpm --dir "$E2E_DIR" install
}

install_browsers() {
  log "installing Playwright Chromium and Ubuntu dependencies..."
  pnpm --dir "$E2E_DIR" run install:browsers
}

seed_env() {
  if [ -f "$E2E_DIR/.env" ]; then
    log "e2e/.env already exists."
    return
  fi

  if [ -f "$E2E_DIR/.env.example" ]; then
    log "creating e2e/.env from .env.example."
    cp "$E2E_DIR/.env.example" "$E2E_DIR/.env"
  fi
}

main() {
  cd "$ROOT_DIR"
  install_nvm_if_needed
  load_nvm
  ensure_node
  ensure_profile_snippet
  prepare_pnpm
  install_e2e_deps
  install_browsers
  seed_env

  log "bootstrap completed."
  log "next steps:"
  log "  pnpm --dir e2e run doctor"
  log "  pnpm --dir e2e run wait:stack"
  log "  pnpm --dir e2e run test:sso"
}

main "$@"
