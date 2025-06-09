<?php

namespace App\Dto\Bot;

use App\Models\Bot\BotModule;

class BotModuleFactory
{
    public static function fromEntity(BotModule $bot_module): BotModuleDto
    {
        $dto = new BotModuleDto();
        $dto->id = $bot_module->id;
        $dto->public_key = $bot_module->public_key;
        $dto->private_key = $bot_module->private_key;
        $dto->bot_id = $bot_module->bot_id;
        $dto->category_id = $bot_module->category_id;
        $dto->version = $bot_module->version;
        $dto->is_paid = $bot_module->is_paid;
        $dto->secret_user_key = $bot_module->secret_user_key;
        $dto->tariff_cost = $bot_module->tariff_cost;
        $dto->bot_user_id = $bot_module->bot_user_id;

        return $dto;
    }
}
