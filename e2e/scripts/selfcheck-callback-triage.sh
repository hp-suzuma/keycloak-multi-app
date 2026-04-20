#!/usr/bin/env bash

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP_DIR="$(mktemp -d)"
LOG_FILE="$TMP_DIR/callback-timeout.log"
REPORT_FILE="$TMP_DIR/callback-triage-summary.txt"
trap 'rm -rf "$TMP_DIR"' EXIT

cat > "$LOG_FILE" <<'EOF'
Error: SSO callback timeout while waiting for /users?service_scope_id=2&tenant_scope_id=3&keyword=alice&sort=-email.
Callback URL: https://ap.example.com/auth/callback?code=sample-code&state=sample-state
Callback trace: [{"stage":"callback:received","at":"2026-04-20T12:00:00.000Z"},{"stage":"callback:token-exchange:start","at":"2026-04-20T12:00:00.200Z"},{"stage":"callback:token-exchange:error","at":"2026-04-20T12:00:01.100Z"}]
EOF

node "$ROOT_DIR/e2e/scripts/print-callback-trace-summary.mjs" "$LOG_FILE" "$REPORT_FILE"

grep -F '[e2e] extracted Callback trace lines:' "$REPORT_FILE" >/dev/null
grep -F 'Callback trace: [{"stage":"callback:received"' "$REPORT_FILE" >/dev/null
grep -F '[e2e] Callback trace count: 3' "$REPORT_FILE" >/dev/null
grep -F '[e2e] Callback latest stage: callback:token-exchange:error' "$REPORT_FILE" >/dev/null
grep -F '[e2e] Callback latest at: 2026-04-20T12:00:01.100Z' "$REPORT_FILE" >/dev/null

echo '[e2e] callback triage selfcheck passed.'
