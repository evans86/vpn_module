#!/usr/bin/env bash
# Эвристика: по IP/домену и открытым портам / ответам HTTP — «похоже на веб» vs «похоже на VPN-ноду».
# Не даёт 100% гарантии; для аудита и скриптов автоматизации.
#
# Использование:
#   ./classify-host-web-or-vpn.sh 31.58.171.215
#   ./classify-host-web-or-vpn.sh vpn.example.com
#
# Переменные:
#   VPN_HINT_PORTS=443,80,8443,9443,2083,2053,2096
set -uo pipefail

TIMEOUT="${TIMEOUT:-3}"
VPN_HINT_PORTS="${VPN_HINT_PORTS:-443,80,8443,9443,2083,2096,8080}"

usage() {
  sed -n '1,12p' "$0" | tail -n +2
  echo "Использование: $0 HOST_OR_IP"
}

if [[ $# -lt 1 || "$1" == "-h" || "$1" == "--help" ]]; then
  usage
  exit 2
fi

TARGET="$1"
shift

is_ip4() {
  [[ "$1" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]
}

resolve_v4() {
  local h="$1"
  if is_ip4 "$h"; then
    echo "$h"
    return 0
  fi
  if command -v getent >/dev/null 2>&1; then
    getent ahosts "$h" 2>/dev/null | awk '/STREAM/ && $NF ~ /^([0-9]{1,3}\.){3}[0-9]{1,3}$/ { print $1; exit }'
    return 0
  fi
  if command -v dig >/dev/null 2>&1; then
    dig +short A "$h" | grep -E '^([0-9]{1,3}\.){3}[0-9]{1,3}$' | head -1
    return 0
  fi
  return 1
}

tcp_open() {
  local ip="$1" port="$2"
  if command -v nc >/dev/null 2>&1; then
    nc -z -w "$TIMEOUT" "$ip" "$port" >/dev/null 2>&1
    return $?
  fi
  bash -c "exec 3<>/dev/tcp/$ip/$port" 2>/dev/null
}

open_ports=()
ip="$(resolve_v4 "$TARGET" | head -1 | tr -d '\r')"
if [[ -z "$ip" ]]; then
  echo "Резолв IPv4: не удалось для $TARGET"
  exit 3
fi

IFS=',' read -r -a PORTS_ARR <<< "$VPN_HINT_PORTS"
for p in "${PORTS_ARR[@]}"; do
  p="$(echo "$p" | tr -d ' ')"
  [[ -z "$p" ]] && continue
  if tcp_open "$ip" "$p"; then
    open_ports+=("$p")
  fi
done

echo "=== $TARGET -> $ip ==="
echo "Открытые TCP (из списка $VPN_HINT_PORTS): ${open_ports[*]:-нет}"

score_vpn=0
score_web=0

# Типичные VPN-панели / прокси-порты
for p in "${open_ports[@]}"; do
  case "$p" in
    8443|9443|2083|2053|2096|8080|1194|51820)
      score_vpn=$((score_vpn + 2))
      ;;
    443|80)
      score_web=$((score_web + 1))
      ;;
  esac
done

hdr=""
if command -v curl >/dev/null 2>&1; then
  if printf '%s' "${open_ports[*]}" | grep -qE '(^| )443( |$)'; then
    hdr="$(curl -gksS -m 8 --connect-timeout 4 -D - -o /dev/null "https://$TARGET/" 2>/dev/null | tr -d '\r' | head -20)"
  elif printf '%s' "${open_ports[*]}" | grep -qE '(^| )80( |$)'; then
    hdr="$(curl -gksS -m 8 --connect-timeout 4 -D - -o /dev/null "http://$TARGET/" 2>/dev/null | tr -d '\r' | head -20)"
  fi
fi

if [[ -n "$hdr" ]]; then
  echo "--- HTTP(S) заголовки (фрагмент) ---"
  echo "$hdr"
  if echo "$hdr" | grep -qiE 'server:\s*nginx|server:\s*caddy|server:\s*Apache'; then
    score_web=$((score_web + 2))
  fi
  if echo "$hdr" | grep -qiE 'cf-ray|cloudflare'; then
    score_web=$((score_web + 1))
  fi
fi

# Исходящий доступ с машины, где запущен скрипт (не с целевого IP)
if command -v curl >/dev/null 2>&1; then
  echo "--- Исходящие HTTPS (эта машина) ---"
  for url in \
    "https://www.google.com/generate_204" \
    "https://cloudflare-dns.com/dns-query" \
    "https://yandex.ru/robots.txt"
  do
    code="$(curl -gksS -o /dev/null -w '%{http_code}' -m 6 --connect-timeout 3 "$url" 2>/dev/null || echo 0)"
    echo "  $(echo "$url" | sed 's|https://||;s|/.*||'): HTTP $code"
  done
fi

echo ""
echo "Оценка (эвристика): VPN-подобие=$score_vpn  веб-подобие=$score_web"
if [[ "$score_vpn" -ge 4 && "$score_vpn" -gt "$score_web" ]]; then
  echo "Вердикт: скорее VPN/прокси-нода (много специфичных портов)."
elif [[ "$score_web" -ge "$score_vpn" ]]; then
  echo "Вердикт: скорее обычный веб/панель на 80/443 (или CDN)."
else
  echo "Вердикт: неоднозначно — смотрите порты и заголовки."
fi
