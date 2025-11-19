<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use App\Logging\DatabaseLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ViolationManualService
{
    private ConnectionLimitMonitorService $limitMonitorService;
    private KeyActivateService $keyActivateService;
    private DatabaseLogger $logger;

    public function __construct(
        ConnectionLimitMonitorService $limitMonitorService,
        KeyActivateService $keyActivateService,
        DatabaseLogger $logger
    ) {
        $this->limitMonitorService = $limitMonitorService;
        $this->keyActivateService = $keyActivateService;
        $this->logger = $logger;
    }

    /**
     * Ручная проверка нарушений
     */
    public function manualViolationCheck(int $threshold = 2, int $windowMinutes = 60): array
    {
        $this->logger->info('Запущена ручная проверка нарушений', [
            'threshold' => $threshold,
            'window_minutes' => $windowMinutes
        ]);

        // Здесь можно запустить тот же мониторинг, но в ручном режиме
        $monitorService = app(ConnectionMonitorService::class);
        $results = $monitorService->monitorFixed($threshold, $windowMinutes);

        $this->logger->info('Ручная проверка нарушений завершена', [
            'violations_found' => $results['violations_found'],
            'servers_checked' => count($results['servers_checked'])
        ]);

        return $results;
    }

    /**
     * Массовое разрешение нарушений
     */
    public function bulkResolve(array $violationIds): int
    {
        $count = 0;

        foreach ($violationIds as $id) {
            $violation = ConnectionLimitViolation::find($id);
            if ($violation && $this->limitMonitorService->resolveViolation($violation)) {
                $count++;
            }
        }

        $this->logger->info('Массовое разрешение нарушений', [
            'resolved_count' => $count,
            'total_selected' => count($violationIds)
        ]);

        return $count;
    }

    /**
     * Массовое игнорирование нарушений
     */
    public function bulkIgnore(array $violationIds): int
    {
        $count = 0;

        foreach ($violationIds as $id) {
            $violation = ConnectionLimitViolation::find($id);
            if ($violation && $this->limitMonitorService->ignoreViolation($violation)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Массовая отправка уведомлений
     */
    public function bulkNotify(array $violationIds): int
    {
        $count = 0;

        foreach ($violationIds as $id) {
            $violation = ConnectionLimitViolation::find($id);
            if ($violation && $this->sendUserNotification($violation)) {
                $count++;
            }
        }

        return $count;
    }

//    /**
//     * Отправка уведомления пользователю
//     */
//    public function sendUserNotification(ConnectionLimitViolation $violation): bool
//    {
//        try {
//            // Используем метод из ConnectionLimitMonitorService
//            return $this->limitMonitorService->sendViolationNotification($violation);
//        } catch (\Exception $e) {
//            Log::error('Ошибка отправки уведомления', [
//                'violation_id' => $violation->id,
//                'error' => $e->getMessage()
//            ]);
//            return false;
//        }
//    }

    /**
     * Замена ключа пользователя
     */
    public function replaceUserKey(ConnectionLimitViolation $violation): ?KeyActivate
    {
        try {
            DB::beginTransaction();

            $oldKey = $violation->keyActivate;
            $userTgId = $oldKey->user_tg_id;

            if (!$userTgId) {
                throw new \Exception('Пользователь не найден для замены ключа');
            }

            // Создаем новый ключ
            $newKey = $this->keyActivateService->create(
                $oldKey->traffic_limit,
                $oldKey->pack_salesman_id,
                $oldKey->finish_at,
                null
            );

            // Активируем новый ключ
            $activatedKey = $this->keyActivateService->activate($newKey, $userTgId);

            if ($activatedKey) {
                // Деактивируем старый ключ
                $oldKey->status = KeyActivate::EXPIRED;
                $oldKey->save();

                // Помечаем нарушение как решенное
                $this->limitMonitorService->resolveViolation($violation);

                $this->logger->warning('Ключ заменен вручную', [
                    'old_key_id' => $oldKey->id,
                    'new_key_id' => $newKey->id,
                    'violation_id' => $violation->id,
                    'admin_action' => true
                ]);

                DB::commit();
                return $newKey;
            }

            DB::rollBack();
            return null;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка замены ключа', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Массовая замена ключей
     */
    public function bulkReplaceKeys(array $violationIds): int
    {
        $count = 0;

        foreach ($violationIds as $id) {
            $violation = ConnectionLimitViolation::find($id);
            if ($violation && $this->replaceUserKey($violation)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Сброс счетчика нарушений
     */
    public function resetViolationCounter(ConnectionLimitViolation $violation): bool
    {
        try {
            $violation->violation_count = 0;
            $violation->save();

            $this->logger->info('Сброс счетчика нарушений', [
                'violation_id' => $violation->id,
                'user_tg_id' => $violation->user_tg_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Ошибка сброса счетчика', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Удаление нарушений
     */
    public function bulkDelete(array $violationIds): int
    {
        $count = ConnectionLimitViolation::whereIn('id', $violationIds)->delete();

        $this->logger->warning('Удалены нарушения', [
            'deleted_count' => $count,
            'violation_ids' => $violationIds
        ]);

        return $count;
    }

    /**
     * Отправка уведомления пользователю
     */
    public function sendUserNotification(ConnectionLimitViolation $violation): bool
    {
        try {
            // Используем метод из ConnectionLimitMonitorService
            $result = $this->limitMonitorService->sendViolationNotification($violation);

            if ($result) {
                // Увеличиваем счетчик уведомлений
                $violation->incrementNotifications();

                $this->logger->info('Уведомление отправлено пользователю', [
                    'violation_id' => $violation->id,
                    'notifications_count' => $violation->getNotificationsSentCount(),
                    'user_tg_id' => $violation->user_tg_id
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Ошибка отправки уведомления', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Игнорирование нарушения
     */
    public function ignoreViolation(ConnectionLimitViolation $violation): bool
    {
        try {
            return $this->limitMonitorService->ignoreViolation($violation);
        } catch (\Exception $e) {
            Log::error('Ошибка игнорирования нарушения', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Перевыпуск ключа (замена)
     */
    public function reissueKey(ConnectionLimitViolation $violation): ?KeyActivate
    {
        try {
            DB::beginTransaction();

            $oldKey = $violation->keyActivate;
            $userTgId = $oldKey->user_tg_id;

            if (!$userTgId) {
                throw new \Exception('Пользователь не найден для перевыпуска ключа');
            }

            // Создаем новый ключ
            $newKey = $this->keyActivateService->create(
                $oldKey->traffic_limit,
                $oldKey->pack_salesman_id,
                $oldKey->finish_at,
                null
            );

            // Активируем новый ключ
            $activatedKey = $this->keyActivateService->activate($newKey, $userTgId);

            if ($activatedKey) {
                // Деактивируем старый ключ
                $oldKey->status = KeyActivate::EXPIRED;
                $oldKey->save();

                // Обновляем информацию о замене ключа в нарушении
                $violation->key_replaced_at = now();
                $violation->replaced_key_id = $newKey->id;
                $violation->violation_count = 0; // Сбрасываем счетчик нарушений
                $violation->status = ConnectionLimitViolation::STATUS_RESOLVED;
                $violation->resolved_at = now();
                $violation->save();

                $this->logger->warning('Ключ перевыпущен', [
                    'old_key_id' => $oldKey->id,
                    'new_key_id' => $newKey->id,
                    'violation_id' => $violation->id,
                    'user_tg_id' => $userTgId
                ]);

                // Отправляем уведомление о новом ключе
                $this->sendKeyReplacementNotification($violation, $newKey);

                DB::commit();
                return $newKey;
            }

            DB::rollBack();
            return null;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Ошибка перевыпуска ключа', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Отправка уведомления о замене ключа
     */
    private function sendKeyReplacementNotification(ConnectionLimitViolation $violation, KeyActivate $newKey): bool
    {
        try {
            $message = "🔄 <b>Ваш ключ был перевыпущен</b>\n\n";
            $message .= "В связи с нарушениями правил использования ваш ключ был заменен на новый.\n\n";
            $message .= "🔑 <b>Новый ключ:</b> <code>{$newKey->id}</code>\n";
            $message .= "🔗 <b>Конфигурация:</b> https://vpn-telegram.com/config/{$newKey->id}\n\n";
            $message .= "⚠️ Пожалуйста, используйте VPN согласно правилам.";

            return $this->limitMonitorService->sendViolationNotification($violation);
        } catch (\Exception $e) {
            Log::error('Ошибка отправки уведомления о замене ключа', [
                'violation_id' => $violation->id,
                'new_key_id' => $newKey->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
