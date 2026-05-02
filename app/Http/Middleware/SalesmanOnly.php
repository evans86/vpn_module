<?php

namespace App\Http\Middleware;

use Closure;
use App\Helpers\UrlHelper;
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
            return redirect()->to(UrlHelper::personalRoute('personal.auth'));
        }
        return $next($request);
    }
}
