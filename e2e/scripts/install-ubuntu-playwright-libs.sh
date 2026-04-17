#!/usr/bin/env bash

set -euo pipefail

packages=(
  libatk1.0-0t64
  libatk-bridge2.0-0t64
  libcups2t64
  libasound2t64
  libgbm1
  libcairo2
  libpango-1.0-0
  libxcomposite1
  libxdamage1
  libxfixes3
  libxrandr2
  libatspi2.0-0t64
)

printf '[e2e] installing Ubuntu Playwright shared libraries...\n'
sudo apt-get update
sudo apt-get install -y "${packages[@]}"
printf '[e2e] install complete. You can retry: pnpm --dir e2e run test:sso\n'
