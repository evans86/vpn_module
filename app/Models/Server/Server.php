<?php

namespace App\Models\Server;

use App\Models\Location\Location;
use App\Models\Panel\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $provider_id
 * @property string|null $ip
 * @property string|null $login
 * @property string|null $password
 * @property string|null $name
 * @property string|null $dns_record_id
 * @property string|null $host
 * @property string|null $provider
 * @property int|null $location_id
 * @property int|null $server_status
 * @property bool|null $is_free
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Panel|null $panel
 * @property-read Location|null $location
 */
class Server extends Model
{
    use HasFactory;

    protected $table = 'server';

    protected $fillable = [
        'name',
        'ip',
        'login',
        'password',
        'host',
        'provider',
        'location_id',
        'server_status',
        'is_free',
        'logs_upload_enabled'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // Базовые типы
    ];

    /**
     * Get the password attribute (with backward compatibility for unencrypted data)
     */
    public function getPasswordAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        
        try {
            // Пытаемся расшифровать (если данные зашифрованы)
            return decrypt($value);
        } catch (\Illuminate\Contracts\Encryption\DecryptException $e) {
            // Если не удалось расшифровать, значит данные не зашифрованы - возвращаем как есть
            return $value;
        }
    }

    /**
     * Set the password attribute (always encrypt)
     */
    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['password'] = encrypt($value);
        } else {
            $this->attributes['password'] = null;
        }
    }

    /**
     * Get the login attribute (with backward compatibility for unencrypted data)
     */
    public function getLoginAttribute($value)
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
     * Set the login attribute (always encrypt)
     */
    public function setLoginAttribute($value)
    {
        if (!empty($value)) {
            $this->attributes['login'] = encrypt($value);
        } else {
            $this->attributes['login'] = null;
        }
    }

    // Провайдеры серверов
    public const VDSINA = 'vdsina';

    // Действия с сервером
    public const PASSWORD_UPDATE = 'password_update';
    public const SERVER_DELETE = 'server_delete';
    public const PANEL_CREATE = 'panel_create';
    public const SERVER_CREATE = 'server_create';

    // Статусы серверов
    public const SERVER_CREATED = 0;      // Создан
    public const SERVER_CONFIGURED = 1;    // Настроен
    public const SERVER_ERROR = 2;        // Ошибка
    public const SERVER_DELETED = 3;      // Удален
    public const SERVER_PASSWORD_UPDATE = 4; // Обновление пароля

    // Алиасы для обратной совместимости
    public const STATUS_CONFIGURING = self::SERVER_CREATED;
    public const STATUS_ACTIVE = self::SERVER_CONFIGURED;
    public const STATUS_ERROR = self::SERVER_ERROR;

    /**
     * Get the panel associated with the server.
     */
    public function panel(): HasOne
    {
        return $this->hasOne(Panel::class);
    }

    /**
     * Get the location associated with the server.
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the status label.
     */
    public function getStatusLabelAttribute(): string
    {
        switch ($this->server_status) {
            case self::SERVER_CREATED:
                return 'Создан';
            case self::SERVER_CONFIGURED:
                return 'Настроен';
            case self::SERVER_ERROR:
                return 'Ошибка';
            case self::SERVER_DELETED:
                return 'Удален';
            case self::SERVER_PASSWORD_UPDATE:
                return 'Обновление пароля';
            default:
                return 'Неизвестный статус';
        }
    }

    /**
     * Get the status badge class.
     */
    public function getStatusBadgeClassAttribute(): string
    {
        switch ($this->server_status) {
            case self::SERVER_CREATED:
                return 'primary';
            case self::SERVER_CONFIGURED:
                return 'success';
            case self::SERVER_ERROR:
                return 'danger';
            case self::SERVER_DELETED:
                return 'secondary';
            case self::SERVER_PASSWORD_UPDATE:
                return 'warning';
            default:
                return 'info';
        }
    }

    /**
     * Check if the server is in a specific status
     */
    public function isStatus(int $status): bool
    {
        return $this->server_status === $status;
    }

    /**
     * Check if the server can be configured
     */
    public function canBeConfigure(): bool
    {
        return $this->server_status === self::SERVER_CREATED;
    }

    /**
     * Check if the server is active
     */
    public function isActive(): bool
    {
        return $this->server_status === self::SERVER_CONFIGURED;
    }

    /**
     * Check if the server has error
     */
    public function hasError(): bool
    {
        return $this->server_status === self::SERVER_ERROR;
    }

    /**
     * Check if the server is deleted
     */
    public function isDeleted(): bool
    {
        return $this->server_status === self::SERVER_DELETED;
    }
}
