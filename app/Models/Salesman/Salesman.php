<?php

namespace App\Models\Salesman;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $telegram_id
 * @property string|null $username
 * @property string|null $token
 * @property bool|null $status
 * @property string|null $bot_link
 */
class Salesman extends Model
{
    const INACTIVE = 0;
    const ACTIVE = 1;

    use HasFactory;

    protected $guarded = false;
    protected $table = 'salesman';
}
