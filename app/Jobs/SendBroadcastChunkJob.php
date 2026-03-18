<?php

namespace App\Jobs;

use App\Models\Broadcast\BroadcastCampaign;
use App\Models\Broadcast\BroadcastRecipient;
use App\Models\KeyActivate\KeyActivate;
use App\Services\Notification\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Отправляет очередную порцию сообщений рассылки (по 30 получателей за раз).
 * После обработки порции при наличии ожидающих ставит себя снова в очередь.
 * Режим теста: передать второй аргумент (массив key_activate_ids) — отправка тем же кодом, без изменения кампании.
 */
class SendBroadcastChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    private const CHUNK_SIZE = 30;

    private const DELAY_BETWEEN_SENDS_MS = 150;

    /** @var int */
    private $broadcastCampaignId;

    /** @var array|null Режим теста: список id ключей для отправки (кампания не меняется). */
    private $testKeyActivateIds;

    public function __construct(int $broadcastCampaignId, ?array $testKeyActivateIds = null)
    {
        $this->broadcastCampaignId = $broadcastCampaignId;
        $this->testKeyActivateIds = $testKeyActivateIds !== null
            ? array_slice(array_filter(array_map('strval', $testKeyActivateIds)), 0, 20)
            : null;
        $this->onConnection('database');
    }

    public function handle(TelegramNotificationService $notificationService): void
    {
        $campaign = BroadcastCampaign::find($this->broadcastCampaignId);
        if (!$campaign) {
            return;
        }

        if ($this->testKeyActivateIds !== null) {
            $this->handleTest($campaign, $notificationService);
            return;
        }

        if ($campaign->isFinished()) {
            return;
        }

        if ($campaign->status === BroadcastCampaign::STATUS_QUEUED) {
            $campaign->update(['status' => BroadcastCampaign::STATUS_RUNNING]);
        }

        $recipients = $campaign->recipients()
            ->where('status', BroadcastRecipient::STATUS_PENDING)
            ->with('keyActivate')
            ->limit(self::CHUNK_SIZE)
            ->get();

        if ($recipients->isEmpty()) {
            $campaign->update([
                'status' => BroadcastCampaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
            return;
        }

        $delivered = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            $key = $recipient->keyActivate;
            if (!$key) {
                $recipient->update([
                    'status' => BroadcastRecipient::STATUS_FAILED,
                    'error_message' => 'Ключ не найден',
                    'sent_at' => now(),
                ]);
                $failed++;
                continue;
            }

            $result = $notificationService->sendToUserWithResult($key, $campaign->message, null);

            if ($result->shouldCountAsSent) {
                $recipient->update([
                    'status' => BroadcastRecipient::STATUS_DELIVERED,
                    'sent_at' => now(),
                ]);
                $delivered++;
            } else {
                $recipient->update([
                    'status' => BroadcastRecipient::STATUS_FAILED,
                    'error_message' => $result->errorMessage ?? 'Ошибка отправки',
                    'sent_at' => now(),
                ]);
                $failed++;
            }

            if (self::DELAY_BETWEEN_SENDS_MS > 0) {
                usleep(self::DELAY_BETWEEN_SENDS_MS * 1000);
            }
        }

        $campaign->increment('delivered_count', $delivered);
        $campaign->increment('failed_count', $failed);

        $hasMore = $campaign->recipients()->where('status', BroadcastRecipient::STATUS_PENDING)->exists();
        if ($hasMore) {
            self::dispatch($this->broadcastCampaignId)->onConnection('database')->delay(now()->addSeconds(2));
        } else {
            $campaign->update([
                'status' => BroadcastCampaign::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    private function handleTest(BroadcastCampaign $campaign, TelegramNotificationService $notificationService): void
    {
        $keys = KeyActivate::query()
            ->whereIn('id', $this->testKeyActivateIds)
            ->where('status', KeyActivate::ACTIVE)
            ->whereNotNull('user_tg_id')
            ->get();

        foreach ($keys as $key) {
            $notificationService->sendToUserWithResult($key, $campaign->message, null);
            if (self::DELAY_BETWEEN_SENDS_MS > 0) {
                usleep(self::DELAY_BETWEEN_SENDS_MS * 1000);
            }
        }
    }
}
