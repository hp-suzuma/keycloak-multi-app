#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
STANDARD_LOG="$(mktemp)"
DEBUG_LOG="$(mktemp)"
SUMMARY_REPORT="$ROOT_DIR/e2e/test-results/callback-triage-summary.txt"
trap 'rm -f "$STANDARD_LOG" "$DEBUG_LOG"' EXIT

run_and_capture() {
  local log_file="$1"
  shift

  set +e
  "$@" 2>&1 | tee "$log_file"
  local status=${PIPESTATUS[0]}
  set -e

  return "$status"
}

print_callback_trace_summary() {
  local log_file="$1"
  node "$ROOT_DIR/e2e/scripts/print-callback-trace-summary.mjs" "$log_file" "$SUMMARY_REPORT"
  echo "[e2e] callback triage summary saved to $SUMMARY_REPORT"
}

cd "$ROOT_DIR"

echo '[e2e] step 1/2: run standard Playwright container regression'
if run_and_capture "$STANDARD_LOG" pnpm --dir e2e run test:sso:container; then
  echo '[e2e] standard run passed. No debug rerun needed.'
  exit 0
fi

echo '[e2e] standard run failed. step 2/2: rerun with PLAYWRIGHT_SSO_DEBUG=1'
if run_and_capture "$DEBUG_LOG" pnpm --dir e2e run test:sso:debug; then
  echo '[e2e] debug rerun passed. Treating the original failure as unresolved flake.'
else
  echo '[e2e] debug rerun also failed. Inspect Callback trace below.'
fi

print_callback_trace_summary "$DEBUG_LOG"

echo '[e2e] triage captured the follow-up run, but the original standard failure remains unresolved.'
exit 1
