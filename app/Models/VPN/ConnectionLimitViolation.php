<?php

namespace App\Models\VPN;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
use App\Models\ServerUser\ServerUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConnectionLimitViolation extends Model
{
    protected $table = 'connection_limit_violations';

    protected $fillable = [
        'key_activate_id',
        'server_user_id',
        'panel_id',
        'user_tg_id',
        'allowed_connections',
        'actual_connections',
        'ip_addresses',
        'violation_count',
        'status',
        'resolved_at'
    ];

    protected $casts = [
        'ip_addresses' => 'array',
        'resolved_at' => 'datetime'
    ];

    const STATUS_ACTIVE = 'active';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_IGNORED = 'ignored';

    /**
     * Отношение к ключу активации
     */
    public function keyActivate(): BelongsTo
    {
        return $this->belongsTo(KeyActivate::class);
    }

    /**
     * Отношение к пользователю сервера
     */
    public function serverUser(): BelongsTo
    {
        return $this->belongsTo(ServerUser::class);
    }

    /**
     * Отношение к панели
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }

    /**
     * Получить цвет статуса для отображения
     */
    public function getStatusColorAttribute(): string
    {
        switch ($this->status) {
            case self::STATUS_ACTIVE:
                return 'danger';
            case self::STATUS_RESOLVED:
                return 'success';
            case self::STATUS_IGNORED:
                return 'secondary';
            default:
                return 'info';
        }
    }

    /**
     * Получить иконку статуса
     */
    public function getStatusIconAttribute(): string
    {
        switch ($this->status) {
            case self::STATUS_ACTIVE:
                return 'fas fa-exclamation-triangle';
            case self::STATUS_RESOLVED:
                return 'fas fa-check-circle';
            case self::STATUS_IGNORED:
                return 'fas fa-eye-slash';
            default:
                return 'fas fa-info-circle';
        }
    }

    /**
     * Проверить, является ли нарушение активным
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Получить список IP адресов в читаемом формате
     */
    public function getIpAddressesListAttribute(): string
    {
        if (empty($this->ip_addresses)) {
            return 'Неизвестно';
        }

        return implode(', ', array_slice($this->ip_addresses, 0, 5)) .
            (count($this->ip_addresses) > 5 ? '...' : '');
    }

    /**
     * Получить превышение в процентах
     */
    public function getExcessPercentageAttribute(): float
    {
        if ($this->allowed_connections === 0) {
            return 0;
        }

        return round((($this->actual_connections - $this->allowed_connections) / $this->allowed_connections) * 100, 1);
    }

    /**
     * Безопасное получение keyActivate с проверкой
     */
    public function getSafeKeyActivateAttribute()
    {
        try {
            return $this->keyActivate;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Безопасное получение serverUser с проверкой
     */
    public function getSafeServerUserAttribute()
    {
        try {
            return $this->serverUser;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Безопасное получение panel с проверкой
     */
    public function getSafePanelAttribute()
    {
        try {
            return $this->panel;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Получить ID ключа активации безопасно
     */
    public function getKeyActivateIdSafeAttribute()
    {
        $keyActivate = $this->getSafeKeyActivateAttribute();
        return $keyActivate ? $keyActivate->id : 'Удален';
    }
}
