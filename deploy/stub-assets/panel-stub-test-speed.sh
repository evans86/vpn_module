#!/bin/bash
# Исходящие тесты и доступность зон через HTTPS (cgi/fcgiwrap, выполняется от www-data)
# QUERY_STRING может содержать token=...

set +u
# stderr не смешиваем с stdout: иначе fcgiwrap может отдавать upstream в виде некорректного ответа (nginx 502)
export LC_ALL=C.UTF-8
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
: "${QUERY_STRING:=}"
: "${REQUEST_METHOD:=GET}"

TOKEN_FILE="/var/www/panel-stub/.test-speed-token"

# CGI: до тела нужны заголовки и пустая строка; иначе nginx читает первую строку тела как HTTP-шапку и даёт «upstream sent invalid header» → 502
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
  echo "Доступ запрещён: укажите корректный ?token= (сохранён в файле на сервере ${TOKEN_FILE}, строка указана также в сообщении панели после применения заглушки)."
  exit 0
fi

# Короткий ответ для массовых проверок из панели (не гоняем скачивания и speedtest — там укладываемся в секунды)
if [[ "&${QUERY_STRING}&" == *"&fleet_check=1&"* ]]; then
  cgi_reply_headers
  echo "=== /test-speed: короткая проверка из панели ==="
  echo "ok ($(date -Iseconds 2>/dev/null || date))"
  exit 0
fi

mbps_line() {
  local label="$1"
  local spd="$2"
  if [[ "$spd" == "" ]] || awk -v s="$spd" 'BEGIN{ exit !(s+0 <= 0); }'; then
    printf '  %-32s %s\n' "${label}" "нет данных / ошибка"
  else
    awk -v lab="$label" -v spd="$spd" 'BEGIN{
      m = spd * 8 / 1024 / 1024
      printf "  %-32s ~ %.2f Мбит/с (оценка curl speed_download)\n", lab, m
    }'
  fi
}

dl_once() {
  local label="$1"
  local url="$2"
  local secs="${3:-26}"
  if ! command -v curl >/dev/null 2>&1; then
    printf '  %-32s %s\n' "${label}" "нет утилиты curl на сервере"
    return 0
  fi
  local spd=""
  spd=$(curl -gLsS --connect-timeout 7 --max-time "$secs" \
    -o /dev/null -w "%{speed_download}" "$url" 2>/dev/null) || spd=0
  mbps_line "$label" "$spd"
}

probe_https() {
  local label="$1"
  local url="$2"
  if ! command -v curl >/dev/null 2>&1; then
    printf '  %-32s %s\n' "${label}" "нет curl"
    return 0
  fi
  local timing
  timing=$(curl -gksS -o /dev/null \
    -w "%{time_connect} %{time_appconnect} %{time_total}" \
    --connect-timeout 4 -m 18 "$url" 2>/dev/null) || timing="- - нет ответа"
  printf '  %-32s connect/TLS/total sec: %s\n' "${label}" "$timing"
}

cgi_reply_headers
echo "=== Исходящие тесты с VPS (${HOSTNAME:-?}, $(date -Iseconds 2>/dev/null || date)) ==="
echo ""
echo "--- Скачивание (Mbps ≈ по curl speed_download, таймаут на каждый источник) ---"
dl_once 'Tele2 10MiB sample' 'http://speedtest.tele2.net/10MB.zip' 26
dl_once 'Cloudflare CDN 10MiB' 'https://speed.cloudflare.com/__down?bytes=10485760' 35
dl_once 'Hetzner DE 100MiB chunk' 'https://speed.hetzner.de/100MB.bin' 50

echo ""
echo "--- Доступность зон через HTTPS (ICMP от пользователя nginx обычно недоступен) ---"
probe_https 'Яндекс' 'https://yandex.ru/robots.txt'
probe_https 'Google 204' 'https://www.google.com/generate_204'
probe_https 'Cloudflare' 'https://cloudflare-dns.com/'
probe_https 'Telegram' 'https://telegram.org/'
probe_https 'DNS Google JSON (DoH заголовки)' 'https://dns.google/resolve?name=example.com&type=A'

echo ""
echo "--- speedtest (если есть в системе; иначе блок пропускается) ---"
# Сначала speedtest-cli (apt install speedtest-cli) — то, что ставит кнопка в админке; иначе часто первым
# попадает Ookla speedtest с другим CLI и другими флагами. Без пайпа в head: иначе SIGPIPE и ложная «ошибка».
if command -v speedtest-cli >/dev/null 2>&1; then
  echo "  speedtest-cli (python, --simple, до ~160 с):"
  if command -v timeout >/dev/null 2>&1; then
    timeout 160 env LC_ALL=C.UTF-8 speedtest-cli --simple 2>&1 || echo "  ошибка или таймаут"
  else
    env LC_ALL=C.UTF-8 speedtest-cli --simple 2>&1 || echo "  ошибка"
  fi
elif command -v speedtest >/dev/null 2>&1; then
  echo "  speedtest (Ookla):"
  if command -v timeout >/dev/null 2>&1; then
    timeout 160 speedtest --accept-license --accept-gdpr --format=human-readable 2>&1 | sed -n '1,20p' || echo "  ошибка/таймаут"
  else
    speedtest --accept-license --accept-gdpr --simple 2>&1 | sed -n '1,20p' || echo "  ошибка"
  fi
else
  echo "  утилиты speedtest / speedtest-cli не найдены (опционально: поставьте через пакеты ОС)."
fi

echo ""
echo "=== Конец отчёта ==="
exit 0
