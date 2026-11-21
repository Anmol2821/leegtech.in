#!/usr/bin/env bash
# Build the site and push dist/ to the "live" branch, which Hostinger deploys automatically.
# Usage: bash scripts/publish-live.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
node build.mjs
node scripts/qa/check-site.mjs | tail -1 | grep -q "NO PROBLEMS" || { echo "check-site failed; not publishing"; exit 1; }
REMOTE="$(git remote get-url origin)"
TMP="$(mktemp -d)"
cp -a dist/. "$TMP/"
cd "$TMP"
git init -q -b live
git add -A
git -c user.name="Leegtech Publisher" -c user.email="noreply@leegtech.in" commit -q -m "Publish site from main@$(git -C "$ROOT" rev-parse --short HEAD)"
git push -q --force "$REMOTE" live
echo "Published to live branch"
rm -rf "$TMP"
