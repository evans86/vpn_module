<?php

namespace App\Jobs;

use App\Models\Log\ApplicationLog;
use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Перевыпуск просроченного ключа в фоне.
 * Убирает таймаут HTTP: запрос сразу возвращает 200, тяжёлая работа выполняется в воркере.
 */
class RenewKeyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300;

    protected string $keyActivateId;

    public function __construct(string $keyActivateId)
    {
        $this->keyActivateId = $keyActivateId;
    }

    public function handle(KeyActivateService $keyActivateService): void
    {
        $key = KeyActivate::query()->find($this->keyActivateId);
        if (!$key instanceof KeyActivate) {
            Log::warning('RenewKeyJob: ключ не найден', ['key_id' => $this->keyActivateId]);
            return;
        }
        if ($key->status !== KeyActivate::EXPIRED) {
            Log::info('RenewKeyJob: ключ уже не просрочен, пропуск', ['key_id' => $this->keyActivateId, 'status' => $key->status]);
            return;
        }
        if (!$key->user_tg_id) {
            $this->writeAdminLog('Перевыпуск ключа — нельзя перевыпустить без user_tg_id', $key->id, null);
            return;
        }

        try {
            $keyActivateService->renew($key);
        } catch (\Throwable $e) {
            Log::error('RenewKeyJob failed', [
                'key_id' => $this->keyActivateId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            $this->writeAdminLog('Перевыпуск ключа — ОШИБКА: ' . $e->getMessage(), $key->id, $e);
            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->writeAdminLog('Перевыпуск ключа (джоба упала): ' . $e->getMessage(), $this->keyActivateId, $e);
    }

    private function writeAdminLog(string $message, ?string $keyId, ?\Throwable $e): void
    {
        try {
            $trace = $e ? $e->getTraceAsString() : '';
            if (strlen($trace) > 8000) {
                $trace = substr($trace, 0, 8000) . "\n...[обрезано]";
            }
            ApplicationLog::create([
                'level' => 'error',
                'source' => 'key_activate',
                'message' => $message,
                'context' => array_filter([
                    'action' => 'renew',
                    'key_id' => $keyId,
                    'job' => 'RenewKeyJob',
                    'error_class' => $e ? get_class($e) : null,
                    'file' => $e ? $e->getFile() : null,
                    'line' => $e ? $e->getLine() : null,
                    'trace' => $trace ?: null,
                ]),
                'user_id' => null,
                'ip_address' => null,
                'user_agent' => null,
            ]);
        } catch (\Throwable $logEx) {
            Log::error('RenewKeyJob: не удалось записать в application_logs: ' . $logEx->getMessage());
        }
    }
}
