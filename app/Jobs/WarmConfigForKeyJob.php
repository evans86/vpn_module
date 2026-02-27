<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Прогрев конфигурации ключа: запрос к панелям и сохранение ссылок в БД.
 * Вызывается после активации ключа, чтобы страница и приложение сразу получали конфиг из БД.
 */
class WarmConfigForKeyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 180;

    protected string $keyActivateId;

    public function __construct(string $keyActivateId)
    {
        $this->keyActivateId = $keyActivateId;
    }

    public function handle(): void
    {
        $url = rtrim(config('app.url'), '/') . route('vpn.config.refresh', ['token' => $this->keyActivateId], false);
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);
            if (!$response->successful()) {
                Log::warning('WarmConfigForKeyJob: refresh returned non-OK', [
                    'key_activate_id' => $this->keyActivateId,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('WarmConfigForKeyJob failed', [
                'key_activate_id' => $this->keyActivateId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
