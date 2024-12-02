<?php

namespace App\Models\PackSalesman;

use App\Models\Pack\Pack;
use App\Models\Salesman\Salesman;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $pack_id
 * @property int|null $salesman_id
 * @property int|null $status
 */
class PackSalesman extends Model
{
    const NOT_PAID = 0;
    const PAID = 1;
    const EXPIRED = 2;

    use HasFactory;

    protected $guarded = false;
    protected $table = 'pack_salesman';

    /**
     * Отношение к пакету
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class, 'pack_id');
    }

    /**
     * Отношение к продавцу
     */
    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class, 'salesman_id');
    }

    /**
     * Получить текстовое представление статуса
     */
    public function getStatusText(): string
    {
        switch ($this->status) {
            case self::NOT_PAID:
                return 'Не оплачен';
            case self::PAID:
                return 'Оплачен';
            case self::EXPIRED:
                return 'Истек срок';
            default:
                return 'Неизвестно';
        }
    }

    /**
     * Получить класс бейджа для статуса
     */
    public function getStatusBadgeClass(): string
    {
        switch ($this->status) {
            case self::NOT_PAID:
                return 'badge-warning';
            case self::PAID:
                return 'badge-success';
            case self::EXPIRED:
                return 'badge-danger';
            default:
                return 'badge-secondary';
        }
    }

    /**
     * Проверить, оплачен ли пакет
     */
    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }

    /**
     * Проверить, истек ли срок пакета
     */
    public function isExpired(): bool
    {
        return $this->status === self::EXPIRED;
    }
}
