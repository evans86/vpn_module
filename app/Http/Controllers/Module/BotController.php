<?php

namespace App\Http\Controllers\Module;

use App\Console\Commands\TelegramWebhookCommand;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Telegram\Bot\Api;

class BotController extends Controller
{
    public function index()
    {
        return view('module.bot.index');
    }

    public function updateToken(Request $request): RedirectResponse
    {
        try {
            // Валидация
            $request->validate([
                'token' => 'required|string|min:45|max:55'
            ]);

            $token = $request->input('token');
            $envFile = base_path('.env');

            if (file_exists($envFile)) {
                // Читаем содержимое .env файла
                $envContent = file_get_contents($envFile);

                // Обновляем токен
                $envContent = preg_replace(
                    '/TELEGRAM_FATHER_BOT_TOKEN=.*/',
                    'TELEGRAM_FATHER_BOT_TOKEN=' . $token,
                    $envContent
                );

                // Записываем обновленное содержимое
                file_put_contents($envFile, $envContent);

                // Очищаем кэш конфигурации
                Artisan::call('config:clear');

                // Обновляем webhook с новым токеном

                // Используем правильный URL для webhook из конфигурации
                $webhookUrl = config('telegram.father_bot.webhook_url');

                if (empty($webhookUrl)) {
                    // Если URL не настроен в конфиге, формируем URL с учетом токена
                    $webhookUrl = config('app.url') . '/api/telegram/father-bot/' . $token . '/init';
                }

                $telegram = new TelegramWebhookCommand();
                // Устанавливаем webhook
                $telegram->removeWebhook('father', $token);

                return redirect()->back()->with('success', 'Токен бота успешно обновлен и webhook переустановлен');
            }

            return redirect()->back()->with('error', 'Файл .env не найден');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка при обновлении токена: ' . $e->getMessage());
        }
    }
}
