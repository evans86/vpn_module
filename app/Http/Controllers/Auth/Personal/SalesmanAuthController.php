<?php

namespace App\Http\Controllers\Auth\Personal;

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
            return redirect()->away($bot->generateAuthUrl());
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
        return redirect()->route('personal.auth')
            ->with('success', 'Вы успешно вышли из системы');
    }

    /**
     * @return Application|Factory|View|RedirectResponse
     */
    public function showLoginForm()
    {
        if (Auth::guard('salesman')->check()) {
            return redirect()->route('personal.dashboard');
        }

        return view('module.personal.auth.login');
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

            $salesman = Salesman::where('telegram_id', $userId)->first();
            if (!$salesman) {
                throw new Exception('Salesman not found');
            }

            Auth::guard('salesman')->login($salesman);
            Cache::forget("telegram_auth:{$hash}");

            // Всегда редиректим в личный кабинет, независимо от источника
            return redirect()->route('personal.dashboard')
                ->with('success', 'Вы успешно авторизованы');

        } catch (Exception $e) {
            Log::error('Auth error: ' . $e->getMessage());
            return redirect()->route('personal.auth')
                ->with('error', 'Ошибка авторизации: ' . $e->getMessage());
        }
    }
}
