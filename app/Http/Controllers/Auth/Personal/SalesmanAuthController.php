<?php

namespace App\Http\Controllers\Auth\Personal;

use App\Helpers\UrlHelper;
use App\Models\Salesman\Salesman;
use App\Services\Telegram\ModuleBot\FatherBotController;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Telegram\Bot\Exceptions\TelegramSDKException;

class SalesmanAuthController extends Controller
{
    /**
     * @throws Exception
     */
    public function redirect()
    {
        try {
            $bot = new FatherBotController(env('TELEGRAM_FATHER_BOT_TOKEN'));
            $authUrl = $bot->generateAuthUrl();
            if (!$authUrl) {
                return back()->with('error', 'Имя бота не настроено. Обратитесь к администратору.');
            }
            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('Redirect error: ' . $e->getMessage());
            return back()->with('error', 'Ошибка при создании ссылки авторизации');
        }
    }

    /**
     * @return RedirectResponse
     */
    public function logout()
    {
        Auth::guard('salesman')->logout();
        return redirect()->to(UrlHelper::personalRoute('personal.auth'))
            ->with('success', 'Вы успешно вышли из системы');
    }

    /**
     * @return Application|Factory|View|RedirectResponse
     */
    public function showLoginForm()
    {
        if (Auth::guard('salesman')->check()) {
            return redirect()->to(UrlHelper::personalRoute('personal.dashboard'));
        }

        return view('module.personal.auth.login');
    }

    /**
     * Резервный вход в ЛК по email и паролю (если Telegram недоступен).
     */
    public function loginWithEmail(Request $request): RedirectResponse
    {
        if (! $request->has('_token')) {
            return redirect()->to(UrlHelper::personalRoute('personal.auth'));
        }

        $throttleKey = 'salesman-email-login:' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 10)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => 'Слишком много попыток входа. Повторите через ' . $seconds . ' сек.',
            ]);
        }

        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        if (! Auth::guard('salesman')->attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            $request->boolean('remember')
        )) {
            RateLimiter::hit($throttleKey, 120);

            return back()
                ->withErrors(['email' => 'Неверный email или пароль.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        return redirect()->intended(UrlHelper::personalRoute('personal.dashboard'))
            ->with('success', 'Вы вошли в личный кабинет');
    }

    /**
     * Вход в ЛК по подписанной ссылке из админки (между доменами APP_URL и APP_CONFIG_PUBLIC_URL).
     */
    public function impersonateConsume(Request $request): RedirectResponse
    {
        $salesmanId = (int) $request->query('salesman');
        $adminId = (int) $request->query('admin');
        if ($salesmanId < 1 || $adminId < 1) {
            abort(400);
        }
        $salesman = Salesman::findOrFail($salesmanId);
        session([
            'impersonation_admin_id' => $adminId,
            'impersonation_salesman_id' => $salesman->id,
        ]);
        Auth::guard('salesman')->login($salesman);

        return redirect()->to(UrlHelper::personalRoute('personal.dashboard'))
            ->with('success', 'Режим администратора: вы видите личный кабинет как этот продавец.');
    }

    /**
     * @throws TelegramSDKException
     */
    public function callback(Request $request)
    {
        try {
            $hash = $request->input('hash');
            $userId = $request->input('user');

            if (empty($hash) || empty($userId)) {
                throw new Exception('Missing required parameters');
            }

            $authData = Cache::get("telegram_auth:{$hash}");
            if (!$authData) {
                throw new Exception('Invalid or expired auth session');
            }

            $expectedUserId = (int) ($authData['user_id'] ?? 0);
            if ($expectedUserId < 1 || (int) $userId !== $expectedUserId) {
                throw new Exception('Неверные параметры входа');
            }

            $salesman = Salesman::where('telegram_id', $userId)->first();
            if (!$salesman) {
                throw new Exception('Salesman not found');
            }

            Auth::guard('salesman')->login($salesman);
            Cache::forget("telegram_auth:{$hash}");

            // Всегда редиректим в личный кабинет, независимо от источника
            return redirect()->to(UrlHelper::personalRoute('personal.dashboard'))
                ->with('success', 'Вы успешно авторизованы');

        } catch (Exception $e) {
            Log::error('Auth error: ' . $e->getMessage());
            return redirect()->to(UrlHelper::personalRoute('personal.auth'))
                ->with('error', 'Ошибка авторизации: ' . $e->getMessage());
        }
    }
}
