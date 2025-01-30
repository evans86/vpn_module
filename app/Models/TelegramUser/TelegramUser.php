<?php

namespace App\Models\TelegramUser;

use App\Models\Salesman\Salesman;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use phpseclib\Math\BigInteger;

/**
 * @property int $id
 * @property BigInteger|null $salesman_id
 * @property int|null $telegram_id
 * @property string|null $username
 * @property string|null $first_name
 * @property int|null $status
 * @property-read Salesman|null $salesman
 */
class TelegramUser extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $guarded = false;
    protected $table = 'telegram_user_salesman';

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'salesman_id');
    }

}
