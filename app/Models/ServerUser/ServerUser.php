<?php

namespace App\Models\ServerUser;

use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Panel\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use phpseclib\Math\BigInteger;

/**
 * @property string $id
 * @property BigInteger|null $panel_id
 * @property string|null $keys
 * @property bool|null $is_free
 * @property string|null $status
 * @property int|null $used_traffic
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Panel|null $panel
 * @property-read KeyActivateUser|null $keyActivateUser
 */
class ServerUser extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $guarded = false;
    protected $table = 'server_user';

    /**
     * Получить панель, к которой привязан пользователь
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class, 'panel_id');
    }

    /**
     * Получить связь с активацией ключа
     */
    public function keyActivateUser(): HasOne
    {
        return $this->hasOne(KeyActivateUser::class, 'server_user_id');
    }

    /**
     * Получить активированный ключ через связь
     */
    public function keyActivate(): ?KeyActivate
    {
        return $this->keyActivateUser ? $this->keyActivateUser->keyActivate : null;
    }
}
