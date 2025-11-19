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
        'resolved_at',
        'notifications_sent', // Добавляем счетчик уведомлений
        'last_notification_sent_at', // Время последнего уведомления
        'key_replaced_at', // Когда ключ был заменен
        'replaced_key_id' // ID нового ключа если был заменен
    ];

    protected $casts = [
        'ip_addresses' => 'array',
        'resolved_at' => 'datetime',
        'last_notification_sent_at' => 'datetime',
        'key_replaced_at' => 'datetime'
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
     * Увеличить счетчик отправленных уведомлений
     */
    public function incrementNotifications(): void
    {
        $this->notifications_sent = ($this->notifications_sent ?? 0) + 1;
        $this->last_notification_sent_at = now();
        $this->save();
    }

    /**
     * Получить количество отправленных уведомлений
     */
    public function getNotificationsSentCount(): int
    {
        return $this->notifications_sent ?? 0;
    }

    /**
     * Получить время последнего уведомления (безопасная версия)
     */
    public function getLastNotificationTime(): ?string
    {
        return $this->last_notification_sent_at ? $this->last_notification_sent_at->format('d.m.Y H:i') : null;
    }

    /**
     * Получить отформатированное время последнего уведомления или прочерк
     */
    public function getLastNotificationTimeFormatted(): string
    {
        return $this->getLastNotificationTime() ?? '-';
    }

    /**
     * Проверить, был ли ключ заменен
     */
    public function isKeyReplaced(): bool
    {
        return !is_null($this->key_replaced_at);
    }

    /**
     * Получить ID замененного ключа
     */
    public function getReplacedKeyId(): ?string
    {
        return $this->replaced_key_id;
    }

    /**
     * Получить иконку для уведомлений (безопасная версия)
     */
    public function getNotificationIconAttribute(): string
    {
        $count = $this->getNotificationsSentCount();
        if ($count === 0) {
            return 'fas fa-bell-slash text-muted';
        } elseif ($count === 1) {
            return 'fas fa-bell text-warning';
        } else {
            return 'fas fa-bell text-success';
        }
    }
}
