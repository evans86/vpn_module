<?php

namespace App\Models\KeyActivate;

use App\Models\PackSalesman\PackSalesman;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use phpseclib\Math\BigInteger;

/**
 * @property string $id
 * @property int|null $traffic_limit лимит трафика на пользователя (сколько осталось)
 * @property int|null $pack_salesman_id кто продавец
 * @property int|null $finish_at дата окончания
 * @property BigInteger|null $user_tg_id кто активировал ключ
 * @property int|null $deleted_at срок, до которого нужно активировать
 * @property int|null $status
 * @property PackSalesman|null $packSalesman
 */
class KeyActivate extends Model
{
    // Статусы ключа
    const EXPIRED = 0;        // Просрочен
    const ACTIVE = 1;         // Активирован и используется
    const PAID = 2;          // Оплачен, ожидает активации
    const DELETED = 3;       // Удален

    use HasFactory;
    public $incrementing = false;
    protected $guarded = false;
    protected $table = 'key_activate';

    /**
     * Get the pack salesman relation
     */
    public function packSalesman()
    {
        return $this->belongsTo(\App\Models\PackSalesman\PackSalesman::class, 'pack_salesman_id');
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
}
