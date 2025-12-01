<?php

namespace App\Console\Commands;

use App\Models\VPN\ConnectionLimitViolation;
use App\Services\VPN\ConnectionLimitMonitorService;
use App\Services\VPN\ViolationManualService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessViolationsCommand extends Command
{
    protected $signature = 'violations:process
                            {--auto-resolve-hours=72 : Автоматически решать нарушения старше N часов}
                            {--auto-reissue-threshold=3 : Автоматически перевыпускать ключи при N+ нарушениях}
                            {--notify-new : Отправлять уведомления для новых нарушений (по умолчанию false)}';

    protected $description = 'Автоматическая обработка нарушений лимитов подключений';

    private ConnectionLimitMonitorService $monitorService;
    private ViolationManualService $manualService;

    public function __construct(
        ConnectionLimitMonitorService $monitorService,
        ViolationManualService $manualService
    ) {
        parent::__construct();
        $this->monitorService = $monitorService;
        $this->manualService = $manualService;
    }

    public function handle(): int
    {
        $this->info('🚀 Запуск автоматической обработки нарушений...');

        $autoResolveHours = (int) $this->option('auto-resolve-hours');
        $autoReissueThreshold = (int) $this->option('auto-reissue-threshold');
        $notifyNew = $this->option('notify-new'); // true только если явно указан флаг

        $stats = [
            'notifications_sent' => 0,
            'keys_reissued' => 0,
            'auto_resolved' => 0,
            'errors' => 0
        ];

        try {
            // 1. Отправка уведомлений для новых нарушений
            if ($notifyNew) {
                $this->info('📧 Отправка уведомлений для новых нарушений...');
                $stats['notifications_sent'] = $this->processNewViolations();
            }

            // 2. Автоматический перевыпуск ключей при критических нарушениях
            $this->info("🔑 Проверка критических нарушений (≥{$autoReissueThreshold})...");
            $stats['keys_reissued'] = $this->processCriticalViolations($autoReissueThreshold);

            // 3. Автоматическое решение старых нарушений
            $this->info("⏰ Автоматическое решение нарушений старше {$autoResolveHours} часов...");
            $stats['auto_resolved'] = $this->autoResolveOldViolations($autoResolveHours);

            // Вывод статистики
            $this->info("\n✅ Обработка завершена:");
            $this->line("   📧 Уведомлений отправлено: {$stats['notifications_sent']}");
            $this->line("   🔑 Ключей перевыпущено: {$stats['keys_reissued']}");
            $this->line("   ✅ Автоматически решено: {$stats['auto_resolved']}");
            $this->line("   ❌ Ошибок: {$stats['errors']}");

            return 0;

        } catch (\Exception $e) {
            Log::error('Ошибка при автоматической обработке нарушений', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("❌ Ошибка: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Обработка новых нарушений - отправка уведомлений
     */
    private function processNewViolations(): int
    {
        $count = 0;

        // Находим активные нарушения без уведомлений или с последним уведомлением старше 24 часов
        $violations = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_ACTIVE)
            ->where(function ($query) {
                $query->whereNull('last_notification_sent_at')
                    ->orWhere('last_notification_sent_at', '<', now()->subHours(24));
            })
            ->where('created_at', '>=', now()->subDays(7)) // Только за последнюю неделю
            ->with('keyActivate')
            ->get();

        foreach ($violations as $violation) {
            try {
                // Проверяем что ключ еще существует и активен
                if (!$violation->keyActivate || $violation->keyActivate->status !== \App\Models\KeyActivate\KeyActivate::ACTIVE) {
                    continue;
                }

                // Отправляем уведомление
                if ($this->manualService->sendUserNotification($violation)) {
                    $count++;
                    $this->line("   ✓ Уведомление отправлено для нарушения #{$violation->id}");
                }

            } catch (\Exception $e) {
                Log::error('Ошибка отправки уведомления при автоматической обработке', [
                    'violation_id' => $violation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }

    /**
     * Обработка критических нарушений - автоматический перевыпуск ключей
     */
    private function processCriticalViolations(int $threshold): int
    {
        $count = 0;

        // Находим активные нарушения с количеством >= threshold, которые еще не были перевыпущены
        $violations = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_ACTIVE)
            ->where('violation_count', '>=', $threshold)
            ->whereNull('key_replaced_at') // Ключ еще не был заменен
            ->where('created_at', '>=', now()->subDays(30)) // Только за последний месяц
            ->with('keyActivate')
            ->get();

        foreach ($violations as $violation) {
            try {
                // Проверяем что ключ еще существует и активен
                if (!$violation->keyActivate || $violation->keyActivate->status !== \App\Models\KeyActivate\KeyActivate::ACTIVE) {
                    // Помечаем нарушение как решенное если ключ уже неактивен
                    $this->monitorService->resolveViolation($violation);
                    continue;
                }

                // Перевыпускаем ключ
                $newKey = $this->manualService->reissueKey($violation);
                if ($newKey) {
                    $count++;
                    $this->line("   ✓ Ключ перевыпущен для нарушения #{$violation->id} (новый ключ: {$newKey->id})");
                }

            } catch (\Exception $e) {
                Log::error('Ошибка перевыпуска ключа при автоматической обработке', [
                    'violation_id' => $violation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }

    /**
     * Автоматическое решение старых нарушений
     */
    private function autoResolveOldViolations(int $hours): int
    {
        $count = 0;

        // Находим активные нарушения старше указанного времени
        $violations = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_ACTIVE)
            ->where('created_at', '<', now()->subHours($hours))
            ->get();

        foreach ($violations as $violation) {
            try {
                // Помечаем как решенное
                if ($this->monitorService->resolveViolation($violation)) {
                    $count++;
                }
            } catch (\Exception $e) {
                Log::error('Ошибка автоматического решения нарушения', [
                    'violation_id' => $violation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $count;
    }
}

