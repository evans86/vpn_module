<?php

namespace App\Models\Broadcast;

use App\Models\KeyActivate\KeyActivate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $broadcast_campaign_id
 * @property string $key_activate_id
 * @property string $status
 * @property string|null $error_message
 * @property \Carbon\Carbon|null $sent_at
 */
class BroadcastRecipient extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';

    protected $table = 'broadcast_recipients';

    protected $fillable = [
        'broadcast_campaign_id',
        'key_activate_id',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(BroadcastCampaign::class, 'broadcast_campaign_id');
    }

    public function keyActivate(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(KeyActivate::class, 'key_activate_id', 'id');
    }
}
