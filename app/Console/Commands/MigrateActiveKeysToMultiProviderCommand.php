<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Миграция активных ключей на мульти-провайдер: добавление недостающих слотов
 * (по одному слоту на каждый провайдер из config panel.multi_provider_slots).
 *
 * ВАЖНО: Сначала обязательно запустить с --dry-run и при необходимости --limit=1
 * или --key-id=<uuid> для проверки на одном ключе.
 */
class MigrateActiveKeysToMultiProviderCommand extends Command
{
    protected $signature = 'keys:migrate-multi-provider
                            {--dry-run : Только показать, что будет сделано, без создания слотов}
                            {--limit= : Обработать не более N ключей (для теста)}
                            {--key-id= : Обработать только ключ с указанным ID (для теста)}';

    protected $description = 'Добавить недостающие провайдер-слоты ко всем активным ключам (мульти-провайдер)';

    public function handle(KeyActivateService $keyActivateService): int
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        $keyId = $this->option('key-id');

        $slots = config('panel.multi_provider_slots', []);
        $slots = is_array($slots) ? $slots : [];
        if (empty($slots)) {
            $this->warn('Мульти-провайдер отключён: panel.multi_provider_slots пуст. Задайте PANEL_MULTI_PROVIDER_SLOTS в .env (например vdsina,timeweb).');
            return 0;
        }

        $this->info('');
        $this->info('Миграция активных ключей на мульти-провайдер');
        $this->info('Провайдеры из конфига: ' . implode(', ', $slots));
        if ($dryRun) {
            $this->warn('Режим --dry-run: слоты не создаются.');
        }
        $this->newLine();

        $query = KeyActivate::query()
            ->where('status', KeyActivate::ACTIVE)
            ->whereNotNull('user_tg_id');

        if ($keyId !== null && $keyId !== '') {
            $query->where('id', $keyId);
            $total = $query->count();
            if ($total === 0) {
                $this->error("Ключ с ID \"{$keyId}\" не найден или не подходит (должен быть ACTIVE и с user_tg_id).");
                return 1;
            }
        } else {
            $total = $query->count();
        }

        if ($limit !== null && $limit !== '') {
            $limit = (int) $limit;
            $query->limit(max(1, $limit));
        }

        $keys = $query->get();
        $this->info('Ключей к обработке: ' . $keys->count() . ($total > $keys->count() ? " (всего подходящих: {$total})" : ''));

        $addedTotal = 0;
        $processed = 0;
        $errors = 0;

        foreach ($keys as $key) {
            $processed++;
            try {
                $added = $keyActivateService->addMissingProviderSlots($key, $dryRun);
                $addedTotal += $added;
                if ($this->output->isVerbose() && $added > 0) {
                    $this->line("  [{$processed}] Key {$key->id}: добавлено слотов: {$added}");
                }
            } catch (\Throwable $e) {
                $errors++;
                Log::error('keys:migrate-multi-provider error', [
                    'key_id' => $key->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->error("  [{$processed}] Key {$key->id}: " . $e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Обработано ключей: {$processed}. Добавлено слотов: {$addedTotal}. Ошибок: {$errors}.");
        if ($dryRun && $addedTotal > 0) {
            $this->warn("При запуске без --dry-run было бы создано слотов: {$addedTotal}.");
        }

        return $errors > 0 ? 1 : 0;
    }
}
