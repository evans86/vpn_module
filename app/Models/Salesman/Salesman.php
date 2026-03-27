<?php

namespace App\Models\Salesman;

use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Panel\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
 * @property string|null $bot_link
 * @property string $custom_help_text
 * @property string $status
 * @property string $created_at
 * @property string $bot_active
 * @property Panel|null $panel
 * @property BotModule|null $botModule
 * @property string $updated_at
 * @property string|null $email
 * @property string|null $password Хеш пароля для входа в ЛК (не путать с токеном бота)
 * @property string|null $custom_activation_success_text Шаблон HTML сообщения после активации ключа в боте активации
 * @property array|null $activation_success_keyboard_links Кнопки-ссылки под сообщением (текст + url)
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
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'activation_success_keyboard_links' => 'array',
    ];

    /**
     * Get the token attribute (with backward compatibility for unencrypted data)
     */
    public function getTokenAttribute($value)
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
     * Set the token attribute. Храним в открытом виде — по токену ищут вебхук и другие сервисы.
     */
    public function setTokenAttribute($value)
    {
        $this->attributes['token'] = $value ?? null;
    }

    /**
     * Поиск продавца по токену бота. Учитывает старые записи с зашифрованным токеном в БД.
     */
    public static function findByToken(string $token): ?self
    {
        /** @var static|null $salesman */
        $salesman = static::query()->where('token', $token)->first();
        if ($salesman !== null) {
            return $salesman;
        }
        $found = static::query()->get()->first(function ($s) use ($token) {
            return $s->token === $token;
        });

        return $found instanceof static ? $found : null;
    }

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
     * Пароль для входа по email (колонка password). Токен бота — отдельное поле token.
     */
    public function getAuthPassword(): string
    {
        return (string) ($this->attributes['password'] ?? '');
    }

    /**
     * Сессия guard salesman привязана к telegram_id (как при входе через Telegram).
     */
    public function getAuthIdentifierName(): string
    {
        return 'telegram_id';
    }

    /**
     * @return int|string|null
     */
    public function getAuthIdentifier()
    {
        return $this->telegram_id;
    }

    // Отношение для ключей из модуля (прямая связь)
    public function moduleKeyActivates(): HasMany
    {
        return $this->hasMany(KeyActivate::class, 'module_salesman_id');
    }

// Отношение для ключей из бота (через pack_salesman)
    public function botKeyActivates(): HasManyThrough
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

    /**
     * Резервный вход в ЛК по email: включён, если заданы email и хеш пароля (колонка password).
     */
    public function hasCabinetEmailLoginEnabled(): bool
    {
        $email = trim((string) ($this->email ?? ''));
        $pwd = $this->attributes['password'] ?? null;

        return $email !== '' && $pwd !== null && $pwd !== '';
    }
}
