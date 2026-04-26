#!/usr/bin/env bash
# На сервере: выдать файл ~15 МиБ в каталог заглушки (decoy /123.rar).
set -euo pipefail
DIR="${1:-/var/www/panel-stub}"
OUT="${DIR}/123.rar"
mkdir -p "$DIR"
# 15 × 1 МиБ
if dd if=/dev/zero of="$OUT" bs=1M count=15 status=none 2>/dev/null; then
  :
elif command -v truncate >/dev/null 2>&1; then
  truncate -s 15M "$OUT"
else
  head -c $((15 * 1024 * 1024)) /dev/zero >"$OUT"
fi
chmod 644 "$OUT"
ls -la "$OUT"
echo "OK: $OUT"
