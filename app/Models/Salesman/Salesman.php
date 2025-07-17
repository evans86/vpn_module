<?php

namespace App\Models\Salesman;

use App\Models\Pack\Pack;
use App\Models\Panel\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $username
 * @property string $telegram_id
 * @property int|null $panel_id
 * @property string $token
 * @property string $bot_link
 * @property string $custom_help_text
 * @property string $status
 * @property string $created_at
 * @property string $bot_active
 * @property Panel|null $panel
 * @property string $updated_at
 */
class Salesman extends Authenticatable
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'salesman';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'token',
        'remember_token', // Добавьте это поле в миграцию если нужно
    ];

    /**
     * Отношение к панели
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class, 'panel_id');
    }

    public function packs()
    {
        return $this->belongsToMany(Pack::class)->withTimestamps();
    }

    /**
     * Получить пароль для аутентификации (если не используете стандартный)
     */
    public function getAuthPassword()
    {
        return $this->token; // Используем token как пароль
    }

    /**
     * Получить имя уникального идентификатора (используем telegram_id)
     */
    public function getAuthIdentifierName()
    {
        return 'telegram_id';
    }

    /**
     * Получить значение уникального идентификатора
     */
    public function getAuthIdentifier()
    {
        return $this->telegram_id;
    }
}
