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

/**
 * @property string $id
 * @property int|null $panel_id
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
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Базовые типы
    ];

    /**
     * Get the keys attribute (with backward compatibility for unencrypted data)
     */
    public function getKeysAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        try {
            return decrypt($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            return $value;
        }
    }

    /**
     * Set the keys attribute (always encrypt)
     */
    public function setKeysAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['keys'] = encrypt($value);
        } else {
            $this->attributes['keys'] = null;
        }
    }

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
