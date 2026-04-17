<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Сервер-сервер: заголовок X-External-Activation-Key должен совпадать с config('vpn.external_activation_secret').
 * Пока секрет в .env не задан — 503 (эндпоинт отключён).
 */
class VerifyExternalActivationSecret
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('vpn.external_activation_secret', '');
        if ($secret === '') {
            return response()->json([
                'result' => false,
                'message' => 'Внешняя активация по email не настроена (VPN_EXTERNAL_ACTIVATION_SECRET)',
            ], 503);
        }

        $provided = (string) $request->header('X-External-Activation-Key', '');
        if ($provided === '' || !hash_equals($secret, $provided)) {
            return response()->json([
                'result' => false,
                'message' => 'Доступ запрещён',
            ], 403);
        }

        return $next($request);
    }
}
