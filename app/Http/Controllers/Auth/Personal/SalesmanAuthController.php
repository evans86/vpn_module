<?php

namespace App\Http\Controllers\Auth\Personal;

use App\Models\Salesman\Salesman;
use App\Services\Telegram\ModuleBot\FatherBotController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Telegram\Bot\Exceptions\TelegramSDKException;

class SalesmanAuthController extends Controller
{
    /**
     * @throws TelegramSDKException
     * @throws \Exception
     */
    public function redirect()
    {
        $state = Str::random(40);
        session(['telegram_auth_state' => $state]);
        session(['auth_source' => 'website']); // Добавляем метку источника

        $bot = new FatherBotController(env('TELEGRAM_FATHER_BOT_TOKEN'));
        $authUrl = $bot->generateAuthUrl('personal.auth.telegram.callback');

        return redirect()->away($authUrl);
    }

    public function logout(Request $request)
    {
        Auth::guard('salesman')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('personal.auth');
    }

    public function showLoginForm()
    {
        if (Auth::guard('salesman')->check()) {
            return redirect()->route('personal.dashboard');
        }

        return view('module.personal.auth.login'); // Создайте этот view
    }

    /**
     * @throws TelegramSDKException
     */
    public function callback(Request $request)
    {
        try {
            $hash = $request->input('hash');
            $userId = $request->input('user');

            // Проверяем наличие обязательных параметров
            if (empty($hash) || empty($userId)) {
                Log::error('Missing auth parameters', ['hash' => $hash, 'user_id' => $userId]);
                return redirect()->route('personal.auth')->with('error', 'Недостаточно данных для авторизации');
            }

            // Проверяем наличие данных в кэше
            if (!Cache::has("telegram_auth:{$hash}")) {
                Log::error('Invalid auth hash', ['hash' => $hash]);
                return redirect()->route('personal.auth')->with('error', 'Ссылка авторизации устарела или недействительна');
            }

            $authData = Cache::get("telegram_auth:{$hash}");

            // Сверяем user_id
            if ($authData['user_id'] != $userId) {
                Log::error('User ID mismatch', ['expected' => $authData['user_id'], 'actual' => $userId]);
                return redirect()->route('personal.auth')->with('error', 'Ошибка проверки пользователя');
            }

            // Ищем продавца
            $salesman = Salesman::where('telegram_id', $userId)->first();

            if (!$salesman) {
                Log::error('Salesman not found', ['user_id' => $userId]);
                return redirect()->route('personal.auth')->with('error', 'Продавец не найден');
            }

            // Выполняем вход
            Auth::guard('salesman')->login($salesman);
            Cache::forget("telegram_auth:{$hash}");

            // Определяем источник авторизации
            $source = $authData['source'] ?? session('auth_source');

            // Если авторизация инициирована из бота или есть метка 'bot' - редиректим в личный кабинет
            if ($source === 'bot') {
                return redirect()->route('personal.dashboard')->with('success', 'Вы успешно авторизованы');
            }

            // Для авторизации с сайта - редирект на intended URL или в личный кабинет
            return redirect()->intended(route('personal.dashboard'))->with('success', 'Вы успешно авторизованы');

        } catch (\Exception $e) {
            Log::error('Auth callback error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return redirect()->route('personal.auth')->with('error', 'Произошла ошибка при авторизации');
        }
    }
}
