#!/bin/sh
set -eu

cd /app

if [ ! -d node_modules ] || [ -z "$(ls -A node_modules 2>/dev/null)" ]; then
  npm install
fi

exec npm run dev -- --host 0.0.0.0 --port 3000
