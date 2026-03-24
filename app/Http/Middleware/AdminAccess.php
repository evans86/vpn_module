<?php

namespace App\Http\Middleware;

use App\Helpers\UrlHelper;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Проверяем, что пользователь авторизован через guard 'web' (админ)
        if (!Auth::guard('web')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }
            return redirect()->route('login');
        }

        // Продавец без режима имитации не должен попадать в админку
        if (Auth::guard('salesman')->check()) {
            $impersonatorId = session('impersonation_admin_id');
            if (
                $impersonatorId !== null
                && Auth::guard('web')->check()
                && (int) $impersonatorId === (int) Auth::guard('web')->id()
            ) {
                return $next($request);
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Access denied. Admin access required.'], 403);
            }
            return redirect()->to(UrlHelper::personalRoute('personal.auth'))
                ->with('error', 'Доступ запрещен. Требуются права администратора.');
        }

        return $next($request);
    }
}
