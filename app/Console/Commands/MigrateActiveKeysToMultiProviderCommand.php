<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use App\Services\Key\MultiProviderMigrationService;
use App\Services\Notification\TelegramLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Миграция активных ключей на мульти-провайдер: добавление недостающих слотов
 * (по одному слоту на каждый провайдер из config panel.multi_provider_slots).
 *
 * Работает порциями (offset/limit). Статус отправляется в Telegram (основной + резервный бот).
 * Рекомендуется запускать по cron или вручную. Сначала — с --dry-run.
 */
class MigrateActiveKeysToMultiProviderCommand extends Command
{
    protected $signature = 'keys:migrate-multi-provider
                            {--dry-run : Только показать, что будет сделано, без создания слотов}
                            {--batch=50 : Размер порции ключей за один проход}
                            {--limit= : Обработать не более N ключей (0 = без лимита)}
                            {--key-id= : Обработать только ключ с указанным ID (для теста)}';

    protected $description = 'Добавить недостающие провайдер-слоты ко всем активным ключам (мульти-провайдер). Логи в Telegram.';

    public function handle(
        MultiProviderMigrationService $migrationService,
        KeyActivateService $keyActivateService,
        TelegramLogService $telegramLog
    ): int {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch') ?: 50;
        $limit = $this->option('limit') !== null && $this->option('limit') !== '' ? (int) $this->option('limit') : 0;
        $keyId = $this->option('key-id');

        if (! filter_var(config('panel.multi_provider_enabled', false), FILTER_VALIDATE_BOOLEAN)) {
            $msg = 'Мульти-провайдер отключён: задайте PANEL_MULTI_PROVIDER_SLOTS (или * при v2+greedy) в .env.';
            $this->warn($msg);
            $telegramLog->send("[Миграция] " . $msg);
            return 1;
        }

        if ($keyId !== null && $keyId !== '') {
            return $this->runSingleKey($keyId, $dryRun, $keyActivateService, $telegramLog);
        }

        $totalCount = $migrationService->getTotalCount();
        if ($totalCount === 0) {
            $this->info('Нет ключей-кандидатов для миграции.');
            $telegramLog->send("[Миграция] Нет ключей-кандидатов для миграции.");
            return 0;
        }

        $maxTotal = $limit > 0 ? min($limit, $totalCount) : $totalCount;
        $capForService = $limit > 0 ? $maxTotal : null; // в runOneBatch передаём лимит только если --limit задан
        $this->info('');
        $this->info('Миграция на мульти-провайдер (порциями по ' . $batchSize . ')');
        $this->info('Провайдеры: ' . implode(', ', $slots));
        $this->info('Ключей к обработке: ' . $maxTotal . ($dryRun ? ' (dry-run)' : ''));
        $this->newLine();

        $telegramLog->send(
            "[Миграция] Старт. Ключей: {$maxTotal}, порция: {$batchSize}" . ($dryRun ? ', dry-run' : '') . "."
        );

        $offset = 0;
        $totalProcessed = 0;
        $totalAdded = 0;
        $allErrors = [];
        $lastTelegramAt = time();
        $telegramInterval = 300; // раз в 5 минут

        while (true) {
            if ($capForService !== null && $offset >= $capForService) {
                break;
            }

            $result = $migrationService->runOneBatch($offset, $batchSize, $dryRun, $capForService);

            if (!($result['success'] ?? true)) {
                $err = $result['message'] ?? 'Unknown error';
                $this->error($err);
                $telegramLog->send("[Миграция] Ошибка: " . $err);
                return 1;
            }

            $processed = (int) ($result['processed'] ?? 0);
            $totalProcessed += $processed;
            $totalAdded += (int) ($result['added_total'] ?? 0);
            $allErrors = array_merge($allErrors, $result['errors'] ?? []);

            if ($processed > 0 && $this->output->isVerbose()) {
                $this->line("  Обработано: {$totalProcessed}/{$maxTotal}, добавлено слотов: {$totalAdded}");
            }

            if (!empty($result['errors'])) {
                foreach ($result['errors'] as $err) {
                    $this->error("  Ключ " . ($err['key_id'] ?? '') . ": " . ($err['message'] ?? ''));
                }
            }

            if ($result['done'] ?? false) {
                break;
            }

            $offset = (int) ($result['next_offset'] ?? $offset + $processed);
            if ($capForService !== null && $offset >= $capForService) {
                break;
            }

            if (time() - $lastTelegramAt >= $telegramInterval) {
                $telegramLog->send(
                    "[Миграция] Прогресс: обработано {$totalProcessed}/{$maxTotal}, добавлено слотов: {$totalAdded}."
                );
                $lastTelegramAt = time();
            }
        }

        $summary = "Обработано ключей: {$totalProcessed}. Добавлено слотов: {$totalAdded}.";
        if (count($allErrors) > 0) {
            $summary .= " Ошибок: " . count($allErrors);
        }
        $this->newLine();
        $this->info($summary);
        if ($dryRun && $totalAdded > 0) {
            $this->warn("При запуске без --dry-run было бы создано слотов: {$totalAdded}.");
        }

        $telegramLog->send("[Миграция] Завершено. " . $summary);

        return count($allErrors) > 0 ? 1 : 0;
    }

    private function runSingleKey(
        string $keyId,
        bool $dryRun,
        KeyActivateService $keyActivateService,
        TelegramLogService $telegramLog
    ): int {
        $key = KeyActivate::query()
            ->where('status', KeyActivate::ACTIVE)
            ->whereNotNull('user_tg_id')
            ->whereHas('keyActivateUsers')
            ->where('id', $keyId)
            ->first();

        if (!$key) {
            $this->error("Ключ с ID \"{$keyId}\" не найден или не подходит.");
            $telegramLog->send("[Миграция] Ключ {$keyId} не найден.");
            return 1;
        }

        $this->info("Обработка одного ключа: {$keyId}");
        try {
            $added = $keyActivateService->addMissingProviderSlots($key, $dryRun);
            $this->info("Добавлено слотов: {$added}" . ($dryRun ? ' (dry-run)' : ''));
            $telegramLog->send("[Миграция] Ключ {$keyId}: добавлено слотов {$added}" . ($dryRun ? ' (dry-run)' : '') . ".");
            return 0;
        } catch (\Throwable $e) {
            Log::error('keys:migrate-multi-provider single key', ['key_id' => $keyId, 'error' => $e->getMessage()]);
            $this->error($e->getMessage());
            $telegramLog->send("[Миграция] Ошибка ключ {$keyId}: " . $e->getMessage());
            return 1;
        }
    }
}
