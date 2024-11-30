<?php

namespace App\Models\Panel;

use App\Models\Server\Server;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
 */
class Panel extends Model
{
    use HasFactory;

    /** @var string Тип панели Marzban */
    const MARZBAN = 'marzban';

    /** @var int Статус: Панель создана */
    const PANEL_CREATED = 1;
    /** @var int Статус: Панель настроена */
    const PANEL_CONFIGURED = 2;
    /** @var int Статус: Ошибка настройки */
    const PANEL_ERROR = 3;

    protected $table = 'panel';
    
    protected $fillable = [
        'panel',
        'panel_adress',
        'panel_login',
        'panel_password',
        'panel_status',
        'server_id',
        'auth_token',
        'token_died_time'
    ];

    protected $hidden = [
        'panel_password',
        'auth_token'
    ];

    protected $casts = [
        'panel_status' => 'integer',
        'token_died_time' => 'integer',
        'server_id' => 'integer'
    ];

    /**
     * Get the server associated with the panel.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
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
     * Get formatted panel address.
     *
     * @return string
     */
    public function getFormattedAddressAttribute(): string
    {
        return parse_url($this->panel_adress, PHP_URL_HOST) ?: $this->panel_adress;
    }
}
