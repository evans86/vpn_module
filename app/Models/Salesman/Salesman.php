<?php

namespace App\Models\Salesman;

use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Panel\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $username
 * @property string $telegram_id
 * @property int|null $panel_id
 * @property int|null $module_bot_id
 * @property string $token
 * @property string $bot_link
 * @property string $custom_help_text
 * @property string $status
 * @property string $created_at
 * @property string $bot_active
 * @property Panel|null $panel
 * @property BotModule|null $botModule
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

    public function botModule(): BelongsTo
    {
        return $this->belongsTo(BotModule::class, 'module_bot_id');
    }

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

// Получить все ключи, связанные с продавцом через packSales
    public function keyActivates()
    {
        return $this->hasManyThrough(
            KeyActivate::class,
            PackSalesman::class,
            'salesman_id', // Внешний ключ в таблице pack_salesman
            'pack_salesman_id', // Внешний ключ в таблице key_activate
            'id', // Локальный ключ в таблице salesman
            'id' // Локальный ключ в таблице pack_salesman
        );
    }

    /**
     * Отношение к продажам пакетов
     */
    public function packSales(): HasMany
    {
        return $this->hasMany(PackSalesman::class, 'salesman_id');
    }
}
