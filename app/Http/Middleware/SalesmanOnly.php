<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesmanOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::guard('salesman')->check()) {
            return redirect()->away($this->currentOrigin($request) . '/personal/auth');
        }
        return $next($request);
    }

    private function currentOrigin(Request $request): string
    {
        $host = (string) $request->headers->get('x-forwarded-host', '');
        if ($host !== '') {
            $host = trim(explode(',', $host)[0]);
        }
        if ($host === '') {
            $host = $this->allowedRefererHost($request);
        }
        if ($host === '') {
            $host = (string) $request->getHost();
        }

        $proto = (string) $request->headers->get('x-forwarded-proto', '');
        if ($proto !== '') {
            $proto = trim(explode(',', $proto)[0]);
        }
        if ($proto === '') {
            $referer = (string) $request->headers->get('referer', '');
            $refererHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
            if ($refererHost !== '' && strcasecmp($refererHost, $host) === 0) {
                $proto = (string) parse_url($referer, PHP_URL_SCHEME);
            }
        }
        if ($proto === '') {
            $proto = 'https';
        }

        return strtolower($proto) . '://' . $host;
    }

    private function allowedRefererHost(Request $request): string
    {
        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return '';
        }

        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') {
            return '';
        }

        $allowedHosts = array_map('strtolower', (array) config('app.pwa_service_worker_hosts', []));
        return in_array($host, $allowedHosts, true) ? $host : '';
    }
}
