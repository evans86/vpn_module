<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;
use Illuminate\Http\Request;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array
     */
    protected $except = [
        '/father-bot/init',
        'netcheck/telemetry',
    ];

    /**
     * GET на /_lk/* с изменением состояния: токен в query (стандартный CSRF для GET не проверяется).
     * Prefetch без _token и без «боевых» параметров — разрешён (редирект в контроллере).
     */
    public function handle($request, Closure $next)
    {
        if ($request->isMethod('GET') && str_starts_with($request->path(), '_lk/')) {
            if ($request->query('_token')) {
                if (! hash_equals((string) $request->session()->token(), (string) $request->query('_token'))) {
                    abort(419);
                }
            } elseif (! $this->isLkPrefetchGet($request)) {
                abort(419);
            }
        }

        return parent::handle($request, $next);
    }

    private function isLkPrefetchGet(Request $request): bool
    {
        $paths = [
            '_lk/auth/email',
            '_lk/cabinet-login/save',
            '_lk/faq/save',
            '_lk/faq/reset',
            '_lk/faq/vpn-instructions',
            '_lk/faq/vpn-instructions/reset',
            '_lk/network-check/report',
            '_lk/logout',
        ];
        if (! in_array($request->path(), $paths, true)) {
            return false;
        }
        foreach ($request->query->keys() as $key) {
            if (! str_starts_with($key, 'utm_')) {
                return false;
            }
        }

        return true;
    }
}
