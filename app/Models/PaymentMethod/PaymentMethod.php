<?php

namespace App\Models\PaymentMethod;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $details
 * @property string|null $instructions
 * @property bool $is_active
 * @property int $sort_order
 */
class PaymentMethod extends Model
{
    const TYPE_BANK = 'bank';
    const TYPE_CRYPTO = 'crypto';
    const TYPE_EWALLET = 'ewallet';
    const TYPE_OTHER = 'other';

    use HasFactory;

    protected $guarded = false;
    protected $table = 'payment_methods';

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Получить иконку для типа оплаты
     */
    public function getTypeIcon(): string
    {
        switch ($this->type) {
            case self::TYPE_BANK:
                return '🏦';
            case self::TYPE_CRYPTO:
                return '₿';
            case self::TYPE_EWALLET:
                return '💳';
            default:
                return '💰';
        }
    }

    /**
     * Получить текстовое представление типа
     */
    public function getTypeText(): string
    {
        switch ($this->type) {
            case self::TYPE_BANK:
                return 'Банковский перевод';
            case self::TYPE_CRYPTO:
                return 'Криптовалюта';
            case self::TYPE_EWALLET:
                return 'Электронный кошелек';
            default:
                return 'Другое';
        }
    }
}

