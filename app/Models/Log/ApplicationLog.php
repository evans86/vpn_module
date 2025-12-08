<?php

namespace App\Models\Log;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class ApplicationLog extends Model
{
    protected $fillable = [
        'level',
        'source',
        'message',
        'context',
        'user_id',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Scope для фильтрации по уровню лога
     *
     * @param Builder $query
     * @param string|array $level
     * @return Builder
     */
    public function scopeByLevel(Builder $query, $level): Builder
    {
        if (is_array($level)) {
            return $query->whereIn('level', $level);
        }
        return $query->where('level', $level);
    }

    /**
     * Scope для фильтрации по источнику
     *
     * @param Builder $query
     * @param string|array $source
     * @return Builder
     */
    public function scopeBySource(Builder $query, $source): Builder
    {
        if (is_array($source)) {
            return $query->whereIn('source', $source);
        }
        return $query->where('source', $source);
    }

    /**
     * Scope для поиска по сообщению
     *
     * @param Builder $query
     * @param string $search
     * @return Builder
     */
    public function scopeSearchMessage(Builder $query, string $search): Builder
    {
        return $query->where('message', 'LIKE', "%{$search}%");
    }

    /**
     * Scope для фильтрации по пользователю
     *
     * @param Builder $query
     * @param int|array $userId
     * @return Builder
     */
    public function scopeByUser(Builder $query, $userId): Builder
    {
        if (is_array($userId)) {
            return $query->whereIn('user_id', $userId);
        }
        return $query->where('user_id', $userId);
    }

    /**
     * Scope для фильтрации по дате создания
     *
     * @param Builder $query
     * @param string $startDate
     * @param string|null $endDate
     * @return Builder
     */
    public function scopeByDateRange(Builder $query, string $startDate, string $endDate = null): Builder
    {
        if ($endDate) {
            return $query->whereBetween('created_at', [
                Carbon::parse($startDate)->startOfDay(),
                Carbon::parse($endDate)->endOfDay()
            ]);
        }
        return $query->whereDate('created_at', Carbon::parse($startDate));
    }

    /**
     * Scope для поиска по контексту
     *
     * @param Builder $query
     * @param string $key
     * @param mixed $value
     * @return Builder
     */
    public function scopeByContext(Builder $query, string $key, $value): Builder
    {
        return $query->where('context', 'LIKE', "%\"{$key}\":\"{$value}\"%");
    }

    /**
     * Get color class based on log level
     *
     * @return string
     */
    public function getLevelColorClass(): string
    {
        $colors = [
            'emergency' => 'danger',
            'critical' => 'danger',
            'error' => 'danger',
            'warning' => 'warning',
            'notice' => 'info',
            'info' => 'info',
            'debug' => 'secondary'
        ];

        return isset($colors[$this->level]) ? $colors[$this->level] : 'primary';
    }

    /**
     * Get icon based on log level
     *
     * @return string
     */
    public function getLevelIcon(): string
    {
        $icons = [
            'emergency' => 'fa-skull',
            'critical' => 'fa-skull',
            'error' => 'fa-times-circle',
            'warning' => 'fa-exclamation-triangle',
            'notice' => 'fa-info-circle',
            'info' => 'fa-info',
            'debug' => 'fa-bug'
        ];

        return isset($icons[$this->level]) ? $icons[$this->level] : 'fa-question';
    }

    /**
     * Clean old logs
     *
     * @param int $days Number of days to keep logs
     * @return int Number of deleted records
     */
    public static function cleanOldLogs(int $days = 30): int
    {
        return static::where('created_at', '<', Carbon::now()->subDays($days))->delete();
    }

    /**
     * Получить список всех источников логов из БД
     *
     * @return array
     */
    public static function getSourcesList(): array
    {
        return static::distinct()->pluck('source')->toArray();
    }

    /**
     * Получить список всех возможных источников логов в системе
     *
     * @return array
     */
    public static function getAllPossibleSources(): array
    {
        return [
            'server' => 'Сервер',
            'panel' => 'Панель',
            'api' => 'API',
            'telegram' => 'Telegram',
            'cron' => 'Cron',
            'vpn' => 'VPN',
            'cloudflare' => 'Cloudflare',
            'system' => 'Система',
            'key' => 'Ключ',
            'notification' => 'Уведомление',
            'pack' => 'Пакет',
        ];
    }

    /**
     * Получить список всех уровней логов
     *
     * @return array
     */
    public static function getLevelsList(): array
    {
        return static::distinct()->pluck('level')->toArray();
    }
}
