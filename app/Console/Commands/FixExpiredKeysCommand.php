<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Models\VPN\ConnectionLimitViolation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Команда для исправления неправильно просроченных ключей
 * 
 * Исправляет ключи со статусом EXPIRED, у которых срок действия еще не истек,
 * но исключает ключи, которые были заменены из-за нарушений лимитов подключений.
 */
class FixExpiredKeysCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'keys:fix-expired 
                            {--dry-run : Показать что будет изменено без применения изменений}
                            {--force : Выполнить без подтверждения}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Исправление неправильно просроченных ключей (исключая замененные из-за нарушений)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');

        $this->info('');
        $this->info('╔════════════════════════════════════════════════════════════════╗');
        $this->info('║  ИСПРАВЛЕНИЕ НЕПРАВИЛЬНО ПРОСРОЧЕННЫХ КЛЮЧЕЙ                   ║');
        $this->info('╚════════════════════════════════════════════════════════════════╝');
        $this->info('');

        if ($isDryRun) {
            $this->warn('⚠️  РЕЖИМ ПРОВЕРКИ (DRY RUN) - изменения НЕ будут применены');
            $this->info('');
        }

        try {
            // ШАГ 1: Анализ проблемы
            $this->info('📊 Шаг 1: Анализ проблемы...');
            $this->newLine();
            
            $analysis = $this->analyzeKeys();
            $this->displayAnalysis($analysis);

            if ($analysis['wrong_expired'] === 0) {
                $this->info('✅ Неправильно просроченных ключей не найдено!');
                return 0;
            }

            // ШАГ 2: Детальная информация
            if ($this->option('verbose')) {
                $this->info('');
                $this->info('📋 Детальная информация:');
                $this->displayDetailedInfo($analysis);
            }

            // ШАГ 3: Подтверждение
            if (!$isDryRun && !$isForce) {
                $this->newLine();
                if (!$this->confirm('Вы уверены что хотите исправить эти ключи?', false)) {
                    $this->warn('❌ Операция отменена пользователем');
                    return 1;
                }
            }

            // ШАГ 4: Исправление
            if (!$isDryRun) {
                $this->newLine();
                $this->info('🔧 Шаг 2: Исправление ключей...');
                $updated = $this->fixKeys($analysis['keys_to_fix']);
                
                $this->newLine();
                $this->info("✅ Успешно исправлено ключей: {$updated}");
                
                // Финальная проверка
                $this->newLine();
                $this->info('🔍 Финальная проверка...');
                $remaining = $this->countWrongExpiredKeys();
                
                if ($remaining === 0) {
                    $this->info('✅ Все неправильно просроченные ключи исправлены!');
                } else {
                    $this->warn("⚠️  Остались неисправленные ключи: {$remaining}");
                }
            } else {
                $this->newLine();
                $this->info('ℹ️  Для применения изменений запустите команду без --dry-run');
            }

            return 0;

        } catch (\Exception $e) {
            $this->error('❌ Ошибка при выполнении команды: ' . $e->getMessage());
            Log::error('Error in FixExpiredKeysCommand', [
                'source' => 'command',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }

    /**
     * Анализ ключей
     */
    private function analyzeKeys(): array
    {
        $currentTime = time();

        // Получаем ключи которые были заменены из-за нарушений
        $replacedKeyIds = ConnectionLimitViolation::whereNotNull('key_replaced_at')
            ->whereNotNull('replaced_key_id')
            ->pluck('key_activate_id')
            ->unique()
            ->toArray();

        // Все EXPIRED ключи с не истекшим сроком
        // Используем user_tg_id для проверки активации (activated_at в БД нет)
        // Рассматриваем ТОЛЬКО ключи с привязкой к панели (whereHas keyActivateUser)
        $allWrongExpired = KeyActivate::where('status', KeyActivate::EXPIRED)
            ->whereNotNull('finish_at')
            ->where('finish_at', '>', $currentTime)
            ->whereNotNull('user_tg_id')
            ->whereHas('keyActivateUser') // ВАЖНО: только с привязкой к панели
            ->count();

        // Подсчет ключей с исчерпанным трафиком требует проверки через API панели
        // Это будет сделано при детальной проверке каждого ключа
        $expiredDueToTraffic = 0;

        // Ключи замененные из-за нарушений (их НЕ трогаем)
        $replacedDueToViolations = KeyActivate::where('status', KeyActivate::EXPIRED)
            ->whereNotNull('finish_at')
            ->where('finish_at', '>', $currentTime)
            ->whereNotNull('user_tg_id')
            ->whereHas('keyActivateUser')
            ->whereIn('id', $replacedKeyIds)
            ->count();

        // Ключи которые потенциально нужно исправить (исключая замененные и без привязки к панели)
        $potentialKeysToFix = KeyActivate::where('status', KeyActivate::EXPIRED)
            ->whereNotNull('finish_at')
            ->where('finish_at', '>', $currentTime)
            ->whereNotNull('user_tg_id')
            ->whereHas('keyActivateUser') // ВАЖНО: должна быть связь с панелью
            ->whereNotIn('id', $replacedKeyIds)
            ->get();

        // Проверяем каждый ключ через API панели на наличие трафика
        if ($potentialKeysToFix->count() > 0) {
            Log::info("Проверка остатка трафика для {$potentialKeysToFix->count()} ключей через API Marzban", [
                'source' => 'fix_expired_keys_command'
            ]);
        }

        $keysToFix = collect();
        foreach ($potentialKeysToFix as $key) {
            try {
                if ($key->keyActivateUser && $key->keyActivateUser->serverUser) {
                    $serverUser = $key->keyActivateUser->serverUser;
                    if ($serverUser->panel) {
                        $panelStrategy = new \App\Services\Panel\PanelStrategy($serverUser->panel->panel);
                        $subscribeInfo = $panelStrategy->getSubscribeInfo(
                            $serverUser->panel->id,
                            $serverUser->id
                        );

                        // Проверяем остаток трафика
                        $dataLimit = $subscribeInfo['data_limit'] ?? 0;
                        $usedTraffic = $subscribeInfo['used_traffic'] ?? 0;
                        $remainingTraffic = $dataLimit - $usedTraffic;

                        // Если трафик исчерпан - не исправляем
                        if ($remainingTraffic <= 0) {
                            $expiredDueToTraffic++;
                            continue;
                        }
                    }
                }
                $keysToFix->push($key);
            } catch (\Exception $e) {
                // Если не удалось проверить - добавляем в список (безопасный подход)
                $keysToFix->push($key);
            }
        }

        // Статистика по категориям
        $categories = $keysToFix->groupBy(function ($key) use ($currentTime) {
            $daysRemaining = ($key->finish_at - $currentTime) / 86400;
            if ($daysRemaining > 30) return 'Более 30 дней';
            if ($daysRemaining > 7) return '7-30 дней';
            if ($daysRemaining > 1) return '1-7 дней';
            return 'Менее 1 дня';
        })->map->count();

        // Затронутые пользователи и продавцы
        $affectedUsers = $keysToFix->pluck('user_tg_id')->unique()->count();
        $affectedPackSalesmen = $keysToFix->whereNotNull('pack_salesman_id')->pluck('pack_salesman_id')->unique()->count();
        $affectedModuleSalesmen = $keysToFix->whereNotNull('module_salesman_id')->pluck('module_salesman_id')->unique()->count();

        return [
            'all_wrong_expired' => $allWrongExpired,
            'expired_due_to_traffic' => $expiredDueToTraffic,
            'replaced_due_to_violations' => $replacedDueToViolations,
            'wrong_expired' => $keysToFix->count(),
            'keys_to_fix' => $keysToFix,
            'categories' => $categories,
            'affected_users' => $affectedUsers,
            'affected_pack_salesmen' => $affectedPackSalesmen,
            'affected_module_salesmen' => $affectedModuleSalesmen,
        ];
    }

    /**
     * Отображение анализа
     */
    private function displayAnalysis(array $analysis): void
    {
        $this->table(
            ['Метрика', 'Значение'],
            [
                ['Всего EXPIRED с не истекшим сроком', $analysis['all_wrong_expired']],
                ['  ├─ Исчерпан трафик (не трогаем)', $analysis['expired_due_to_traffic']],
                ['  ├─ Заменены из-за нарушений (не трогаем)', $analysis['replaced_due_to_violations']],
                ['  └─ Нужно исправить', $analysis['wrong_expired']],
                ['', ''],
                ['Затронуто пользователей', $analysis['affected_users']],
                ['Затронуто продавцов (бот)', $analysis['affected_pack_salesmen']],
                ['Затронуто продавцов (модуль)', $analysis['affected_module_salesmen']],
            ]
        );

        if ($analysis['wrong_expired'] > 0) {
            $this->newLine();
            $this->info('Распределение по срокам:');
            $this->table(
                ['Категория', 'Количество'],
                collect($analysis['categories'])->map(fn($count, $category) => [$category, $count])->toArray()
            );
        }
    }

    /**
     * Отображение детальной информации
     */
    private function displayDetailedInfo(array $analysis): void
    {
        $keys = $analysis['keys_to_fix']->take(20);
        
        $this->table(
            ['ID ключа', 'Пользователь', 'Дата окончания', 'Дней осталось'],
            $keys->map(function ($key) {
                $daysRemaining = ceil(($key->finish_at - time()) / 86400);
                return [
                    substr($key->id, 0, 13) . '...',
                    $key->user_tg_id ?? 'N/A',
                    date('d.m.Y H:i', $key->finish_at),
                    $daysRemaining . ' дн.',
                ];
            })->toArray()
        );

        if ($analysis['keys_to_fix']->count() > 20) {
            $this->info("... и еще " . ($analysis['keys_to_fix']->count() - 20) . " ключей");
        }
    }

    /**
     * Исправление ключей
     */
    private function fixKeys($keys): int
    {
        $updated = 0;
        $bar = $this->output->createProgressBar($keys->count());
        $bar->start();

        DB::beginTransaction();

        try {
            foreach ($keys as $key) {
                $oldStatus = $key->status;
                $key->status = KeyActivate::ACTIVE;
                $key->save();

                Log::info('Key status fixed from EXPIRED to ACTIVE', [
                    'source' => 'fix_expired_keys_command',
                    'key_id' => $key->id,
                    'old_status' => $oldStatus,
                    'new_status' => $key->status,
                    'finish_at' => $key->finish_at,
                    'days_remaining' => ceil(($key->finish_at - time()) / 86400)
                ]);

                $updated++;
                $bar->advance();
            }

            DB::commit();
            $bar->finish();
            $this->newLine();

        } catch (\Exception $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            throw $e;
        }

        return $updated;
    }

    /**
     * Подсчет оставшихся неправильно просроченных ключей
     */
    private function countWrongExpiredKeys(): int
    {
        $currentTime = time();
        
        $replacedKeyIds = ConnectionLimitViolation::whereNotNull('key_replaced_at')
            ->whereNotNull('replaced_key_id')
            ->pluck('key_activate_id')
            ->unique()
            ->toArray();

        // Простой подсчет без проверки трафика (для быстрой финальной проверки)
        return KeyActivate::where('status', KeyActivate::EXPIRED)
            ->whereNotNull('finish_at')
            ->where('finish_at', '>', $currentTime)
            ->whereNotNull('user_tg_id')
            ->whereHas('keyActivateUser') // должна быть связь с панелью
            ->whereNotIn('id', $replacedKeyIds)
            ->count();
    }
}

