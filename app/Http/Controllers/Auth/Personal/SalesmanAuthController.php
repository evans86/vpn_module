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
            $token = (string) config('telegram.father_bot.token');
            if ($token === '') {
                return back()->with('error', 'Токен Telegram-бота не настроен. Обратитесь к администратору.');
            }

            // Для генерации deep-link не нужно каждый раз переустанавливать webhook.
            $bot = new FatherBotController($token, false);
            $authUrl = $bot->generateAuthUrl();
            if (!$authUrl) {
                return back()->with('error', 'Имя бота не настроено. Обратитесь к администратору.');
            }
            return redirect()->away($authUrl);
        } catch (Exception $e) {
            Log::error('Telegram auth redirect error: ' . $e->getMessage(), [
                'source' => 'telegram',
                'trace' => $e->getTraceAsString(),
            ]);
            return back()->with('error', 'Ошибка при создании ссылки авторизации');
        }
    }

    /**
     * @return RedirectResponse
     */
    public function logout()
    {
        Auth::guard('salesman')->logout();
        return redirect()->away($this->currentOrigin() . '/personal/auth')
            ->with('success', 'Вы успешно вышли из системы');
    }

    /**
     * @return Application|Factory|View|RedirectResponse
     */
    public function showLoginForm()
    {
        if (Auth::guard('salesman')->check()) {
            return redirect()->away($this->currentOrigin() . '/personal/dashboard');
        }

        return view('module.personal.auth.login');
    }

    /**
     * Резервный вход в ЛК по email и паролю (если Telegram недоступен).
     */
    public function loginWithEmail(Request $request): RedirectResponse
    {
        if (! $request->has('_token')) {
            return redirect()->away($this->currentOrigin() . '/personal/auth');
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

        return redirect()->away($this->currentOrigin() . '/personal/dashboard')
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

        return redirect()->away($this->currentOrigin() . '/personal/dashboard')
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
                if (! $this->hasValidSignedFallback($request, (string) $hash, (int) $userId)) {
                    Log::warning('Telegram auth callback without cache entry', [
                        'hash' => $hash,
                        'user_id' => $userId,
                        'source' => 'telegram',
                    ]);
                    throw new Exception('Ссылка входа устарела или кэш был очищен. Нажмите «Войти через Telegram» заново.');
                }

                $authData = [
                    'user_id' => (int) $userId,
                    'source' => 'signed_fallback',
                ];
            }

            $expectedUserId = (int) ($authData['user_id'] ?? 0);
            if ($expectedUserId < 1 || (int) $userId !== $expectedUserId) {
                throw new Exception('Неверные параметры входа');
            }

            $salesman = Salesman::where('telegram_id', $userId)->first();
            if (!$salesman) {
                Log::warning('Telegram auth callback salesman not found', [
                    'telegram_id' => $userId,
                    'source' => 'telegram',
                ]);
                throw new Exception('Продавец с этим Telegram ID не найден. Сначала напишите /start главному боту или проверьте привязку продавца.');
            }

            Auth::guard('salesman')->login($salesman);
            $request->session()->regenerate();
            Cache::forget("telegram_auth:{$hash}");

            // Всегда редиректим в личный кабинет, независимо от источника
            return redirect()->away($this->currentOrigin() . '/personal/dashboard?telegram_login=ok')
                ->with('success', 'Вы успешно авторизованы');

        } catch (Exception $e) {
            Log::error('Telegram auth callback error: ' . $e->getMessage(), [
                'source' => 'telegram',
                'hash' => $request->input('hash'),
                'user_id' => $request->input('user'),
            ]);

            return redirect()->away($this->currentOrigin() . '/personal/auth?auth_error=' . rawurlencode($e->getMessage()))
                ->with('error', 'Ошибка авторизации: ' . $e->getMessage());
        }
    }

    private function currentOrigin(): string
    {
        $host = (string) request()->headers->get('x-forwarded-host', '');
        if ($host !== '') {
            $host = trim(explode(',', $host)[0]);
        }
        if ($host === '') {
            $host = (string) request()->getHost();
        }

        $proto = (string) request()->headers->get('x-forwarded-proto', '');
        if ($proto !== '') {
            $proto = trim(explode(',', $proto)[0]);
        }
        if ($proto === '') {
            $proto = 'https';
        }

        return strtolower($proto) . '://' . $host;
    }

    private function hasValidSignedFallback(Request $request, string $hash, int $userId): bool
    {
        $expires = (int) $request->input('expires', 0);
        $sig = (string) $request->input('sig', '');

        if ($expires < time() || $sig === '') {
            return false;
        }

        return hash_equals($this->authCallbackSignature($hash, $userId, $expires), $sig);
    }

    private function authCallbackSignature(string $hash, int $userId, int $expires): string
    {
        $key = (string) config('app.key');
        if (strpos($key, 'base64:') === 0) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false) {
                $key = $decoded;
            }
        }

        return hash_hmac('sha256', $hash . '|' . $userId . '|' . $expires, $key);
    }
}
