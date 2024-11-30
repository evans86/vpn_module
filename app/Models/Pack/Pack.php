<?php

namespace App\Models\Pack;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int|null $price
 * @property int|null $period сколько дней действует ключ
 * @property int|null $traffic_limit доступный объем трафика
 * @property int|null $count количество ключей
 * @property int|null $activate_time время за которое надо активировать ключи
 * @property boolean|null $status
 */
class Pack extends Model
{
    const ARCHIVED = 0;
    const ACTIVE = 1;

    use HasFactory;
    use SoftDeletes;

    protected $guarded = false;
    protected $table = 'pack';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'price',
        'period',
        'traffic_limit',
        'count',
        'activate_time',
        'status'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'integer',
        'period' => 'integer',
        'traffic_limit' => 'integer',
        'count' => 'integer',
        'activate_time' => 'integer',
        'status' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the formatted price with currency symbol.
     *
     * @return string
     */
    public function getFormattedPriceAttribute(): string
    {
        return number_format($this->price, 0, '.', ' ') . ' ₽';
    }

    /**
     * Get the traffic limit in GB.
     *
     * @return float
     */
    public function getTrafficLimitGbAttribute(): float
    {
        return round($this->traffic_limit / 1024 / 1024 / 1024, 1);
    }

    /**
     * Get the activation time in hours.
     *
     * @return int
     */
    public function getActivateTimeHoursAttribute(): int
    {
        return floor($this->activate_time / 3600);
    }
}
