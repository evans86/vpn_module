<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Запрет кэширования HTML админки (CDN мог отдавать страницу со старым csrf_token → 419 на POST).
 */
class PreventAdminResponseCaching
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($request->is('admin') || $request->is('admin/*')) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }
}
