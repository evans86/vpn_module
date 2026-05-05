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
        $hostLower = strtolower($request->getHost());

        $multiHosts = config('app.pwa_service_worker_hosts', []);
        $multiHostsLower = is_array($multiHosts) ? array_map('strtolower', $multiHosts) : [];

        $onKnownHost =
            $hostLower !== ''
            && (($multiHostsLower !== [] && in_array($hostLower, $multiHostsLower, true))
                || UrlHelper::hostMatchesTrustedApplicationPatterns($hostLower));

        if ($onKnownHost) {
            URL::forceRootUrl($request->getSchemeAndHttpHost());
        } elseif ($defaultRoot !== '') {
            URL::forceRootUrl($defaultRoot);
        }

        return $next($request);
    }
}
