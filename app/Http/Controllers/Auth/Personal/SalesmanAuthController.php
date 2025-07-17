<?php

namespace App\Http\Controllers\Auth\Personal;

use App\Models\Salesman\Salesman;
use App\Services\Telegram\ModuleBot\FatherBotController;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Telegram\Bot\Exceptions\TelegramSDKException;

class SalesmanAuthController extends Controller
{
    /**
     * @throws TelegramSDKException
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
        return redirect()->away($bot->generateAuthUrl(
            route('personal.auth.telegram.callback'),
            $state
        ));
    }

    /**
     * @throws TelegramSDKException
     */
    public function callback(Request $request)
    {
        // Проверка state параметра
        if ($request->input('state') !== session('telegram_auth_state')) {
            abort(403, 'Неверный state параметр');
        }

        // Проверка хэша авторизации
        $hash = $request->input('hash');
        $telegramId = $request->input('user');

        if (Cache::get("telegram_auth:{$hash}") != $telegramId) {
            abort(403, 'Неверная авторизационная ссылка');
        }

        // Валидация данных пользователя
        $bot = new FatherBotController(env('TELEGRAM_FATHER_BOT_TOKEN'));
        $telegramUser = $bot->validateAuth($request->all());

        if (!$telegramUser) {
            return redirect()->route('login')->withErrors('Ошибка авторизации');
        }

        // Создание/обновление продавца
        $salesman = Salesman::updateOrCreate(
            ['telegram_id' => $telegramUser['id']],
            [
                'name' => $telegramUser['first_name'] ?? 'Unknown',
                'username' => $telegramUser['username'] ?? null,
            ]
        );

        // Аутентификация
        Auth::guard('salesman')->login($salesman);

        // Очистка кэша
        Cache::forget("telegram_auth:{$hash}");

        return redirect()->route('personal.dashboard');
    }
}
