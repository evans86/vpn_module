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
    public function manualViolationCheck(int $threshold = 3, int $windowMinutes = 15): array
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
    /**
     * Замена ключа пользователя при нарушении лимита подключений
     *
     * @param ConnectionLimitViolation $violation Нарушение лимита подключений
     * @return KeyActivate|null Новый ключ или null если не удалось создать
     * @throws \Exception При ошибках создания или активации ключа
     */
    public function replaceUserKey(ConnectionLimitViolation $violation): ?KeyActivate
    {
        try {
            // Используем DB::transaction() для автоматического rollback при ошибках
            return DB::transaction(function () use ($violation) {
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

                if (!$activatedKey) {
                    throw new \Exception('Не удалось активировать новый ключ');
                }

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

                return $newKey;
            });
        } catch (\Exception $e) {
            Log::error('Ошибка замены ключа', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'vpn'
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
                'error' => $e->getMessage(),
                'source' => 'vpn'
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
     * Отправляет только ОДНО уведомление - для следующего недостающего номера нарушения
     */
    public function sendUserNotification(ConnectionLimitViolation $violation): bool
    {
        try {
            // Определяем, какое уведомление нужно отправить (следующее недостающее)
            $notificationsSent = $violation->getNotificationsSentCount();
            $nextNotificationNumber = $notificationsSent + 1;
            
            // Если уже отправлены все уведомления для текущего количества нарушений - ничего не делаем
            if ($nextNotificationNumber > $violation->violation_count) {
                return false;
            }
            
            // Используем новый метод с детальным результатом, передавая номер уведомления
            $result = $this->limitMonitorService->sendViolationNotificationWithResult($violation, $nextNotificationNumber);

            // Если уведомление должно считаться отправленным (успешно или заблокирован)
            if ($result->shouldCountAsSent) {
                // Увеличиваем счетчик уведомлений
                $violation->incrementNotifications();

                // Сохраняем информацию о статусе отправки
                $violation->last_notification_status = $result->status;
                $violation->last_notification_error = $result->errorMessage;
                $violation->save();

                $this->logger->info('Уведомление засчитано как отправленное', [
                    'violation_id' => $violation->id,
                    'status' => $result->status,
                    'notifications_count' => $violation->getNotificationsSentCount(),
                    'user_tg_id' => $violation->user_tg_id,
                    'is_blocked' => $result->isBlocked()
                ]);

                return true;
            } else {
                // Техническая ошибка - сохраняем для повторной попытки
                $violation->last_notification_status = $result->status;
                $violation->last_notification_error = $result->errorMessage;
                $violation->notification_retry_count = ($violation->notification_retry_count ?? 0) + 1;
                $violation->save();

                $this->logger->warning('Уведомление не доставлено (техническая ошибка)', [
                    'violation_id' => $violation->id,
                    'status' => $result->status,
                    'error' => $result->errorMessage,
                    'retry_count' => $violation->notification_retry_count,
                    'user_tg_id' => $violation->user_tg_id
                ]);

                return false;
            }

        } catch (\Exception $e) {
            Log::error('Ошибка отправки уведомления', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage(),
                'source' => 'vpn'
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
                'error' => $e->getMessage(),
                'source' => 'vpn'
            ]);
            return false;
        }
    }

    /**
     * Перевыпуск ключа (замена) при нарушении лимита подключений
     * Учитывает оставшееся время и трафик от старого ключа
     *
     * @param ConnectionLimitViolation $violation Нарушение лимита подключений
     * @return KeyActivate|null Новый ключ или null если не удалось создать
     * @throws \Exception При ошибках создания или активации ключа
     */
    public function reissueKey(ConnectionLimitViolation $violation): ?KeyActivate
    {
        try {
            // Используем DB::transaction() для автоматического rollback при ошибках
            return DB::transaction(function () use ($violation) {
                $oldKey = $violation->keyActivate;
                $userTgId = $oldKey->user_tg_id;

                if (!$userTgId) {
                    throw new \Exception('Пользователь не найден для перевыпуска ключа');
                }

                // Вычисляем оставшееся время от старого ключа
                $currentTime = time();
                $remainingTime = 0;
                $remainingTraffic = $oldKey->traffic_limit;

                if ($oldKey->finish_at && $oldKey->finish_at > $currentTime) {
                    // Оставшееся время в секундах
                    $remainingTime = $oldKey->finish_at - $currentTime;
                }

                // Пытаемся получить информацию об использованном трафике с панели
                try {
                    if ($oldKey->keyActivateUser && $oldKey->keyActivateUser->serverUser) {
                        $serverUser = $oldKey->keyActivateUser->serverUser;
                        if ($serverUser->panel) {
                            $panelStrategy = new \App\Services\Panel\PanelStrategy($serverUser->panel->panel);
                            $subscribeInfo = $panelStrategy->getSubscribeInfo(
                                $serverUser->panel->id,
                                $serverUser->id
                            );

                            // Вычисляем оставшийся трафик
                            if (isset($subscribeInfo['data_limit']) && isset($subscribeInfo['used_traffic'])) {
                                $dataLimit = (int)$subscribeInfo['data_limit'];
                                $usedTraffic = (int)$subscribeInfo['used_traffic'];
                                $remainingTraffic = max(0, $dataLimit - $usedTraffic);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Если не удалось получить информацию о трафике, используем исходный лимит
                    Log::warning('Не удалось получить информацию о трафике при перевыпуске ключа', [
                        'key_id' => $oldKey->id,
                        'error' => $e->getMessage(),
                        'source' => 'vpn'
                    ]);
                }

                // Вычисляем новую дату окончания (текущее время + оставшееся время)
                $newFinishAt = $currentTime + $remainingTime;

                // Если оставшееся время меньше 1 дня, устанавливаем минимум 1 день
                if ($remainingTime < 86400) {
                    $newFinishAt = $currentTime + 86400; // Минимум 1 день
                    Log::warning('Оставшееся время меньше 1 дня, установлен минимум', [
                        'old_key_id' => $oldKey->id,
                        'remaining_seconds' => $remainingTime,
                        'source' => 'vpn'
                    ]);
                }

                // Создаем новый ключ с учетом оставшегося времени и трафика
                $newKey = $this->keyActivateService->create(
                    $remainingTraffic,
                    $oldKey->pack_salesman_id,
                    $newFinishAt,
                    null
                );

                // Активируем новый ключ (передаем finish_at чтобы не пересчитывался)
                $activatedKey = $this->keyActivateService->activateWithFinishAt($newKey, $userTgId, $newFinishAt);

                if (!$activatedKey) {
                    throw new \Exception('Не удалось активировать новый ключ');
                }

                // Деактивируем старый ключ
                $oldKey->status = KeyActivate::EXPIRED;
                $oldKey->save();

                // Удаляем пользователя из панели Marzban для старого ключа
                // ВАЖНО: Удаляем только из панели, не из БД (чтобы сохранить историю)
                try {
                    if ($oldKey->keyActivateUser && $oldKey->keyActivateUser->serverUser) {
                        $serverUser = $oldKey->keyActivateUser->serverUser;
                        if ($serverUser->panel) {
                            // Используем стратегию для работы с панелью (независимо от типа)
                            $panel = $serverUser->panel;
                            $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
                            $panelStrategy = $panelStrategyFactory->create($panel->panel);
                            
                            // Обновляем токен через стратегию
                            $panel = $panelStrategy->updateToken($panel->id);
                            
                            // Удаляем пользователя через стратегию
                            $panelStrategy->deleteServerUser($panel->id, $serverUser->id);
                            
                            $this->logger->info('Пользователь удален из панели при перевыпуске ключа', [
                                'old_key_id' => $oldKey->id,
                                'new_key_id' => $newKey->id,
                                'server_user_id' => $serverUser->id,
                                'panel_id' => $panel->id,
                                'panel_type' => $panel->panel
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    // Логируем ошибку, но не прерываем процесс перевыпуска
                    Log::error('Ошибка при удалении пользователя из панели при перевыпуске ключа', [
                        'old_key_id' => $oldKey->id,
                        'new_key_id' => $newKey->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'source' => 'vpn'
                    ]);
                    // Не выбрасываем исключение - перевыпуск ключа должен продолжиться
                }

                // Обновляем информацию о замене ключа в нарушении
                // НЕ сбрасываем violation_count - сохраняем историю для отображения
                $violation->key_replaced_at = now();
                $violation->replaced_key_id = $newKey->id;
                // violation_count остается как есть - это история нарушений
                $violation->status = ConnectionLimitViolation::STATUS_RESOLVED;
                $violation->resolved_at = now();
                $violation->save();

                $this->logger->warning('Ключ перевыпущен с учетом оставшегося времени и трафика', [
                    'old_key_id' => $oldKey->id,
                    'new_key_id' => $newKey->id,
                    'violation_id' => $violation->id,
                    'user_tg_id' => $userTgId,
                    'old_finish_at' => $oldKey->finish_at,
                    'new_finish_at' => $newFinishAt,
                    'remaining_time_days' => round($remainingTime / 86400, 2),
                    'old_traffic_limit' => $oldKey->traffic_limit,
                    'new_traffic_limit' => $remainingTraffic
                ]);

                // Отправляем уведомление о новом ключе
                $this->sendKeyReplacementNotification($violation, $newKey);

                return $newKey;
            });
        } catch (\Exception $e) {
            Log::error('Ошибка перевыпуска ключа', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'source' => 'vpn'
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
            // Используем форматирование из ConnectionLimitMonitorService, но с новым ключом
            $message = "🔴 <b>Ключ заменен за нарушения</b>\n\n";
            $message .= "Превышен лимит нарушений правил использования.\n";
            $message .= "Ваш ключ доступа был автоматически заменен.\n\n";
            $message .= "Новый ключ: <code>{$newKey->id}</code>\n";
            $message .= "🔗 Конфигурация: https://vpn-telegram.com/config/{$newKey->id}";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '🔗 Открыть конфигурацию',
                            'url' => "https://vpn-telegram.com/config/{$newKey->id}"
                        ]
                    ],
                    [
                        [
                            'text' => '🆕 Новый ключ',
                            'url' => "https://vpn-telegram.com/config/{$newKey->id}"
                        ]
                    ]
                ]
            ];

            // Отправляем уведомление напрямую через notificationService
            $notificationService = app(\App\Services\Notification\TelegramNotificationService::class);
            $result = $notificationService->sendToUser($newKey, $message, $keyboard);

            if ($result) {
                $this->logger->info('Уведомление о замене ключа отправлено', [
                    'violation_id' => $violation->id,
                    'old_key_id' => $violation->key_activate_id,
                    'new_key_id' => $newKey->id,
                    'user_tg_id' => $newKey->user_tg_id
                ]);
            }

            return $result;
        } catch (\Exception $e) {
            Log::error('Ошибка отправки уведомления о замене ключа', [
                'violation_id' => $violation->id,
                'source' => 'vpn',
                'new_key_id' => $newKey->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
