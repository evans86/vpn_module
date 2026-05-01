#!/bin/bash
# Лёгкая самодиагностика с VPS (порт 80/443 через fcgiwrap, как /test-speed).
# Даёт задержки исходящих HTTPS и короткий download-speed до публичных точек (РФ-ориентир, Cloudflare).
#
# Nginx (пример): location = /server-probe-light { include snippets/panel-stub-server-probe-light.inc; }
#
# Безопасность: если существует /var/www/panel-stub/.test-speed-token — нужен ?token=... как у /test-speed

set +u
export LC_ALL=C.UTF-8
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
: "${QUERY_STRING:=}"

TOKEN_FILE="/var/www/panel-stub/.test-speed-token"

cgi_reply_headers() {
  echo "Content-Type: text/plain; charset=utf-8"
  echo ""
}

extract_query_token() {
  local q="$QUERY_STRING"
  local t
  [[ -z "$q" ]] && { echo ""; return 0; }
  t="${q#*token=}"
  t="${t%%&*}"
  t="${t%%#*}"
  echo "$t"
}

EXPECTED=""
if [[ -f "$TOKEN_FILE" ]]; then
  EXPECTED="$(tr -d '\r\n\t ' <"$TOKEN_FILE" 2>/dev/null)"
fi
GOT="$(extract_query_token | tr -d '\r\n\t ')"

if [[ -n "$EXPECTED" && "$GOT" != "$EXPECTED" ]]; then
  cgi_reply_headers
  echo "Нужен корректный ?token= (как для /test-speed)."
  exit 0
fi

probe_line() {
  local label="$1"
  local url="$2"
  if ! command -v curl >/dev/null 2>&1; then
    printf '%-28s %s\n' "$label" "нет curl"
    return 0
  fi
  local t
  t=$(curl -gksS -o /dev/null -w '%{time_connect} %{time_appconnect} %{time_total}' \
    --connect-timeout 4 -m 18 "$url" 2>/dev/null) || t="- - fail"
  printf '%-28s %s\n' "$label" "$t"
}

dl_mbps() {
  local label="$1"
  local url="$2"
  local secs="${3:-12}"
  if ! command -v curl >/dev/null 2>&1; then
    printf '%-28s %s\n' "$label" "нет curl"
    return 0
  fi
  local spd
  spd=$(curl -gLsS --connect-timeout 5 --max-time "$secs" -o /dev/null -w "%{speed_download}" "$url" 2>/dev/null) || spd=0
  awk -v lab="$label" -v spd="$spd" 'BEGIN{
    if (spd+0 <= 0) { printf "%-28s нет данных\n", lab; exit }
    m = spd * 8 / 1024 / 1024
    printf "%-28s ~ %.2f Мбит/с\n", lab, m
  }'
}

cgi_reply_headers
echo "=== server-probe-light $(date -Iseconds 2>/dev/null || date) host:${HOSTNAME:-?} ==="
echo ""
echo "--- HTTPS latency (connect TLS total sec) ---"
probe_line 'Yandex robots' 'https://yandex.ru/robots.txt'
probe_line 'Google 204' 'https://www.google.com/generate_204'
probe_line 'Cloudflare' 'https://cloudflare-dns.com/'
probe_line 'Telegram' 'https://telegram.org/'
echo ""
echo "--- Короткая загрузка (оценка Мбит/с) ---"
dl_mbps 'Cloudflare 1 MiB' 'https://speed.cloudflare.com/__down?bytes=1048576' 14
dl_mbps 'Tele2 1 MiB' 'http://speedtest.tele2.net/1MB.zip' 14
echo ""
echo "done"
