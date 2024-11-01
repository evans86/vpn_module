<?php

namespace App\Models\KeyActivate;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use phpseclib\Math\BigInteger;

/**
 * @property string $id
 * @property int|null $traffic_limit лимит трафика на пользователя (сколько осталось)
 * @property int|null $pack_salesman_id кто продавец
 * @property int|null $finish_at дата окончания
 * @property BigInteger|null $user_tg_id кто активировал ключ
 * @property int|null $deleted_at срок, до которого нужно активировать
 * @property bool|null $status
 */
class KeyActivate extends Model
{
    const EXPIRED = 0;
    const ACTIVE = 1;

    use HasFactory;
    public $incrementing = false;
    protected $guarded = false;
    protected $table = 'key_activate';
}
