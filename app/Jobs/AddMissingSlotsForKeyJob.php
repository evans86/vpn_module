<?php

namespace App\Jobs;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Добавить недостающие провайдер-слоты для одного ключа (в фоне).
 * Вызывается со страницы конфига, чтобы не блокировать отдачу страницы.
 */
class AddMissingSlotsForKeyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 120;

    protected string $keyActivateId;

    public function __construct(string $keyActivateId)
    {
        $this->keyActivateId = $keyActivateId;
    }

    public function handle(KeyActivateService $keyActivateService): void
    {
        $model = KeyActivate::query()->find($this->keyActivateId);
        if (!$model instanceof KeyActivate || $model->status !== KeyActivate::ACTIVE) {
            return;
        }
        try {
            $keyActivateService->addMissingProviderSlots($model, false);
        } catch (\Throwable $e) {
            Log::warning('AddMissingSlotsForKeyJob failed', [
                'key_activate_id' => $this->keyActivateId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
