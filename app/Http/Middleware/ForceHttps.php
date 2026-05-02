<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class ForceHttps
{
    public function handle(Request $request, Closure $next)
    {
        if (!$this->isOriginalRequestSecure($request) && app()->environment('production')) {
            URL::forceScheme('https');

            // 302 превращает POST в GET при редиректе на HTTPS → 405 на POST-маршрутах. 307 сохраняет метод.
            return redirect()->secure($request->getRequestUri(), 307);
        }

        // Add security headers
        $response = $next($request);
        
        if (method_exists($response, 'header')) {
            $response->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('X-XSS-Protection', '1; mode=block');
            $response->header('X-Frame-Options', 'SAMEORIGIN');
            $response->header('Referrer-Policy', 'strict-origin-when-cross-origin');
            $response->header('Alt-Svc', 'h3=":443"; ma=86400');
        }

        return $response;
    }

    /**
     * На проде приложение часто стоит за Cloudflare/nginx: PHP видит http,
     * хотя клиент пришёл по https. Без учёта proxy-заголовков получается redirect loop.
     */
    private function isOriginalRequestSecure(Request $request): bool
    {
        if ($request->secure()) {
            return true;
        }

        if ($this->hostIsConfiguredHttpsPublicHost($request)) {
            return true;
        }

        $forwardedProto = strtolower((string) $request->headers->get('x-forwarded-proto', ''));
        if ($forwardedProto !== '') {
            $first = trim(explode(',', $forwardedProto)[0]);
            if ($first === 'https') {
                return true;
            }
        }

        $cfVisitor = (string) $request->headers->get('cf-visitor', '');
        if ($cfVisitor !== '' && stripos($cfVisitor, '"scheme":"https"') !== false) {
            return true;
        }

        return strtolower((string) $request->headers->get('x-forwarded-ssl', '')) === 'on'
            || strtolower((string) $request->headers->get('front-end-https', '')) === 'on'
            || strtolower((string) $request->server('HTTPS', '')) === 'on';
    }

    private function hostIsConfiguredHttpsPublicHost(Request $request): bool
    {
        $host = $this->originalHost($request);
        if ($host === '') {
            return false;
        }

        $urls = array_filter(array_merge(
            [(string) config('app.url'), (string) config('app.config_public_url'), (string) config('app.public_url')],
            is_array(config('app.mirror_urls')) ? config('app.mirror_urls') : []
        ));

        foreach ($urls as $url) {
            $url = trim((string) $url);
            if ($url === '') {
                continue;
            }

            $normalizedUrl = $this->normalizeUrl($url);
            $scheme = strtolower((string) parse_url($normalizedUrl, PHP_URL_SCHEME));
            $configuredHost = strtolower((string) parse_url($normalizedUrl, PHP_URL_HOST));

            if ($scheme === 'https' && $configuredHost !== '' && $host === $configuredHost) {
                return true;
            }
        }

        return false;
    }

    private function originalHost(Request $request): string
    {
        $forwardedHost = (string) $request->headers->get('x-forwarded-host', '');
        if ($forwardedHost !== '') {
            $first = trim(explode(',', $forwardedHost)[0]);
            if ($first !== '') {
                return strtolower($first);
            }
        }

        return strtolower((string) $request->getHost());
    }

    private function normalizeUrl(string $url): string
    {
        return strpos($url, '://') !== false ? $url : 'https://' . $url;
    }
}
