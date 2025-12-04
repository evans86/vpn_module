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
                            {--auto-reissue-threshold=3 : Автоматически перевыпускать ключи при N+ нарушениях}';

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

        // Логируем начало обработки
        Log::info('🚀 Запуск автоматической обработки нарушений', [
            'auto_resolve_hours' => $autoResolveHours,
            'auto_reissue_threshold' => $autoReissueThreshold,
            'started_at' => now()->format('Y-m-d H:i:s')
        ]);

        $stats = [
            'notifications_sent' => 0,
            'keys_reissued' => 0,
            'auto_resolved' => 0,
            'errors' => 0
        ];

        $startTime = microtime(true);

        try {
            // 1. Обработка нарушений: отправка уведомлений и перевыпуск ключей
            $this->info('📧 Обработка нарушений (уведомления и перевыпуск ключей)...');
            $result = $this->processViolations();
            $stats['notifications_sent'] = $result['notifications_sent'];
            $stats['keys_reissued'] = $result['keys_reissued'];

            // 2. Автоматическое решение старых нарушений
            $this->info("⏰ Автоматическое решение нарушений старше {$autoResolveHours} часов...");
            $stats['auto_resolved'] = $this->autoResolveOldViolations($autoResolveHours);

            $executionTime = round(microtime(true) - $startTime, 2);

            // Вывод статистики
            $this->info("\n✅ Обработка завершена:");
            $this->line("   📧 Уведомлений отправлено: {$stats['notifications_sent']}");
            $this->line("   🔑 Ключей перевыпущено: {$stats['keys_reissued']}");
            $this->line("   ✅ Автоматически решено: {$stats['auto_resolved']}");
            $this->line("   ❌ Ошибок: {$stats['errors']}");

            // Логируем успешное завершение обработки
            Log::info('✅ Автоматическая обработка нарушений завершена', [
                'notifications_sent' => $stats['notifications_sent'],
                'keys_reissued' => $stats['keys_reissued'],
                'auto_resolved' => $stats['auto_resolved'],
                'errors' => $stats['errors'],
                'execution_time_seconds' => $executionTime,
                'completed_at' => now()->format('Y-m-d H:i:s')
            ]);

            return 0;

        } catch (\Exception $e) {
            $executionTime = round(microtime(true) - $startTime, 2);
            
            Log::error('❌ Ошибка при автоматической обработке нарушений', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'execution_time_seconds' => $executionTime,
                'failed_at' => now()->format('Y-m-d H:i:s')
            ]);

            $this->error("❌ Ошибка: {$e->getMessage()}");
            return 1;
        }
    }

    /**
     * Обработка нарушений: отправка уведомлений и перевыпуск ключей
     * Логика: при каждом нарушении (1, 2, 3) отправляем уведомление, при 3-м - перевыпускаем ключ
     */
    private function processViolations(): array
    {
        $notificationsSent = 0;
        $keysReissued = 0;

        // Находим активные нарушения, которые требуют обработки
        // 1. Старые нарушения, где уведомление еще не отправлено (созданные до внедрения автоматической отправки)
        // 2. Нарушения с техническими ошибками для повторной попытки (не более 3 попыток)
        $violations = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_ACTIVE)
            ->whereNull('key_replaced_at') // Ключ еще не был заменен
            ->where('created_at', '>=', now()->subDays(7)) // Только за последнюю неделю
            ->where(function($query) {
                // Либо уведомление еще не отправлено для текущего количества нарушений (старые нарушения)
                $query->whereRaw('notifications_sent < violation_count')
                    // Либо есть техническая ошибка и попыток меньше 3, и прошло 30 минут с последней попытки
                    ->orWhere(function($q) {
                        $q->where('last_notification_status', 'technical_error')
                          ->where('notification_retry_count', '<', 3)
                          ->where(function($subQ) {
                              // Если last_notification_sent_at есть, проверяем что прошло 30 минут
                              // Если нет, значит это первая попытка после технической ошибки
                              $subQ->whereNull('last_notification_sent_at')
                                   ->orWhere('last_notification_sent_at', '<=', now()->subMinutes(30));
                          });
                    });
            })
            ->with('keyActivate')
            ->get();

        foreach ($violations as $violation) {
            try {
                // Проверяем что ключ еще существует и активен
                if (!$violation->keyActivate || $violation->keyActivate->status !== \App\Models\KeyActivate\KeyActivate::ACTIVE) {
                    continue;
                }

                $violationCount = $violation->violation_count;
                $notificationsCount = $violation->getNotificationsSentCount();
                $isTechnicalError = $violation->last_notification_status === 'technical_error';
                $retryCount = $violation->notification_retry_count ?? 0;

                // Обрабатываем только если:
                // 1. Уведомление еще не отправлено для текущего количества нарушений (старые нарушения)
                // 2. ИЛИ есть техническая ошибка и попыток меньше 3
                if ($notificationsCount < $violationCount || ($isTechnicalError && $retryCount < 3)) {
                    // Отправляем только ОДНО уведомление за раз, чтобы не спамить
                    // Если уведомлений не хватает, отправляем только следующее недостающее
                    $result = $this->manualService->sendUserNotification($violation);
                    if ($result) {
                        $notificationsSent++;
                        $status = $violation->fresh()->last_notification_status ?? 'unknown';
                        $statusText = $status === 'blocked' ? ' (пользователь заблокировал бота)' : '';
                        $this->line("   ✓ Уведомление засчитано для нарушения #{$violation->id} (нарушение #{$violationCount}){$statusText}");
                    } else {
                        // Техническая ошибка - логируем для повторной попытки
                        $newRetryCount = $violation->fresh()->notification_retry_count ?? 0;
                        if ($newRetryCount < 3) {
                            $this->line("   ⚠ Техническая ошибка отправки для нарушения #{$violation->id} (попытка {$newRetryCount}/3)");
                        } else {
                            $this->line("   ❌ Не удалось отправить уведомление для нарушения #{$violation->id} после 3 попыток");
                        }
                    }
                }

                // При 3-м нарушении перевыпускаем ключ (независимо от статуса отправки уведомления)
                if ($violationCount >= 3 && is_null($violation->key_replaced_at)) {
                    $newKey = $this->manualService->reissueKey($violation);
                    if ($newKey) {
                        $keysReissued++;
                        $this->line("   ✓ Ключ перевыпущен для нарушения #{$violation->id} (новый ключ: {$newKey->id})");
                    }
                }

            } catch (\Exception $e) {
                Log::error('Ошибка обработки нарушения', [
                    'violation_id' => $violation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return [
            'notifications_sent' => $notificationsSent,
            'keys_reissued' => $keysReissued
        ];
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

