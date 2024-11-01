<?php

namespace App\Models\Pack;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $price
 * @property int|null $period сколько дней действует ключ
 * @property int|null $traffic_limit доступный объем трафика
 * @property int|null $count количество ключей
 * @property int|null $activate_time время за которое надо активировать ключи
 * @property boolean|null $status
 */
class Pack extends Model
{
    const ARCHIVED = 0;
    const ACTIVE = 1;

    use HasFactory;

    protected $guarded = false;
    protected $table = 'pack';
}
