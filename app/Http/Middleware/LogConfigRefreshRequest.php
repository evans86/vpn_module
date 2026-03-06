<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Логирует каждый GET-запрос к /config/.../refresh (до маршрутизации).
 * Нужно, чтобы видеть запросы, которые не доходят до контроллера (404, редирект и т.д.).
 */
class LogConfigRefreshRequest
{
    public function handle($request, Closure $next)
    {
        if ($request->isMethod('GET') && str_contains($request->path(), 'config') && str_contains($request->path(), 'refresh')) {
            Log::warning('VpnConfig refresh hit (middleware)', [
                'path' => $request->path(),
                'full_url' => $request->fullUrl(),
                'source' => 'vpn',
            ]);
        }

        return $next($request);
    }
}
