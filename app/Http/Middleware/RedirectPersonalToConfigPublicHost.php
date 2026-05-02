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
        /*
         * Не редиректим host внутри Laravel.
         *
         * На проде vpnhigh.su может проксироваться в тот же backend с внутренним Host
         * основного сайта. В таком случае Laravel видит "не тот" host и бесконечно
         * редиректит браузер обратно на vpnhigh.su, хотя браузер уже там.
         *
         * Ссылки внутри ЛК в основном относительные через UrlHelper::personalRoute(),
         * поэтому безопаснее обработать запрос на текущем origin, чем канонизировать host.
         */
        return $next($request);
    }
}
