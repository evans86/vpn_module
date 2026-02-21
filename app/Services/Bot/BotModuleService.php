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
        $bot = BotModule::query()
            ->where('public_key', $dto->public_key)
            ->where('private_key', $dto->private_key)
            ->first();

        if (empty($bot) && !empty($dto->id)) {
            $bot = BotModule::query()->find($dto->id);
        }
        if (empty($bot)) {
            throw new RuntimeException('Not found module.');
        }

        $bot->version = $dto->version;
        $bot->category_id = $dto->category_id;
        $bot->secret_user_key = $dto->secret_user_key;
        $bot->tariff_cost = $dto->tariff_cost;
        $bot->free_show = $dto->free_show;
        $bot->bot_user_id = $dto->bot_user_id;

        $creator = BottApi::getCreator($dto->public_key, $dto->private_key);
        
        // Проверка наличия данных в ответе API
        if (!isset($creator['data']) || !isset($creator['data']['user'])) {
            Log::error('Invalid API response from getCreator', [
                'public_key' => $dto->public_key,
                'response' => $creator
            ]);
            throw new RuntimeException('Invalid API response: missing user data');
        }

        $telegramId = $creator['data']['user']['telegram_id'];
        $username = $creator['data']['user']['username'] ?? null;
        
        $salesman = Salesman::query()->where('telegram_id', $telegramId)->first();

        if (!isset($salesman)) {
            $this->salesmanService->create($telegramId, $username);
        }

        $salesman = Salesman::query()->where('telegram_id', $telegramId)->first();
        if (!$salesman) {
            throw new RuntimeException('Failed to create or find salesman');
        }
        
        $salesman->module_bot_id = $dto->id;

        if (!$salesman->save())
            throw new RuntimeException('salesman dont save');

        if (!$bot->save())
            throw new RuntimeException('bot dont save');
        return $bot;
    }

    /**
     * Удаление модуля по ключам
     */
    public function delete(string $public_key, string $private_key): void
    {
        $bot = BotModule::query()->where('public_key', $public_key)->where('private_key', $private_key)->first();
        if (empty($bot)) {
            throw new RuntimeException('Not found module.');
        }
        if (!$bot->delete()) {
            throw new RuntimeException('Bot dont delete');
        }
    }

    /**
     * Удаление модуля по id (для вызова из списка BOT T)
     */
    public function deleteById(int $id): void
    {
        $bot = BotModule::query()->find($id);
        if (empty($bot)) {
            throw new RuntimeException('Not found module.');
        }
        if (!$bot->delete()) {
            throw new RuntimeException('Bot dont delete');
        }
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
