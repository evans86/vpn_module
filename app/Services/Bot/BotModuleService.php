<?php

namespace App\Services\Bot;

use App\Dto\Bot\BotModuleDto;
use App\Helpers\ApiHelpers;
use App\Models\Bot\BotModule;
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
}
