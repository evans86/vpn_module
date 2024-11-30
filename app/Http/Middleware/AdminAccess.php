<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAccess
{
    public function handle(Request $request, Closure $next)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // Здесь можно добавить дополнительные проверки для админ-доступа
        // Например, проверку роли пользователя

        return $next($request);
    }
}
