<?php

namespace App\Models\Broadcast;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $message
 * @property string $status
 * @property int $total_recipients
 * @property int $delivered_count
 * @property int $failed_count
 * @property \Carbon\Carbon|null $started_at
 * @property \Carbon\Carbon|null $completed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class BroadcastCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'broadcast_campaigns';

    protected $fillable = [
        'name',
        'message',
        'status',
        'total_recipients',
        'delivered_count',
        'failed_count',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'total_recipients' => 'integer',
        'delivered_count' => 'integer',
        'failed_count' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function recipients(): HasMany
    {
        return $this->hasMany(BroadcastRecipient::class, 'broadcast_campaign_id');
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING || $this->status === self::STATUS_QUEUED;
    }

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->status === self::STATUS_CANCELLED;
    }

    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_DRAFT => 'Черновик',
            self::STATUS_QUEUED => 'В очереди',
            self::STATUS_RUNNING => 'Выполняется',
            self::STATUS_COMPLETED => 'Завершена',
            self::STATUS_CANCELLED => 'Отменена',
        ];
        return isset($labels[$this->status]) ? $labels[$this->status] : $this->status;
    }

    public function getPendingCount(): int
    {
        return max(0, $this->total_recipients - $this->delivered_count - $this->failed_count);
    }
}
