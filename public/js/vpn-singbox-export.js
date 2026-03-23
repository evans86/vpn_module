/**
 * Сборка минимального валидного JSON sing-box из URI (vless/vmess/trojan/ss)
 * для импорта в Hiddify и другие клиенты на sing-box.
 */
(function (global) {
    'use strict';

    function safeTag(name, index) {
        var t = String(name || '')
            .replace(/[#\s]/g, '_')
            .replace(/[^\x20-\x7E]/g, '')
            .replace(/[^a-zA-Z0-9._-]/g, '')
            .slice(0, 48);
        return t || ('node-' + index);
    }

    function ensureUniqueTags(outbounds) {
        var seen = {};
        outbounds.forEach(function (o) {
            var base = o.tag || 'node';
            if (seen[base] === undefined) {
                seen[base] = 0;
            } else {
                seen[base] += 1;
                o.tag = base + '-' + seen[base];
            }
        });
    }

    function parseVlessUri(uri, index) {
        try {
            var u = new URL(uri);
            if (u.protocol !== 'vless:') return null;
            var uuid = decodeURIComponent(u.username || '');
            if (!uuid) return null;
            var server = u.hostname;
            var port = parseInt(u.port, 10) || 443;
            var frag = u.hash ? decodeURIComponent(u.hash.replace(/^#/, '')) : '';
            var q = u.searchParams;
            var type = (q.get('type') || 'tcp').toLowerCase();
            var security = (q.get('security') || 'none').toLowerCase();

            var outbound = {
                type: 'vless',
                tag: safeTag(frag, index),
                server: server,
                server_port: port,
                uuid: uuid
            };
            var flow = q.get('flow');
            if (flow) outbound.flow = flow;

            if (type === 'ws') {
                outbound.transport = {
                    type: 'ws',
                    path: q.get('path') || '/',
                    headers: {}
                };
                var h = q.get('host') || q.get('sni');
                if (h) outbound.transport.headers.Host = h;
            } else if (type === 'grpc') {
                outbound.transport = {
                    type: 'grpc',
                    service_name: q.get('serviceName') || q.get('service_name') || ''
                };
            } else if (type === 'httpupgrade') {
                outbound.transport = {
                    type: 'httpupgrade',
                    path: q.get('path') || '/',
                    host: q.get('host') || '',
                    headers: {}
                };
            }

            if (security === 'tls' || security === 'reality') {
                outbound.tls = {
                    enabled: true,
                    server_name: q.get('sni') || q.get('host') || server
                };
                if (security === 'reality' && q.get('pbk')) {
                    outbound.tls.reality = {
                        public_key: q.get('pbk'),
                        short_id: q.get('sid') || ''
                    };
                }
                if (q.get('fp')) {
                    outbound.tls.utls = { enabled: true, fingerprint: q.get('fp') };
                }
            }
            return outbound;
        } catch (e) {
            return null;
        }
    }

    function parseVmessUri(uri, index) {
        try {
            if (uri.toLowerCase().indexOf('vmess://') !== 0) return null;
            var raw = uri.slice(8).split('#')[0];
            var pad = raw.length % 4;
            if (pad) raw += '===='.slice(0, 4 - pad);
            var json = JSON.parse(atob(raw));
            var add = json.add || json.host;
            var port = parseInt(String(json.port || '443'), 10) || 443;
            var id = json.id || json.uuid;
            if (!add || !id) return null;
            var net = (json.net || 'tcp').toLowerCase();
            var tag = safeTag(json.ps || json.remark || ('vmess-' + index), index);
            var outbound = {
                type: 'vmess',
                tag: tag,
                server: add,
                server_port: port,
                uuid: id,
                security: json.scy != null && json.scy !== '' ? String(json.scy) : 'auto',
                alter_id: parseInt(String(json.aid || '0'), 10) || 0
            };
            if (net === 'ws') {
                outbound.transport = { type: 'ws', path: json.path || '/', headers: {} };
                if (json.host) outbound.transport.headers.Host = json.host;
            } else if (net === 'grpc') {
                outbound.transport = {
                    type: 'grpc',
                    service_name: json.path || json.servicename || ''
                };
            }
            var tlsOn = json.tls === 'tls' || json.tls === true || String(json.tls) === '1';
            if (tlsOn) {
                outbound.tls = { enabled: true, server_name: json.sni || json.host || add };
            }
            return outbound;
        } catch (e) {
            return null;
        }
    }

    function parseTrojanUri(uri, index) {
        try {
            var u = new URL(uri);
            if (u.protocol !== 'trojan:') return null;
            var password = decodeURIComponent(u.username || '');
            var tag = safeTag(u.hash ? decodeURIComponent(u.hash.replace(/^#/, '')) : 'trojan-' + index, index);
            var outbound = {
                type: 'trojan',
                tag: tag,
                server: u.hostname,
                server_port: parseInt(u.port, 10) || 443,
                password: password
            };
            var q = u.searchParams;
            var t = (q.get('type') || 'tcp').toLowerCase();
            if (t === 'ws') {
                outbound.transport = { type: 'ws', path: q.get('path') || '/', headers: {} };
                if (q.get('host')) outbound.transport.headers.Host = q.get('host');
            }
            var sec = (q.get('security') || 'tls').toLowerCase();
            if (sec === 'tls' || sec === '') {
                outbound.tls = {
                    enabled: true,
                    server_name: q.get('sni') || q.get('host') || u.hostname
                };
            }
            return outbound;
        } catch (e) {
            return null;
        }
    }

    function parseShadowsocksUri(uri, index) {
        try {
            if (uri.toLowerCase().indexOf('ss://') !== 0) return null;
            var name = '';
            var rest = uri.slice(5);
            var hashIdx = rest.indexOf('#');
            if (hashIdx >= 0) {
                name = decodeURIComponent(rest.slice(hashIdx + 1));
                rest = rest.slice(0, hashIdx);
            }
            var qIdx = rest.indexOf('?');
            if (qIdx >= 0) rest = rest.slice(0, qIdx);

            var method;
            var password;
            var host;
            var port;

            if (rest.indexOf('@') >= 0) {
                var u = new URL('http://' + rest);
                method = decodeURIComponent(u.username || '');
                password = decodeURIComponent(u.password || '');
                host = u.hostname;
                port = parseInt(u.port, 10) || 8388;
            } else {
                var pad = rest.length % 4;
                if (pad) rest += '===='.slice(0, 4 - pad);
                var decoded = atob(rest);
                var at = decoded.lastIndexOf('@');
                if (at < 0) return null;
                var mp = decoded.slice(0, at).indexOf(':');
                method = decoded.slice(0, mp);
                password = decoded.slice(mp + 1, at);
                var hp = decoded.slice(at + 1);
                var colon = hp.lastIndexOf(':');
                host = hp.slice(0, colon);
                port = parseInt(hp.slice(colon + 1), 10) || 8388;
            }

            if (!method || !host) return null;

            return {
                type: 'shadowsocks',
                tag: safeTag(name, index),
                server: host,
                server_port: port,
                method: method,
                password: password || ''
            };
        } catch (e) {
            return null;
        }
    }

    function parseLinkToOutbound(uri, index) {
        var s = String(uri || '').trim();
        if (!s) return null;
        var low = s.toLowerCase();
        if (low.indexOf('vless://') === 0) return parseVlessUri(s, index);
        if (low.indexOf('vmess://') === 0) return parseVmessUri(s, index);
        if (low.indexOf('trojan://') === 0) return parseTrojanUri(s, index);
        if (low.indexOf('ss://') === 0) return parseShadowsocksUri(s, index);
        return null;
    }

    /**
     * @param {string[]} links
     * @returns {object|null}
     */
    function buildSingBoxProfile(links) {
        var proxies = [];
        for (var i = 0; i < links.length; i++) {
            var o = parseLinkToOutbound(links[i], i);
            if (o) proxies.push(o);
        }
        ensureUniqueTags(proxies);
        if (proxies.length === 0) return null;

        var tags = proxies.map(function (p) {
            return p.tag;
        });

        return {
            log: { level: 'warn' },
            dns: {},
            inbounds: [],
            outbounds: [
                {
                    type: 'selector',
                    tag: 'proxy',
                    outbounds: tags.slice(),
                    default: tags[0]
                },
                {
                    type: 'urltest',
                    tag: 'auto',
                    outbounds: tags.slice(),
                    interval: '10m',
                    tolerance: 50,
                    url: 'https://www.gstatic.com/generate_204'
                }
            ].concat(proxies),
            route: {
                rules: [],
                final: 'auto'
            }
        };
    }

    /**
     * @param {string[]} links
     * @returns {string}
     */
    function buildSingBoxProfileJson(links) {
        var profile = buildSingBoxProfile(links);
        if (!profile) {
            return '';
        }
        return JSON.stringify(profile, null, 2);
    }

    global.buildSingBoxProfile = buildSingBoxProfile;
    global.buildSingBoxProfileJson = buildSingBoxProfileJson;
})(window);
