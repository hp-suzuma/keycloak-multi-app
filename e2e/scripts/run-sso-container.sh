#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
PLAYWRIGHT_IMAGE="${PLAYWRIGHT_IMAGE:-mcr.microsoft.com/playwright:v1.59.1-noble}"
PLAYWRIGHT_CONTAINER_USER="${PLAYWRIGHT_CONTAINER_USER:-1000:1000}"

printf '[e2e] running SSO test in Playwright container: %s\n' "$PLAYWRIGHT_IMAGE"

exec docker run --rm \
  --network host \
  --user "$PLAYWRIGHT_CONTAINER_USER" \
  -e CI=true \
  -e PLAYWRIGHT_BASE_URL="${PLAYWRIGHT_BASE_URL:-https://ap.example.com}" \
  -e KEYCLOAK_USERNAME="${KEYCLOAK_USERNAME:-alice}" \
  -e KEYCLOAK_PASSWORD="${KEYCLOAK_PASSWORD:-password}" \
  -e PLAYWRIGHT_WAIT_TIMEOUT_MS="${PLAYWRIGHT_WAIT_TIMEOUT_MS:-120000}" \
  -e PLAYWRIGHT_WAIT_INTERVAL_MS="${PLAYWRIGHT_WAIT_INTERVAL_MS:-3000}" \
  -e PLAYWRIGHT_HOST_MAP="${PLAYWRIGHT_HOST_MAP:-ap.example.com=127.0.0.1,global.example.com=127.0.0.1,keycloak.example.com=127.0.0.1,ap-backend-fpm.example.com=127.0.0.1}" \
  -v "$ROOT_DIR:/work" \
  -w /work/e2e \
  "$PLAYWRIGHT_IMAGE" \
  bash -lc 'npm exec --yes pnpm@10.33.0 -- install --frozen-lockfile && npm exec --yes pnpm@10.33.0 -- run test:sso'
