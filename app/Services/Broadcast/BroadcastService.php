<?php

namespace App\Services\Broadcast;

use App\Jobs\SendBroadcastChunkJob;
use App\Models\Broadcast\BroadcastCampaign;
use App\Models\Broadcast\BroadcastRecipient;
use App\Models\KeyActivate\KeyActivate;

class BroadcastService
{
    /**
     * Количество получателей (уникальных user_tg_id) без загрузки всех записей.
     * Используется на странице создания рассылки для быстрого отображения.
     */
    public function getEligibleRecipientsCount(): int
    {
        return (int) KeyActivate::query()
            ->where('status', KeyActivate::ACTIVE)
            ->whereNotNull('user_tg_id')
            ->selectRaw('COUNT(DISTINCT user_tg_id) as cnt')
            ->value('cnt');
    }

    /**
     * Возвращает ключи для рассылки: по одному ключу на каждого уникального user_tg_id
     * (активные ключи с активированным пользователем).
     */
    public function getEligibleKeyActivateIds(): array
    {
        return KeyActivate::query()
            ->where('status', KeyActivate::ACTIVE)
            ->whereNotNull('user_tg_id')
            ->orderBy('user_tg_id')
            ->orderBy('id')
            ->get()
            ->unique('user_tg_id')
            ->pluck('id')
            ->values()
            ->all();
    }

    /**
     * Создаёт рассылку (черновик) и заполняет получателей.
     */
    public function createCampaignWithRecipients(string $name, string $message): BroadcastCampaign
    {
        $keyIds = $this->getEligibleKeyActivateIds();
        $campaign = BroadcastCampaign::create([
            'name' => $name,
            'message' => $message,
            'status' => BroadcastCampaign::STATUS_DRAFT,
            'total_recipients' => count($keyIds),
        ]);

        foreach ($keyIds as $keyId) {
            $campaign->recipients()->create([
                'key_activate_id' => $keyId,
                'status' => BroadcastRecipient::STATUS_PENDING,
            ]);
        }

        return $campaign->fresh();
    }

    /**
     * Запускает рассылку (переводит в queued и ставит в очередь на отправку).
     */
    public function startCampaign(BroadcastCampaign $campaign): void
    {
        if (!$campaign->isDraft()) {
            return;
        }

        $campaign->update([
            'status' => BroadcastCampaign::STATUS_QUEUED,
            'started_at' => now(),
        ]);

        SendBroadcastChunkJob::dispatch($campaign->id);
    }
}
