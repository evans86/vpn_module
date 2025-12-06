<?php

namespace App\Models\Panel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelMonthlyStatistics extends Model
{
    protected $table = 'panel_monthly_statistics';

    protected $fillable = [
        'panel_id',
        'year',
        'month',
        'active_users',
        'online_users',
        'traffic_used_bytes',
        'traffic_limit_bytes',
        'traffic_used_percent',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'active_users' => 'integer',
        'online_users' => 'integer',
        'traffic_used_bytes' => 'integer',
        'traffic_limit_bytes' => 'integer',
        'traffic_used_percent' => 'decimal:2',
    ];

    /**
     * Связь с панелью
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }
}
