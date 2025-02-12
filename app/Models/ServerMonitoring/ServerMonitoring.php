<?php

namespace App\Models\ServerMonitoring;

use App\Models\Panel\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $pack_id
 * @property string|null $keys
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ServerMonitoring extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $guarded = false;
    protected $table = 'server_monitoring';

    /**
     * Получить панель
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class, 'panel_id');
    }
}
