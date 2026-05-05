<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Запрет кэширования HTML админки и ЛК (CDN мог отдавать страницу со старым csrf/_token на GET forms → 419).
 */
class PreventAdminResponseCaching
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if ($this->shouldPreventCaching($request)) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate');
            $response->headers->set('Pragma', 'no-cache');
        }

        return $response;
    }

    private function shouldPreventCaching(Request $request): bool
    {
        if ($request->is('admin') || $request->is('admin/*')) {
            return true;
        }

        if ($request->is('_lk', '_lk/*')) {
            return true;
        }

        return $request->is('personal', 'personal/*');
    }
}
