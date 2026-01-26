<?php

namespace App\Models\Order;

use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Salesman\Salesman;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $pack_id
 * @property int|null $salesman_id
 * @property int|null $payment_method_id
 * @property int $status
 * @property float|null $amount
 * @property string|null $payment_proof
 * @property string|null $admin_comment
 * @property int|null $pack_salesman_id
 * @property Pack|null $pack
 * @property Salesman|null $salesman
 * @property PaymentMethod|null $paymentMethod
 * @property PackSalesman|null $packSalesman
 */
class Order extends Model
{
    const STATUS_PENDING = 0; // Ожидает оплаты
    const STATUS_AWAITING_CONFIRMATION = 1; // Ожидает подтверждения (оплата отправлена)
    const STATUS_APPROVED = 2; // Одобрен
    const STATUS_REJECTED = 3; // Отклонен
    const STATUS_CANCELLED = 4; // Отменен

    use HasFactory;

    protected $guarded = false;
    protected $table = 'orders';

    protected $casts = [
        'amount' => 'decimal:2',
        'status' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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
     * Отношение к способу оплаты
     */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method_id');
    }

    /**
     * Отношение к выданному пакету
     */
    public function packSalesman(): BelongsTo
    {
        return $this->belongsTo(PackSalesman::class, 'pack_salesman_id');
    }

    /**
     * Получить текстовое представление статуса
     */
    public function getStatusText(): string
    {
        switch ($this->status) {
            case self::STATUS_PENDING:
                return 'Ожидает оплаты';
            case self::STATUS_AWAITING_CONFIRMATION:
                return 'Ожидает подтверждения';
            case self::STATUS_APPROVED:
                return 'Одобрен';
            case self::STATUS_REJECTED:
                return 'Отклонен';
            case self::STATUS_CANCELLED:
                return 'Отменен';
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
            case self::STATUS_PENDING:
                return 'badge-secondary';
            case self::STATUS_AWAITING_CONFIRMATION:
                return 'badge-warning';
            case self::STATUS_APPROVED:
                return 'badge-success';
            case self::STATUS_REJECTED:
                return 'badge-danger';
            case self::STATUS_CANCELLED:
                return 'badge-dark';
            default:
                return 'badge-secondary';
        }
    }

    /**
     * Проверить, можно ли отменить заказ
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_AWAITING_CONFIRMATION]);
    }

    /**
     * Проверить, можно ли одобрить заказ
     */
    public function canBeApproved(): bool
    {
        return $this->status === self::STATUS_AWAITING_CONFIRMATION;
    }

    /**
     * Проверить, можно ли отклонить заказ
     */
    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_AWAITING_CONFIRMATION;
    }
}

