<?php

namespace App\Models\Salesman;

use App\Models\Panel\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $username
 * @property string $telegram_id
 * @property int|null $panel_id
 * @property string $token
 * @property string $bot_link
 * @property string $status
 * @property string $created_at
 * @property string $bot_active
 * @property Panel|null $panel
 * @property string $updated_at
 */
class Salesman extends Model
{
    use HasFactory;

    protected $guarded = false;
    protected $table = 'salesman';

    /**
     * Отношение к панели
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class, 'panel_id');
    }
}
