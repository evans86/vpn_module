<?php

namespace App\Services\Bot;

use App\Dto\Bot\BotModuleDto;
use App\Helpers\ApiHelpers;
use App\Models\Bot\BotModule;
use App\Models\Salesman\Salesman;
use App\Services\External\BottApi;
use App\Services\Salesman\SalesmanService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BotModuleService
{
    private SalesmanService $salesmanService;

    public function __construct()
    {
        $this->salesmanService = app(SalesmanService::class);
    }

    /**
     * Создание модуля
     *
     * @param string $public_key
     * @param string $private_key
     * @param int $bot_id
     * @return BotModule
     */
    public function create(string $public_key, string $private_key, int $bot_id): BotModule
    {
        $bot = new BotModule();
        $bot->public_key = $public_key;
        $bot->private_key = $private_key;
        $bot->bot_id = $bot_id;
        $bot->category_id = 0;
        $bot->version = 1;
        $bot->is_paid = 0;
        $bot->free_show = 1;
        $bot->secret_user_key = '';
        $bot->bot_user_id = 0;
        $bot->tariff_cost = '1-150,3-400,6-600,12-1100';
        $bot->vpn_instructions = self::getDefaultVpnInstructions();
        if (!$bot->save())
            throw new RuntimeException('bot dont save');
        return $bot;
    }

    /**
     * Обновление настроек модуля
     *
     * @param BotModuleDto $dto
     * @return BotModule|string
     * @throws GuzzleException
     */
    public function update(BotModuleDto $dto): BotModule
    {
        $bot = BotModule::findByKeys($dto->public_key, $dto->private_key);
        if (empty($bot))
            throw new RuntimeException('Not found module.');

        $bot->version = $dto->version;
        $bot->category_id = $dto->category_id;
        $bot->secret_user_key = $dto->secret_user_key;
        $bot->tariff_cost = $dto->tariff_cost;
        $bot->free_show = $dto->free_show;
        $bot->bot_user_id = $dto->bot_user_id;

        // Имя Telegram-бота модуля (для Profile-Title VPN / ссылок), как в PersonalController::dashboard
        try {
            $botInfo = BottApi::getBot($dto->public_key);
            if (! empty($botInfo['result']) && ! empty($botInfo['data']['name'])) {
                $bot->username = ltrim((string) $botInfo['data']['name'], '@');
            }
        } catch (\Throwable $e) {
            Log::debug('BotModuleService::update getBot username skipped', [
                'public_key' => $dto->public_key,
                'message' => $e->getMessage(),
            ]);
        }

        // Привязка к продавцу через API BOT T — при ошибке не блокируем сохранение настроек модуля
        try {
            $creator = BottApi::getCreator($dto->public_key, $dto->private_key);
            if (isset($creator['data']['user'])) {
                $telegramId = $creator['data']['user']['telegram_id'];
                $username = $creator['data']['user']['username'] ?? null;
                $salesman = Salesman::query()->where('telegram_id', $telegramId)->first();
                if (!$salesman) {
                    $this->salesmanService->create($telegramId, $username);
                    $salesman = Salesman::query()->where('telegram_id', $telegramId)->first();
                }
                if ($salesman) {
                    $salesman->module_bot_id = $dto->id;
                    $salesman->save();
                }
            } else {
                Log::warning('BotModuleService::update getCreator — нет user в ответе', [
                    'public_key' => $dto->public_key,
                    'response' => $creator
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('BotModuleService::update getCreator — ошибка, настройки модуля сохраняем', [
                'public_key' => $dto->public_key,
                'message' => $e->getMessage()
            ]);
        }

        if (!$bot->save())
            throw new RuntimeException('bot dont save');
        return $bot;
    }

    /**
     * Удаление модуля
     *
     * @param string $public_key
     * @param string $private_key
     * @return void
     */
    public function delete(string $public_key, string $private_key): void
    {
        $bot = BotModule::findByKeys($public_key, $private_key);
        if (empty($bot))
            throw new RuntimeException('Not found module.');
        if (!$bot->delete())
            throw new RuntimeException('Bot dont delete');
    }

    public function getDefaultVpnInstructions(): string
    {
        return <<<TEXT
<blockquote><b>🔐 Инструкция по настройке VPN</b></blockquote>
1️⃣ Нажмите кнопку <strong>«Купить»</strong> и приобретите VPN-ключ
2️⃣ Скопируйте конфигурацию полученного 🔑 ключа
3️⃣ Следуйте инструкциям для подключения на различных устройствах

<blockquote><b>📁 Пошаговые инструкции:</b></blockquote>
- <a href="https://teletype.in/@bott_manager/C0WFg-Bsren">Android</a> 🤖
- <a href="https://teletype.in/@bott_manager/8jEexiKqjlEWQ">iOS</a> 🍏
- <a href="https://teletype.in/@bott_manager/kJaChoXUqmZ">Windows</a> 🪟
- <a href="https://teletype.in/@bott_manager/Q8vOQ-_lnQ_">MacOS</a> 💻
- <a href="https://teletype.in/@bott_manager/OIc2Dwer6jV">AndroidTV</a> 📺

<blockquote><b>❓ Если VPN не подключается:</b></blockquote>
✅ Убедитесь, что используете <strong>актуальный конфиг</strong>
🔁 Попробуйте <strong>другой протокол</strong>
📲 Смените VPN-клиент
🔄 Перезагрузите устройство
💬 Обратитесь в поддержку
TEXT;
    }
}
