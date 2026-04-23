#!/usr/bin/env python3
# Генерация/слияние config.json sing-box: локальный SOCKS -> WireGuard (WARP) через wgcf.conf.
# Вызывается install-скриптом с: PORT [STATE_DIR]

from __future__ import annotations

import json
import os
import re
import sys
from pathlib import Path


def parse_wgcf(text: str) -> dict:
    sections: dict[str, dict[str, str]] = {}
    cur: str | None = None
    for line in text.splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        if line.startswith("[") and line.endswith("]"):
            cur = line[1:-1]
            sections[cur] = {}
        elif "=" in line and cur:
            k, v = line.split("=", 1)
            sections[cur][k.strip()] = v.strip()
    return sections


def parse_endpoint(raw: str) -> tuple[str, int]:
    ep = raw.strip()
    if ep.startswith("["):
        i = ep.find("]:")
        if i != -1:
            return ep[1:i], int(ep[i + 2 :])
    if ":" in ep:
        a, b = ep.rsplit(":", 1)
        if b.isdigit():
            return a.strip("[]"), int(b)
    return "engage.cloudflareclient.com", 2408


def build_config(sections: dict) -> dict:
    iface = sections.get("Interface", {})
    peer = sections.get("Peer", {})
    priv = iface.get("PrivateKey", "")
    addrs = [x.strip() for x in iface.get("Address", "").split(",") if x.strip()]
    if not addrs and iface.get("Address"):
        addrs = [iface["Address"].strip()]
    pub = peer.get("PublicKey", "bmXOC+F1FxEMF9dyiK2H5/1SUtzH0JuVo51h2wPfgyo=")
    ep, sp = parse_endpoint(peer.get("Endpoint", "engage.cloudflareclient.com:2408"))
    reserved = [0, 0, 0]
    raws = iface.get("Reserved", "")
    if raws:
        try:
            parts = re.split(r"[,\s]+", raws.strip())
            got = [int(x) for x in parts if x.isdigit()][:3]
            reserved = (got + [0, 0, 0])[:3]
        except (ValueError, TypeError):
            pass
    if not addrs or not priv:
        raise SystemExit("wgcf.conf: нет PrivateKey или Address — выполните wgcf generate")
    return {
        "type": "wireguard",
        "tag": "warp",
        "server": ep,
        "server_port": sp,
        "local_address": addrs,
        "private_key": priv,
        "peer_public_key": pub,
        "reserved": reserved,
    }


def main() -> None:
    if len(sys.argv) < 2:
        print("usage: marzban-warp-socks-config.py PORT [STATE_DIR]", file=sys.stderr)
        raise SystemExit(1)
    port = int(sys.argv[1])
    if port < 1 or port > 65535:
        raise SystemExit("invalid port")
    state = sys.argv[2] if len(sys.argv) > 2 else os.environ.get("STATE_DIR", "/opt/marzban-warp-socks")
    state_p = Path(state)
    # wgcf generate пишет wgcf-profile.conf; старые инструкции — wgcf.conf
    for name in ("wgcf.conf", "wgcf-profile.conf"):
        candidate = state_p / name
        if candidate.is_file():
            wgcf_path = candidate
            break
    else:
        raise SystemExit(
            "нет WireGuard-профиля (wgcf.conf или wgcf-profile.conf) в " + str(state_p)
        )
    cfg_path = state_p / "config.json"
    text = wgcf_path.read_text(encoding="utf-8", errors="replace")
    sections = parse_wgcf(text)
    wg_out = build_config(sections)
    inbounds: list[dict] = []
    if cfg_path.is_file():
        try:
            old = json.loads(cfg_path.read_text(encoding="utf-8"))
            for x in old.get("inbounds", []):
                if not (
                    x.get("type") == "socks"
                    and x.get("listen") == "127.0.0.1"
                    and int(x.get("listen_port", 0)) == port
                ):
                    inbounds.append(x)
        except (json.JSONDecodeError, OSError, TypeError, ValueError):
            inbounds = []
    tag = "socks-warp-" + str(port)
    inbounds.append(
        {
            "type": "socks",
            "tag": tag,
            "listen": "127.0.0.1",
            "listen_port": port,
        }
    )
    inbounds = sorted(
        inbounds, key=lambda x: (x.get("listen") or "", int(x.get("listen_port", 0)))
    )
    cfg = {
        "log": {"level": "warning"},
        "inbounds": inbounds,
        "outbounds": [wg_out],
        "route": {"final": "warp"},
    }
    cfg_path.write_text(json.dumps(cfg, ensure_ascii=False, indent=2), encoding="utf-8")
    print("OK: wrote " + str(cfg_path))


if __name__ == "__main__":
    main()
