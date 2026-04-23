#!/usr/bin/env bash
# Автоустановка: sing-box + wgcf, локальный SOCKS5 (127.0.0.1:PORT) -> Cloudflare WARP.
# Требования: root; Debian/Ubuntu (apt) или dnf/yum; curl.
# Опционально: CONFIG_HELPER — путь к marzban-warp-socks-config.py (подставляет Laravel по SFTP).

set -euo pipefail

PORT="${1:-40000}"
STATE_DIR="${STATE_DIR:-/opt/marzban-warp-socks}"
# Версии (при необходимости обновите вручную / форком репозитория)
SING_VERSION="${SING_VERSION:-1.10.7}"
WGCF_VERSION="${WGCF_VERSION:-2.2.22}"

log() { echo "[marzban-warp-socks] $*"; }

die() { log "ОШИБКА: $*"; exit 1; }

need_cmd() { command -v "$1" >/dev/null 2>&1; }

install_pkg() {
  if need_cmd apt-get; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y -qq ca-certificates curl python3
  elif need_cmd dnf; then
    dnf install -y ca-certificates curl python3
  elif need_cmd yum; then
    yum install -y ca-certificates curl python3
  else
    die "Не найден apt-get/dnf/yum. Установите вручную: curl, python3, ca-certificates."
  fi
}

if ! need_cmd curl || ! need_cmd python3; then
  log "Устанавливаю curl/python3…"
  install_pkg
fi

case "$(uname -m)" in
  x86_64)  SB_ARCH=amd64;  WGCF_ARCH=amd64  ;;
  aarch64|arm64) SB_ARCH=arm64; WGCF_ARCH=arm64 ;;
  *) die "Неподдерживаемая архитектура: $(uname -m)" ;;
esac

if ! [[ "$PORT" =~ ^[0-9]+$ ]] || [ "$PORT" -lt 1 ] || [ "$PORT" -gt 65535 ]; then
  die "Некорректный порт: $PORT"
fi

mkdir -p "$STATE_DIR"
cd "$STATE_DIR"

# helper для JSON (из репозитория приложения или встроенный путь)
if [ -n "${CONFIG_HELPER:-}" ] && [ -f "$CONFIG_HELPER" ]; then
  cp -f "$CONFIG_HELPER" "$STATE_DIR/marzban-warp-socks-config.py"
  chmod 600 "$STATE_DIR/marzban-warp-socks-config.py"
elif [ -f "$STATE_DIR/marzban-warp-socks-config.py" ]; then
  :
else
  die "Нет marzban-warp-socks-config.py. Ожидается загрузка CONFIG_HELPER на сервер (админка)."
fi

# --- sing-box ---
SB_TGZ="sing-box-${SING_VERSION}-linux-${SB_ARCH}.tar.gz"
if [ ! -x "$STATE_DIR/sing-box" ]; then
  log "Скачиваю sing-box ${SING_VERSION}…"
  curl -fsSL -o "/tmp/${SB_TGZ}" \
    "https://github.com/SagerNet/sing-box/releases/download/v${SING_VERSION}/${SB_TGZ}"
  EXD="/tmp/marzban-sing-extract-$$"
  mkdir -p "$EXD"
  tar -xzf "/tmp/${SB_TGZ}" -C "$EXD"
  SB_BIN_PATH="$(find "$EXD" -name sing-box -type f 2>/dev/null | head -1)"
  [ -n "$SB_BIN_PATH" ] && [ -x "$SB_BIN_PATH" ] || { rm -rf "$EXD"; die "не найден бинарник sing-box в архиве"; }
  cp -f "$SB_BIN_PATH" "$STATE_DIR/sing-box"
  chmod 755 "$STATE_DIR/sing-box"
  rm -rf "$EXD"
  rm -f "/tmp/${SB_TGZ}" || true
fi
SB_BIN="$STATE_DIR/sing-box"

# --- wgcf ---
WGCF_BIN="wgcf_${WGCF_VERSION}_linux_${WGCF_ARCH}"
if [ ! -x "$STATE_DIR/wgcf" ]; then
  log "Скачиваю wgcf ${WGCF_VERSION}…"
  curl -fsSL -o "$STATE_DIR/wgcf" \
    "https://github.com/ViRb3/wgcf/releases/download/v${WGCF_VERSION}/${WGCF_BIN}"
  chmod 755 "$STATE_DIR/wgcf"
fi
WGCF="$STATE_DIR/wgcf"

# --- WARP (Cloudflare) учётка ---
# --accept-tos: без интерактива (иначе «Do you agree?» зависает в SSH/cron)
if [ ! -f "$STATE_DIR/wgcf-account.toml" ]; then
  log "Регистрация wgcf (WARP)…"
  if ! "$WGCF" register --accept-tos 2>&1; then
    die "wgcf register не удался (лимит Cloudflare, сеть, уже есть аккаунт — смотрите логи выше)."
  fi
fi
log "wgcf generate…"
if ! "$WGCF" generate 2>&1; then
  die "wgcf generate не удался"
fi

# --- config.json ---
export STATE_DIR
if ! python3 "$STATE_DIR/marzban-warp-socks-config.py" "$PORT" "$STATE_DIR" 2>&1; then
  die "не удалось сгенерировать config.json"
fi

if ! "$SB_BIN" check -c "$STATE_DIR/config.json" 2>&1; then
  die "sing-box check не пройден (см. вывод выше)"
fi

# --- systemd ---
UNIT="/etc/systemd/system/marzban-warp-socks.service"
cat >"$UNIT" <<EOF
[Unit]
Description=Marzban WARP local SOCKS (sing-box)
After=network-online.target
Wants=network-online.target

[Service]
Type=simple
WorkingDirectory=$STATE_DIR
ExecStart=$SB_BIN run -c $STATE_DIR/config.json
Restart=on-failure
RestartSec=3

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable marzban-warp-socks.service
if systemctl is-active --quiet marzban-warp-socks.service; then
  systemctl restart marzban-warp-socks.service
else
  systemctl start marzban-warp-socks.service
fi
sleep 1
if ! systemctl is-active --quiet marzban-warp-socks.service; then
  journalctl -u marzban-warp-socks.service -n 30 --no-pager 2>&1 || true
  die "marzban-warp-socks.service не запустился"
fi

# --- smoke test ---
if need_cmd curl; then
  if ! curl -fsS -m 15 -x "socks5h://127.0.0.1:${PORT}" "https://cloudflare.com/cdn-cgi/trace" 2>&1 | head -5; then
    log "Предупреждение: проверка через SOCKS не прошла (firewall, Docker сеть, или WARP ещё поднялся)."
  fi
else
  log "curl нет — пропускаю тест через SOCKS"
fi

log "Готово. SOCKS5: 127.0.0.1:${PORT} (только localhost)."
log "Если Xray в Docker без host-сети, укажите в админке вместо 127.0.0.1 — IP шлюза хоста (часто 172.17.0.1)."

exit 0
