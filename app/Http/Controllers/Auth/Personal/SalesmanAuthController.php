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
        // Для локального тестирования - пропускаем Telegram бота
//        if (app()->environment('local')) {
//            $testUser = [
//                'id' => 12345678, // Тестовый Telegram ID
//                'first_name' => 'TestUser',
//                'username' => 'test_user'
//            ];
//
//            $salesman = Salesman::updateOrCreate(
//                ['telegram_id' => $testUser['id']],
//                [
//                    'name' => $testUser['first_name'],
//                    'username' => $testUser['username'],
//                ]
//            );
//
//            Auth::guard('salesman')->login($salesman);
//            return redirect()->route('personal.dashboard');
//        }

        $state = Str::random(40);
        session(['telegram_auth_state' => $state]);

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
            $action = $request->input('action'); // Получаем параметр action

            // Проверяем наличие обязательных параметров
            if (empty($hash) || empty($userId)) {
                Log::error('Missing auth parameters', [
                    'hash' => $hash,
                    'user_id' => $userId
                ]);
                return redirect()->route('personal.auth')
                    ->with('error', 'Недостаточно данных для авторизации');
            }

            // Проверяем наличие данных в кэше
            if (!Cache::has("telegram_auth:{$hash}")) {
                Log::error('Invalid auth hash', ['hash' => $hash]);
                return redirect()->route('personal.auth')
                    ->with('error', 'Ссылка авторизации устарела или недействительна');
            }

            $authData = Cache::get("telegram_auth:{$hash}");

            // Сверяем user_id
            if ($authData['user_id'] != $userId) {
                Log::error('User ID mismatch', [
                    'expected' => $authData['user_id'],
                    'actual' => $userId
                ]);
                return redirect()->route('personal.auth')
                    ->with('error', 'Ошибка проверки пользователя');
            }

            // Ищем продавца
            $salesman = Salesman::where('telegram_id', $userId)->first();

            if (!$salesman) {
                Log::error('Salesman not found', ['user_id' => $userId]);
                return redirect()->route('personal.auth')
                    ->with('error', 'Продавец не найден');
            }

            // Выполняем вход
            Auth::guard('salesman')->login($salesman);
            Cache::forget("telegram_auth:{$hash}");

            Log::info('Successful salesman login', [
                'salesman_id' => $salesman->id,
                'telegram_id' => $userId
            ]);

            // Проверяем, что авторизация прошла успешно
            if (!Auth::guard('salesman')->check()) {
                Log::error('Salesman authentication failed after login attempt', [
                    'salesman_id' => $salesman->id
                ]);
                return redirect()->route('personal.auth')
                    ->with('error', 'Ошибка входа в систему');
            }

            // Если action=profile, перенаправляем сразу в личный кабинет
            if ($action === 'profile') {
                return redirect()->route('personal.dashboard')
                    ->with('success', 'Вы успешно авторизованы');
            }

            // Редирект с очисткой URL от параметров авторизации
            return redirect()->intended(route('personal.dashboard'))
                ->with('success', 'Вы успешно авторизованы');

        } catch (\Exception $e) {
            Log::error('Auth callback error: '.$e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return redirect()->route('personal.auth')
                ->with('error', 'Произошла ошибка при авторизации');
        }
    }
}
