#!/usr/bin/env bash
# Проверка DNS и доступности TCP-портов для нод VPN (Marzban / другое).
# Bash 4+ рекомендуется. На Windows: Git Bash или WSL.
#
# Примеры:
#   ./check-node-endpoints.sh 212.34.145.128
#   ./check-node-endpoints.sh vpnserver.example.org
#   ./check-node-endpoints.sh -p 8443,9443,2083 vpn1.example.org 31.58.171.215
#   ./check-node-endpoints.sh -f targets.txt
#
# Формат targets.txt — по одному host или host:ports на строку:
#   212.34.145.128
#   vpn.example.org:8443,9443
#   vpn2.example.org   # допускаются комментарии после # при обрезкой

set -uo pipefail

DEFAULT_PORTS="8443,9443,2083"
TIMEOUT="${TIMEOUT:-3}"
PORTS="$DEFAULT_PORTS"
INPUT_FILE=""
TARGETS=()

usage() {
  sed -n '1,20p' "$0" | tail -n +2
  echo "Использование: $0 [опции] [--] HOST [HOST ...]"
  echo ""
  echo "Опции:"
  echo "  -p LIST   порты через запятую (по умолчанию: $DEFAULT_PORTS)"
  echo "  -f FILE   файл со списком хостов (см. комментарий в начале скрипта)"
  echo "  -t SEC    таймаут TCP в секундах (по умолчанию: $TIMEOUT)"
  echo "  -q        только итог: OK/FAIL без подробностей DNS"
  echo "  -h        эта справка"
}

QUIET=0

log() {
  [[ "$QUIET" -eq 1 ]] && return
  printf '%s\n' "$*"
}

is_ip4() {
  [[ "$1" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]]
}

lookup_ipv4() {
  local host="$1"
  if is_ip4 "$host"; then
    printf '%s' "$host"
    return 0
  fi
  if command -v getent >/dev/null 2>&1; then
    getent ahosts "$host" 2>/dev/null | awk '/STREAM/ && $NF ~ /^([0-9]{1,3}\.){3}[0-9]{1,3}$/ { print $1; exit }'
    return "${PIPESTATUS[0]}"
  fi
  if command -v dig >/dev/null 2>&1; then
    dig +short A "$host" | grep -E '^([0-9]{1,3}\.){3}[0-9]{1,3}$' | head -1
    return 0
  fi
  if command -v host >/dev/null 2>&1; then
    host -t A "$host" 2>/dev/null | awk '/has address / { print $NF; exit }'
    return 0
  fi
  return 1
}

tcp_open_with_timeout_bash() {
  local host="$1"
  local port="$2"
  if command -v timeout >/dev/null 2>&1; then
    timeout "${TIMEOUT}s" bash -c "exec 3<>/dev/tcp/$host/$port" 2>/dev/null
    return $?
  fi
  # Без GNU timeout: открытие /dev/tcp в фоне + sleep
  bash -c "exec 3<>/dev/tcp/$host/$port" 2>/dev/null &
  local pid=$!
  sleep "$TIMEOUT"
  if kill -0 "$pid" 2>/dev/null; then
    kill "$pid" 2>/dev/null || true
    wait "$pid" 2>/dev/null || true
    return 124
  fi
  wait "$pid"
}

tcp_open() {
  local host="$1"
  local port="$2"
  if command -v nc >/dev/null 2>&1; then
    nc -z -w "$TIMEOUT" "$host" "$port" >/dev/null 2>&1
    return $?
  fi
  tcp_open_with_timeout_bash "$host" "$port"
}

check_ports() {
  local label="$1"
  shift
  local addrs=( "$@" )
  local p
  for p in "${PORTS_ARRAY[@]}"; do
    local ok_any=0
    local addr
    for addr in "${addrs[@]}"; do
      [[ -z "$addr" ]] && continue
      if tcp_open "$addr" "$p"; then
        log "  tcp/$p @$addr ... OK (${TIMEOUT}s)"
        ok_any=1
        break
      fi
    done
    if [[ "$ok_any" -eq 0 ]]; then
      for addr in "${addrs[@]}"; do
        [[ -z "$addr" ]] && continue
        log "  tcp/$p @$addr ... FAIL (timeout ${TIMEOUT}s)"
      done
      return 1
    fi
  done
  return 0
}

parse_ports() {
  IFS=',' read -r -a PORTS_ARRAY <<< "$1"
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    -h|--help)
      usage
      exit 0
      ;;
    -p)
      PORTS="$2"
      shift 2
      ;;
    -f)
      INPUT_FILE="$2"
      shift 2
      ;;
    -t)
      TIMEOUT="$2"
      shift 2
      ;;
    -q)
      QUIET=1
      shift
      ;;
    --)
      shift
      TARGETS+=("$@")
      break
      ;;
    -*)
      echo "Неизвестная опция: $1" >&2
      usage >&2
      exit 2
      ;;
    *)
      TARGETS+=("$1")
      shift
      ;;
  esac
done

parse_ports "$PORTS"

if [[ -n "$INPUT_FILE" ]]; then
  if [[ ! -r "$INPUT_FILE" ]]; then
    echo "Файл не найден или не читается: $INPUT_FILE" >&2
    exit 4
  fi
  while IFS= read -r line || [[ -n "${line:-}" ]]; do
    line="${line%%#*}"
    line="$(echo "$line" | sed 's/[[:space:]]*$//' | sed 's/^[[:space:]]*//')"
    [[ -z "$line" ]] && continue
    TARGETS+=("$line")
  done <"$INPUT_FILE"
fi

if [[ "${#TARGETS[@]}" -eq 0 ]]; then
  echo "Укажите хотя бы один хост или -f файл." >&2
  usage >&2
  exit 2
fi

if [[ "${BASH_VERSINFO[0]:-0}" -lt 4 ]] 2>/dev/null; then
  echo "Подсказка: для некоторых имён вида «host:порты» удобнее bash 4+." >&2
fi

parse_target_raw() {
  local raw="$1"
  host_part=""
  ports_override=""
  # 31.58.171.215:8443 или 31.58.171.215:8443,9443
  if [[ "$raw" =~ ^([0-9]{1,3}(\.[0-9]{1,3}){3}):([0-9][0-9,]*)$ ]]; then
    host_part="${BASH_REMATCH[1]}"
    ports_override="${BASH_REMATCH[3]}"
    return 0
  fi
  # vpn.example.org:8443,9443 — первое двоеточие отделяет хост
  if [[ "$raw" == *:* ]] && [[ ! "$raw" =~ ^([0-9]{1,3}(\.[0-9]{1,3}){3}): ]] && [[ ! "$raw" =~ ^\[ ]]; then
    host_part="${raw%%:*}"
    ports_override="${raw#*:}"
    return 0
  fi
  host_part="$raw"
}

exit_code=0
for raw in "${TARGETS[@]}"; do
  ports_override=""
  parse_target_raw "$raw"

  if [[ -n "${ports_override:-}" ]]; then
    parse_ports "$ports_override"
  else
    parse_ports "$PORTS"
  fi

  log "=== $raw ==="

  ips=()
  if is_ip4 "$host_part"; then
    ips+=( "$host_part" )
    log "  DNS: (это уже IPv4) -> ${host_part}"
  else
    if ip="$(lookup_ipv4 "$host_part")"; then
      ips+=( "$(echo "$ip" | tr '\n' ' ' | awk '{ print $1 }')" )
      log "  DNS A $host_part -> ${ips[0]:-FAIL}"
      if [[ -z "${ips[0]:-}" ]]; then
        log "  ошибка резолва"
        exit_code=1
        continue
      fi
    else
      log "  DNS: не удалось получить A-запись (нужны getent/dig/host)"
      exit_code=1
      continue
    fi
  fi

  label="$raw"
  if ! check_ports "$label" "${ips[@]}"; then
    exit_code=1
  fi
  log ""

  # восстановить глобальные порты если переопределили через host:ports
  if [[ -n "$ports_override" ]]; then
    parse_ports "$PORTS"
  fi
done

exit "$exit_code"
