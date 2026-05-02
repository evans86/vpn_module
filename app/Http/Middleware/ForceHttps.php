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
}
