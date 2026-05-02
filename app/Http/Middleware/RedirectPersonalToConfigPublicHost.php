<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Личный кабинет продавца должен открываться на APP_CONFIG_PUBLIC_URL (как конфиги),
 * иначе сессия и CSRF ломаются при абсолютных ссылках на другой хост.
 */
class RedirectPersonalToConfigPublicHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $base = rtrim((string) config('app.config_public_url'), '/');
        $targetHost = parse_url(str_contains($base, '://') ? $base : 'https://' . $base, PHP_URL_HOST);

        $requestHost = $this->originalHost($request);
        if (!$targetHost || strcasecmp($requestHost, (string) $targetHost) === 0) {
            return $next($request);
        }

        $url = $base . '/' . ltrim($request->path(), '/');
        $qs = $request->getQueryString();
        if ($qs !== null && $qs !== '') {
            $url .= '?' . $qs;
        }

        // 302 превращает POST в GET при следовании редиректу — формы ЛК (POST) давали 405 на целевом хосте.
        return redirect()->away($url, 307);
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
}
