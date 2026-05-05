<?php

namespace App\Http\Middleware;

use App\Helpers\UrlHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

/**
 * Должен идти сразу после {@see TrustProxies}.
 *
 * В {@see \Illuminate\Support\ServiceProvider::boot()} прокси ещё не «доверены»: Symfony может
 * игнорировать X-Forwarded-Host до вызова TrustProxies, и getHost() отдаёт vhost основного домена —
 * тогда все redirect()->route() ведут на APP_URL даже при заходе с зеркала.
 */
class ForceUrlRootForTrustedHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $defaultRoot = rtrim((string) config('app.url'), '/');

        $multiHosts = config('app.pwa_service_worker_hosts', []);
        $multiHostsLower = is_array($multiHosts) ? array_map('strtolower', $multiHosts) : [];

        $chosenHost = $this->firstTrustedHostAmongCandidates($request, $multiHostsLower);
        if ($chosenHost !== null) {
            $scheme = $this->forwardedScheme($request);
            URL::forceRootUrl($this->originForHost($request, $scheme, $chosenHost));
        } elseif ($defaultRoot !== '') {
            URL::forceRootUrl($defaultRoot);
        }

        return $next($request);
    }

    /**
     * Иногда nginx/fastcgi подставляет в PHP основной server_name (vpn-telegram.com), хотя браузер шлёт Host: vpnhigh.su.
     * Смотрим X-Forwarded-Host (CDN) и сырой HTTP_HOST до getHost().
     *
     * @param  list<string>  $multiHostsLower
     */
    private function firstTrustedHostAmongCandidates(Request $request, array $multiHostsLower): ?string
    {
        $candidates = $this->candidateHosts($request);
        $appCanonical = $this->canonicalAppUrlHost();

        foreach ($candidates as $hostLower) {
            if (! $this->hostIsTrustedAppHost($hostLower, $multiHostsLower)) {
                continue;
            }
            if ($appCanonical !== '' && $hostLower !== $appCanonical) {
                return $hostLower;
            }
        }

        foreach ($candidates as $hostLower) {
            if ($this->hostIsTrustedAppHost($hostLower, $multiHostsLower)) {
                return $hostLower;
            }
        }

        return null;
    }

    private function hostIsTrustedAppHost(string $hostLower, array $multiHostsLower): bool
    {
        if ($hostLower === '') {
            return false;
        }
        if ($multiHostsLower !== [] && in_array($hostLower, $multiHostsLower, true)) {
            return true;
        }

        return UrlHelper::hostMatchesTrustedApplicationPatterns($hostLower);
    }

    private function canonicalAppUrlHost(): string
    {
        $root = rtrim((string) config('app.url'), '/');
        if ($root === '') {
            return '';
        }
        $h = parse_url(str_contains($root, '://') ? $root : 'https://'.$root, PHP_URL_HOST);

        return $h !== null && $h !== '' ? strtolower((string) $h) : '';
    }

    /**
     * @return list<string>
     */
    private function candidateHosts(Request $request): array
    {
        $raw = [];

        $xfh = (string) $request->headers->get('X-Forwarded-Host');
        if ($xfh !== '') {
            foreach (array_map('trim', explode(',', $xfh)) as $part) {
                if ($part !== '') {
                    $raw[] = $this->normalizeHostFromHeader($part);
                }
            }
        }

        $httpHost = (string) $request->server->get('HTTP_HOST');
        if ($httpHost !== '') {
            $raw[] = $this->normalizeHostFromHeader($httpHost);
        }

        $gh = strtolower((string) $request->getHost());
        if ($gh !== '') {
            $raw[] = $this->stripPortFromHost($gh);
        }

        /** @var list<string> */
        return array_values(array_unique(array_filter($raw)));
    }

    private function normalizeHostFromHeader(string $value): string
    {
        return $this->stripPortFromHost(strtolower(trim($value)));
    }

    private function stripPortFromHost(string $hostLower): string
    {
        if ($hostLower === '') {
            return '';
        }
        if (str_starts_with($hostLower, '[')) {
            return $hostLower;
        }
        $parts = explode(':', $hostLower, 2);

        return (count($parts) === 2 && preg_match('/^\d+$/', $parts[1]))
            ? $parts[0]
            : $hostLower;
    }

    private function forwardedScheme(Request $request): string
    {
        $p = trim(explode(',', (string) $request->headers->get('X-Forwarded-Proto'))[0] ?? '');
        if ($p !== '') {
            return strtolower($p);
        }

        return $request->getScheme();
    }

    private function originForHost(Request $request, string $scheme, string $chosenHost): string
    {
        if (str_starts_with($chosenHost, '[')) {
            $authority = $chosenHost;
        } else {
            $authority = str_contains($chosenHost, ':') && ! str_starts_with($chosenHost, '[')
                ? '['.$chosenHost.']'
                : $chosenHost;
        }

        $port = $request->getPort();
        $explicit = ($scheme === 'https' && $port !== 443) || ($scheme === 'http' && $port !== 80);
        if ($explicit && $port > 0) {
            return $scheme.'://'.$authority.':'.$port;
        }

        return $scheme.'://'.$authority;
    }
}
