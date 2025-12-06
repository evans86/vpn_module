<?php

namespace App\Models\Panel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PanelErrorHistory extends Model
{
    use HasFactory;

    protected $table = 'panel_error_history';

    protected $fillable = [
        'panel_id',
        'error_message',
        'error_occurred_at',
        'resolved_at',
        'resolution_type',
        'resolution_note',
    ];

    protected $casts = [
        'error_occurred_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    /**
     * Связь с панелью
     */
    public function panel(): BelongsTo
    {
        return $this->belongsTo(Panel::class);
    }
}
