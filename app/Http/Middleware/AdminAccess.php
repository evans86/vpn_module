<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminAccess
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
        // Проверяем, что пользователь авторизован через guard 'web' (админ)
        if (!Auth::guard('web')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }

        // Дополнительная проверка: убеждаемся, что это не продавец
        // (продавцы используют guard 'salesman')
        if (Auth::guard('salesman')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Access denied. Admin access required.'], 403);
            }
            return redirect()->route('personal.auth')->with('error', 'Доступ запрещен. Требуются права администратора.');
        }

        return $next($request);
    }
}
