<?php

namespace App\Models\KeyActivate;

use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\PackSalesman\PackSalesman;
use App\Models\VPN\ConnectionLimitViolation;
use App\Models\Salesman\Salesman;
use App\Models\TelegramUser\TelegramUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property string $id
 * @property int|null $traffic_limit НАЧАЛЬНЫЙ лимит трафика (data_limit для панели, НЕ остаток!)
 * @property int|null $pack_salesman_id кто продавец ключа из бота
 * @property int|null $module_salesman_id кто продавец ключа из модуля
 * @property int|null $finish_at дата окончания
 * @property int|null $user_tg_id кто активировал ключ (наличие означает активацию)
 * @property int|null $deleted_at срок, до которого нужно активировать
 * @property int|null $status статус
 * @property PackSalesman|null $packSalesman
 * @property Salesman|null $moduleSalesman
 * @property KeyActivateUser|null $keyActivateUser
 * @property \Illuminate\Database\Eloquent\Collection<int, KeyActivateUser> $keyActivateUsers
 * 
 * ВАЖНО: Реальный остаток трафика (data_limit - used_traffic) хранится на панели Marzban,
 * а НЕ в этом поле! Это поле содержит НАЧАЛЬНОЕ значение лимита.
 */
class KeyActivate extends Model
{
    const EXPIRED = 0;        // Просрочен
    const ACTIVE = 1;         // Активирован и используется
    const PAID = 2;          // Оплачен, ожидает активации
    const DELETED = 3;       // Удален

    use HasFactory;

    public $incrementing = false;
    protected $guarded = false;
    protected $table = 'key_activate';

    protected $casts = [
        'status' => 'integer',
        'finish_at' => 'integer',
        'deleted_at' => 'integer',
    ];

    /**
     * Get the pack salesman relation
     */
    public function packSalesman(): BelongsTo
    {
        return $this->belongsTo(PackSalesman::class, 'pack_salesman_id');
    }

    /**
     * Get the salesman relation
     */
    public function moduleSalesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'module_salesman_id');
    }

    /**
     * Get the key activate user relation (one, для обратной совместимости — первый слот)
     */
    public function keyActivateUser(): HasOne
    {
        return $this->hasOne(KeyActivateUser::class, 'key_activate_id');
    }

    /**
     * Все слоты ключа (один или несколько провайдеров).
     */
    public function keyActivateUsers(): HasMany
    {
        return $this->hasMany(KeyActivateUser::class, 'key_activate_id');
    }

    /**
     * Активные нарушения лимита подключений (для страницы конфига).
     */
    public function activeViolations(): HasMany
    {
        return $this->hasMany(ConnectionLimitViolation::class, 'key_activate_id')
            ->where('status', ConnectionLimitViolation::STATUS_ACTIVE)
            ->whereNull('key_replaced_at')
            ->orderBy('created_at', 'desc');
    }

    /**
     * Последнее нарушение с перевыпуском ключа (для страницы конфига).
     */
    public function replacedViolation(): HasOne
    {
        return $this->hasOne(ConnectionLimitViolation::class, 'key_activate_id')
            ->whereNotNull('key_replaced_at')
            ->whereNotNull('replaced_key_id')
            ->orderBy('key_replaced_at', 'desc');
    }

    public function getTgStatusText(): string
    {
        switch ($this->status) {
            case self::EXPIRED:
                return '🚫 Срок действия истек';
            case self::ACTIVE:
                return '✅ Активирован';
            case self::PAID:
                return '⚪️ Не активирован';
            case self::DELETED:
                return 'Ключ удален';
            default:
                return 'Неизвестно';
        }
    }

    /**
     * Получить текстовое описание статуса
     *
     * @return string
     */
    public function getStatusText(): string
    {
        switch ($this->status) {
            case self::EXPIRED:
                return 'Просрочен';
            case self::ACTIVE:
                return 'Активирован';
            case self::PAID:
                return 'Оплачен';
            case self::DELETED:
                return 'Удален';
            default:
                return 'Неизвестно';
        }
    }

    /**
     * Получить класс бейджа для статуса
     *
     * @return string
     */
    public function getStatusBadgeClass(): string
    {
        switch ($this->status) {
            case self::EXPIRED:
                return 'badge-danger';
            case self::ACTIVE:
                return 'badge-success';
            case self::PAID:
                return 'badge-info';
            case self::DELETED:
                return 'badge-secondary';
            default:
                return 'badge-warning';
        }
    }

    public function getStatusBadgeClassSalesman(): string
    {
        switch ($this->status) {
            case self::EXPIRED:
                return 'bg-red-100 text-red-800';
            case self::ACTIVE:
                return 'bg-green-100 text-green-800';
            case self::PAID:
                return 'bg-yellow-100 text-yellow-800';
            case self::DELETED:
                return 'bg-gray-100 text-gray-800';
            default:
                return 'badge-warning';
        }
    }

    public function user()
    {
        return $this->belongsTo(TelegramUser::class, 'user_tg_id', 'telegram_id');
    }

    public function getUserNicknameAttribute()
    {
        if ($this->user_tg_id && $this->user) {
            return $this->user->username ?? $this->user->first_name ?? 'Пользователь';
        }

        return $this->user_tg_id ? 'Пользователь #' . $this->user_tg_id : 'Не активирован';
    }

    public function getTrafficInfo()
    {
        if ($this->packSalesman && $this->packSalesman->pack) {
            $pack = $this->packSalesman->pack;
            return $pack->name  . number_format($pack->traffic_limit / (1024*1024*1024), 1) . ' GB';
        }

        return 'Основной пакет удален';
    }

    public function getExpiryDateFormattedAttribute()
    {
        if (!$this->finish_at) {
            return 'Не активирован';
        }

        $expiryDate = Carbon::createFromTimestamp($this->finish_at);
        $now = Carbon::now();

        if ($expiryDate->isPast()) {
            return 'Истек ' . $expiryDate->format('d.m.Y H:i');
        }

        return 'До ' . $expiryDate->format('d.m.Y H:i');
    }

    public function getPeriodInfo()
    {
        if ($this->packSalesman && $this->packSalesman->pack) {
            $pack = $this->packSalesman->pack;
            return $pack->period . ' дн.';
        }

        return 'Основной пакет удален';
    }

    public function getConfigUrlAttribute()
    {
        return \App\Helpers\UrlHelper::configUrl($this->id);
    }

    public function hasConfig()
    {
        return true;
    }
}
