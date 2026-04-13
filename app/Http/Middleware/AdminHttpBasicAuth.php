<?php

namespace App\Http\Middleware;

use App\Services\Admin\AdminBasicAuthTelegramNotifier;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AdminHttpBasicAuth
{
    /**
     * Дополнительная защита раздела /admin через HTTP Basic (учётные данные из .env).
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $user = (string) (config('admin.http_basic_user') ?? '');
        $password = (string) (config('admin.http_basic_password') ?? '');

        if ($user === '' || $password === '') {
            return $next($request);
        }

        $givenUser = $request->getUser();
        $givenPassword = $request->getPassword();

        if (
            $givenUser === null
            || $givenPassword === null
            || ! hash_equals($user, $givenUser)
            || ! hash_equals($password, $givenPassword)
        ) {
            $reason = ($givenUser === null || $givenPassword === null) ? 'missing' : 'invalid';
            $attempted = $givenUser !== null ? (string) $givenUser : null;

            // Неверный логин/пароль — пользователь нажал OK в диалоге (отдельное уведомление).
            // Запрос без Authorization — не уведомляем (ещё не «ввод в форму», только открыли URL).
            if ($reason === 'invalid') {
                App::terminating(function () use ($request, $attempted, $reason): void {
                    app(AdminBasicAuthTelegramNotifier::class)->notifyFailure($request, $attempted, $reason);
                });
            }

            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Admin"',
            ]);
        }

        $basicUsername = (string) $givenUser;
        $response = $next($request);

        // Успех: уведомляем только при первом прохождении Basic в этой сессии браузера.
        // Дальше браузер сам подставляет учётные данные — это не повторный «ввод в форму».
        if ($request->hasSession() && ! $request->session()->get('admin_basic_telegram_success_notified')) {
            $request->session()->put('admin_basic_telegram_success_notified', true);
            App::terminating(function () use ($request, $basicUsername): void {
                app(AdminBasicAuthTelegramNotifier::class)->notifySuccess($request, $basicUsername);
            });
        }

        return $response;
    }
}
