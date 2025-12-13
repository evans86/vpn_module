<?php

namespace App\Models\Panel;

use App\Models\Server\Server;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $server_id
 * @property string|null $panel Тип панели (например, 'marzban')
 * @property string|null $panel_adress URL адрес панели
 * @property string|null $panel_login Логин для входа в панель
 * @property string|null $panel_password Пароль для входа в панель
 * @property int|null $panel_status Статус панели (1 - создана, 2 - настроена)
 * @property string|null $auth_token Токен авторизации для API
 * @property int|null $token_died_time Время истечения токена
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Server|null $server Связанный сервер
 * @property-read string $status_label Текстовое описание статуса
 * @property-read string $status_badge_class CSS класс для бейджа статуса
 * @property-read string $panel_type_label Текстовое описание типа панели
 * @property-read string $panel_api_address Форматированный адрес панели для API запросов
 * @property-read string $api_address Форматированный адрес панели для API запросов
 */
class Panel extends Model
{
    use HasFactory;

    /**
     * Имя таблицы
     *
     * @var string
     */
    protected $table = 'panel';

    /**
     * @var string Тип панели Marzban
     */
    const MARZBAN = 'marzban';
    /**
     * @var int Статус: Панель создана
     */
    const PANEL_CREATED = 1;
    /**
     * @var int Статус: Панель настроена
     */
    const PANEL_CONFIGURED = 2;
    /**
     * @var int Статус: Ошибка настройки
     */
    const PANEL_ERROR = 3;
    /**
     * @var int Статус: Панель удалена
     */
    const PANEL_DELETED = 4;

    protected $fillable = [
        'panel',
        'panel_adress',
        'panel_login',
        'panel_password',
        'panel_status',
        'server_id',
        'auth_token',
        'token_died_time',
        'has_error',
        'error_message',
        'error_at',
        'reality_private_key',
        'reality_public_key',
        'reality_short_id',
        'reality_grpc_short_id',
        'reality_xhttp_short_id',
        'reality_keys_generated_at',
        'config_type',
        'config_updated_at'
    ];

    protected $hidden = [
        'panel_password',
        'auth_token'
    ];

    protected $casts = [
        'panel_status' => 'integer',
        'token_died_time' => 'integer',
        'server_id' => 'integer',
        'has_error' => 'boolean',
        'error_at' => 'datetime',
        'reality_keys_generated_at' => 'datetime',
        'config_updated_at' => 'datetime'
    ];

    /**
     * @var string Тип конфига: стабильный (без REALITY)
     */
    const CONFIG_TYPE_STABLE = 'stable';

    /**
     * @var string Тип конфига: с REALITY (лучший обход)
     */
    const CONFIG_TYPE_REALITY = 'reality';

    /**
     * Get the server associated with the panel.
     *
     * @return BelongsTo
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Get the error history for the panel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function errorHistory()
    {
        return $this->hasMany(PanelErrorHistory::class);
    }

    /**
     * Get the status label.
     *
     * @return string
     */
    public function getStatusLabelAttribute(): string
    {
        switch ($this->panel_status) {
            case self::PANEL_CREATED:
                return 'Создана';
            case self::PANEL_CONFIGURED:
                return 'Настроена';
            case self::PANEL_ERROR:
                return 'Ошибка';
            case self::PANEL_DELETED:
                return 'Удалена';
            default:
                return 'Неизвестно';
        }
    }

    /**
     * Get the status badge class.
     *
     * @return string
     */
    public function getStatusBadgeClassAttribute(): string
    {
        switch ($this->panel_status) {
            case self::PANEL_CREATED:
                return 'warning';
            case self::PANEL_CONFIGURED:
                return 'success';
            case self::PANEL_ERROR:
                return 'danger';
            case self::PANEL_DELETED:
                return 'dark';
            default:
                return 'secondary';
        }
    }

    /**
     * Get panel type label.
     *
     * @return string
     */
    public function getPanelTypeLabelAttribute(): string
    {
        switch ($this->panel) {
            case self::MARZBAN:
                return 'Marzban';
            default:
                return 'Неизвестно';
        }
    }

    /**
     * Check if panel is configured.
     *
     * @return bool
     */
    public function isConfigured(): bool
    {
        return $this->panel_status === self::PANEL_CONFIGURED;
    }

    /**
     * Check if panel has valid auth token.
     *
     * @return bool
     */
    public function hasValidToken(): bool
    {
        return $this->auth_token && $this->token_died_time > time();
    }

    /**
     * Check if panel is deleted
     *
     * @return bool
     */
    public function isDeleted(): bool
    {
        return $this->panel_status === self::PANEL_DELETED;
    }

    /**
     * Get formatted panel address.
     *
     * @return string
     */
    public function getFormattedAddressAttribute(): string
    {
        return parse_url($this->panel_adress, PHP_URL_HOST) ?: $this->panel_adress;
    }

    /**
     * Get formatted panel address for API requests.
     *
     * @return string
     */
    public function getPanelApiAddressAttribute(): string
    {
        $address = rtrim($this->panel_adress, '/');
        return preg_replace('#/dashboard/?$#', '', $address);
    }

    /**
     * Get formatted panel address for API requests.
     *
     * @return string
     */
    public function getApiAddressAttribute(): string
    {
        $address = rtrim($this->panel_adress, '/');
        return preg_replace('#/dashboard/?$#', '', $address);
    }

    /**
     * Set the panel address.
     *
     * @param string $value
     * @return void
     */
    public function setPanelAdressAttribute($value)
    {
        // Если адрес не содержит /dashboard/, добавляем его
        if (!str_contains($value, '/dashboard')) {
            $value = rtrim($value, '/') . '/dashboard';
        }
        $this->attributes['panel_adress'] = $value;
    }

    /**
     * Check if panel has REALITY keys generated.
     *
     * @return bool
     */
    public function hasRealityKeys(): bool
    {
        return !empty($this->reality_private_key) 
            && !empty($this->reality_public_key)
            && !empty($this->reality_short_id)
            && !empty($this->reality_grpc_short_id)
            && !empty($this->reality_xhttp_short_id);
    }

    /**
     * Get REALITY keys as array.
     *
     * @return array
     */
    public function getRealityKeys(): array
    {
        return [
            'private_key' => $this->reality_private_key,
            'public_key' => $this->reality_public_key,
            'short_id' => $this->reality_short_id,
            'grpc_short_id' => $this->reality_grpc_short_id,
            'xhttp_short_id' => $this->reality_xhttp_short_id,
            'generated_at' => $this->reality_keys_generated_at
        ];
    }

    /**
     * Get config type label.
     *
     * @return string
     */
    public function getConfigTypeLabelAttribute(): string
    {
        switch ($this->config_type) {
            case self::CONFIG_TYPE_STABLE:
                return 'Стабильный (без REALITY)';
            case self::CONFIG_TYPE_REALITY:
                return 'С REALITY (лучший обход)';
            default:
                return 'Неизвестно';
        }
    }

    /**
     * Get config type badge class.
     *
     * @return string
     */
    public function getConfigTypeBadgeClassAttribute(): string
    {
        switch ($this->config_type) {
            case self::CONFIG_TYPE_STABLE:
                return 'info';
            case self::CONFIG_TYPE_REALITY:
                return 'success';
            default:
                return 'secondary';
        }
    }

    /**
     * Check if using REALITY config.
     *
     * @return bool
     */
    public function isUsingRealityConfig(): bool
    {
        return $this->config_type === self::CONFIG_TYPE_REALITY;
    }

    /**
     * Check if using stable config.
     *
     * @return bool
     */
    public function isUsingStableConfig(): bool
    {
        return $this->config_type === self::CONFIG_TYPE_STABLE;
    }
}
