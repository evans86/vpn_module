<?php

namespace App\Models\Bot;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_key
 * @property string $private_key
 * @property int $bot_id
 * @property int|null $version
 * @property int $category_id
 * @property int $percent
 * @property int $is_paid
 * @property int $free_show
 * @property int $secret_user_key
 * @property string|null $tariff_cost
 * @property int|null $bot_user_id
 */
class BotModule extends Model

{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'bot_module';
}
