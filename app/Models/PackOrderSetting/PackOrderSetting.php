<?php

namespace App\Models\PackOrderSetting;

use App\Models\Pack\Pack;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $pack_id
 * @property bool $is_available
 * @property int $sort_order
 * @property Pack|null $pack
 */
class PackOrderSetting extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'pack_order_settings';

    protected $casts = [
        'is_available' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Отношение к пакету
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(Pack::class, 'pack_id');
    }
}

