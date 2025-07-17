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

    /**
     * @throws TelegramSDKException
     */
    public function callback(Request $request)
    {
        try {
            $hash = $request->input('hash');
            $userId = $request->input('user');

            if (!Cache::has("telegram_auth:{$hash}")) {
                return redirect()->back()->with('error', 'Недействительная ссылка авторизации');
            }

            $cachedUserId = Cache::get("telegram_auth:{$hash}");

            if ($cachedUserId != $userId) {
                return redirect()->back()->with('error', 'Ошибка проверки пользователя');
            }

            // Находим продавца и авторизуем
            $salesman = Salesman::where('telegram_id', $userId)->first();

            if (!$salesman) {
                return redirect()->back()->with('error', 'Продавец не найден');
            }

            Auth::guard('salesman')->login($salesman);
            Cache::forget("telegram_auth:{$hash}");

            return redirect()->route('personal.dashboard');

        } catch (\Exception $e) {
            Log::error('Auth callback error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Произошла ошибка при авторизации');
        }
    }
}
