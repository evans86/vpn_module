<?php

namespace App\Services\Bot;

use App\Dto\Bot\BotModuleDto;
use App\Helpers\ApiHelpers;
use App\Models\Bot\BotModule;
use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class BotModuleService
{
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
     */
    public function update(BotModuleDto $dto): BotModule
    {
        $bot = BotModule::query()->where('public_key', $dto->public_key)->where('private_key', $dto->private_key)->first();
        if (empty($bot))
            return ApiHelpers::error('Not found module.');

        $bot->version = $dto->version;
        $bot->category_id = $dto->category_id;
        $bot->secret_user_key = $dto->secret_user_key;
        $bot->tariff_cost = $dto->tariff_cost;
        $bot->free_show = $dto->free_show;
        $bot->bot_user_id = $dto->bot_user_id;

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
        $bot = BotModule::query()->where('public_key', $public_key)->where('private_key', $private_key)->first();
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
3️⃣ Вставьте конфигурацию в приложение <a href="https://play.google.com/store/apps/details?id=app.hiddify.com&hl=ru">Hiddify</a> или <a href="https://apps.apple.com/ru/app/streisand/id6450534064">Streisand</a>

<blockquote><b>📁 Пошаговые инструкции:</b></blockquote>
- <a href="https://teletype.in/@bott_manager/UPSEXs-nn66">Android</a> 📱
- <a href="https://teletype.in/@bott_manager/nau_zbkFsdo">iOS</a> 🍏
- <a href="https://teletype.in/@bott_manager/HhKafGko3sO">Windows</a> 🖥️

<blockquote><b>❓ Если VPN не подключается:</b></blockquote>
✅ Убедитесь, что используете <strong>актуальный конфиг</strong>
🔁 Попробуйте <strong>другой протокол</strong>
📲 Смените приложение на <strong>Hiddify</strong> или <strong>Streisand</strong>
🔄 Перезагрузите устройство
💬 Обратитесь в поддержку
TEXT;
    }
}
