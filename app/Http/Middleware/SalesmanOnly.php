<?php

namespace App\Http\Middleware;

use App\Helpers\UrlHelper;
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
            return redirect()->away(UrlHelper::incomingRequestOrigin($request) . '/personal/auth');
        }
        return $next($request);
    }
}
