<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Services\Key\KeyActivateService;
use App\Services\External\MarzbanAPI;
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
                $oldStatus = $oldKey->status;
                $oldKey->status = KeyActivate::EXPIRED;
                $oldKey->save();

                // Помечаем нарушение как решенное
                $this->limitMonitorService->resolveViolation($violation);

                $currentTime = time();
                $currentDate = date('Y-m-d H:i:s', $currentTime);

                Log::critical("🚫 [KEY: {$oldKey->id}] СТАТУС КЛЮЧА ИЗМЕНЕН НА EXPIRED (замена ключа из-за нарушения лимита подключений - ручная замена) | KEY_ID: {$oldKey->id} | {$oldKey->id}", [
                    'source' => 'vpn',
                    'action' => 'update_status_to_expired',
                    'key_id' => $oldKey->id,
                    'search_key' => $oldKey->id, // Для быстрого поиска
                    'search_tag' => 'KEY_EXPIRED',
                    'user_tg_id' => $oldKey->user_tg_id,
                    'old_status' => $oldStatus,
                    'old_status_text' => $this->getStatusTextByCode($oldStatus),
                    'new_status' => KeyActivate::EXPIRED,
                    'new_status_text' => 'EXPIRED (Просрочен)',
                    'reason' => 'Замена ключа из-за нарушения лимита подключений (ручная замена администратором)',
                    'violation_id' => $violation->id,
                    'new_key_id' => $newKey->id,
                    'old_key_finish_at' => $oldKey->finish_at,
                    'old_key_finish_at_date' => $oldKey->finish_at ? date('Y-m-d H:i:s', $oldKey->finish_at) : null,
                    'old_key_deleted_at' => $oldKey->deleted_at,
                    'old_key_deleted_at_date' => $oldKey->deleted_at ? date('Y-m-d H:i:s', $oldKey->deleted_at) : null,
                    'old_key_traffic_limit' => $oldKey->traffic_limit,
                    'pack_salesman_id' => $oldKey->pack_salesman_id,
                    'module_salesman_id' => $oldKey->module_salesman_id,
                    'current_time' => $currentTime,
                    'current_date' => $currentDate,
                    'admin_action' => true,
                    'method' => 'replaceKeyManually',
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);

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
            // Проверяем, прошло ли 30 минут с последнего уведомления по этому ключу
            $lastNotificationTime = $violation->last_notification_sent_at;
            if ($lastNotificationTime) {
                $minutesSinceLastNotification = $lastNotificationTime->diffInMinutes(now());
                if ($minutesSinceLastNotification < 30) {
                    Log::info('Пропущена отправка уведомления - прошло менее 30 минут с последнего уведомления', [
                        'violation_id' => $violation->id,
                        'key_id' => $violation->key_activate_id,
                        'minutes_since_last_notification' => round($minutesSinceLastNotification, 2),
                        'last_notification_sent_at' => $lastNotificationTime->format('Y-m-d H:i:s')
                    ]);
                    return false;
                }
            }
            
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
            // Проверяем, что нарушение существует в БД
            if (!$violation->exists) {
                throw new \Exception("Нарушение с ID {$violation->id} не существует в БД");
            }

            // Перезагружаем нарушение из БД для проверки актуальности
            $violation->refresh();
            if (!$violation->exists) {
                throw new \Exception("Нарушение с ID {$violation->id} было удалено из БД");
            }

            // Используем DB::transaction() для автоматического rollback при ошибках
            return DB::transaction(function () use ($violation) {
                // Еще раз проверяем внутри транзакции
                $violation->refresh();
                if (!$violation->exists) {
                    throw new \Exception("Нарушение с ID {$violation->id} не существует в БД внутри транзакции");
                }

                $oldKey = $violation->keyActivate;
                
                // Проверяем, что ключ существует
                if (!$oldKey) {
                    throw new \Exception("Ключ с ID {$violation->key_activate_id} не найден для нарушения {$violation->id}");
                }
                
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
                $oldStatus = $oldKey->status;
                $oldKey->status = KeyActivate::EXPIRED;
                $oldKey->save();

                // КРИТИЧЕСКИ ВАЖНО: Сохраняем нарушение ДО удаления пользователя сервера!
                // Иначе нарушение будет удалено каскадно при удалении server_user
                // Обновляем информацию о замене ключа в нарушении
                // НЕ сбрасываем violation_count - сохраняем историю для отображения
                $violation->key_replaced_at = now();
                $violation->replaced_key_id = $newKey->id;
                // violation_count остается как есть - это история нарушений
                $violation->status = ConnectionLimitViolation::STATUS_RESOLVED;
                $violation->resolved_at = now();
                $violationSaved = $violation->save();

                // Проверяем, что нарушение действительно сохранилось
                if (!$violationSaved) {
                    throw new \Exception('Не удалось сохранить информацию о замене ключа в нарушении');
                }

                // Перезагружаем нарушение из БД для проверки (ВАЖНО: внутри транзакции)
                $violation->refresh();
                if (!$violation->exists) {
                    throw new \Exception('Нарушение не найдено в БД после сохранения');
                }

                // Удаляем пользователей из панелей Marzban для старого ключа (все слоты при мульти-провайдере)
                // ВАЖНО: Удаляем только из панели, НЕ из БД (чтобы сохранить историю и нарушение)
                try {
                    $violationBeforeDelete = ConnectionLimitViolation::where('id', $violation->id)->exists();
                    $keyActivateUsers = $oldKey->keyActivateUsers()->with('serverUser.panel')->get();
                    foreach ($keyActivateUsers as $kau) {
                        $serverUser = $kau->serverUser;
                        if ($serverUser && $serverUser->panel) {
                            $panel = $serverUser->panel;
                            $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
                            $panelStrategy = $panelStrategyFactory->create($panel->panel);
                            $panel = $panelStrategy->updateToken($panel->id);
                            $marzbanApi = new MarzbanAPI($panel->api_address);
                            $marzbanApi->deleteUser($panel->auth_token, $serverUser->id);
                        }
                    }
                    foreach ($keyActivateUsers as $kau) {
                        $kau->delete();
                    }
                    $violationAfterDelete = ConnectionLimitViolation::where('id', $violation->id)->exists();
                    if (!$violationAfterDelete && $violationBeforeDelete) {
                        Log::critical('⚠️ КРИТИЧЕСКАЯ ОШИБКА: Нарушение было удалено при удалении keyActivateUser!', [
                            'violation_id' => $violation->id,
                            'old_key_id' => $oldKey->id,
                            'new_key_id' => $newKey->id,
                            'source' => 'vpn'
                        ]);
                        throw new \Exception("Нарушение с ID {$violation->id} было удалено при удалении keyActivateUser!");
                    }
                    $this->logger->info('Пользователи удалены из панелей при перевыпуске ключа (все слоты, serverUser в БД сохранены для истории)', [
                        'old_key_id' => $oldKey->id,
                        'new_key_id' => $newKey->id,
                        'slots_deleted' => $keyActivateUsers->count(),
                        'violation_id' => $violation->id,
                        'violation_exists_before_delete' => $violationBeforeDelete,
                        'violation_exists_after_delete' => $violationAfterDelete,
                        'note' => 'KeyActivateUser удалены, serverUser НЕ удалены из БД'
                    ]);
                } catch (\Exception $e) {
                    // Логируем ошибку, но не прерываем процесс перевыпуска
                    Log::error('Ошибка при удалении пользователя из панели при перевыпуске ключа', [
                        'old_key_id' => $oldKey->id,
                        'new_key_id' => $newKey->id,
                        'violation_id' => $violation->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                        'source' => 'vpn'
                    ]);
                    // Не выбрасываем исключение - перевыпуск ключа должен продолжиться
                }

                // Проверяем нарушение еще раз после удаления пользователя из панели
                $violation->refresh();
                if (!$violation->exists) {
                    throw new \Exception("Нарушение с ID {$violation->id} было удалено после удаления пользователя из панели!");
                }
                
                // Финальная проверка через прямой запрос к БД
                $violationExistsInDb = ConnectionLimitViolation::where('id', $violation->id)->exists();
                if (!$violationExistsInDb) {
                    throw new \Exception("Нарушение с ID {$violation->id} не найдено в БД после всех операций!");
                }

                // Сохраняем ID нарушения и другие данные для логирования
                $violationId = $violation->id;
                $oldKeyId = $oldKey->id;
                $newKeyId = $newKey->id;
                $packSalesmanId = $oldKey->pack_salesman_id;
                $moduleSalesmanId = $oldKey->module_salesman_id;
                $oldKeyFinishAt = $oldKey->finish_at;
                $oldKeyDeletedAt = $oldKey->deleted_at;
                $oldKeyTrafficLimit = $oldKey->traffic_limit;
                $hasServerUser = $oldKey->keyActivateUser && $oldKey->keyActivateUser->serverUser ? true : false;
                $serverUserId = ($oldKey->keyActivateUser && $oldKey->keyActivateUser->serverUser) ? $oldKey->keyActivateUser->serverUser->id : null;
                $panelId = ($oldKey->keyActivateUser && $oldKey->keyActivateUser->serverUser) ? $oldKey->keyActivateUser->serverUser->panel_id : null;

                // Коммитим транзакцию ПЕРЕД логированием
                // Это гарантирует, что если транзакция откатится, лог не будет записан
                // (хотя на самом деле логи пишутся вне транзакции, но мы хотя бы убедимся что данные сохранены)

                $currentTimeForLog = time();
                $currentDateForLog = date('Y-m-d H:i:s', $currentTimeForLog);

                // Проверяем нарушение еще раз после коммита (если транзакция уже закоммичена)
                // Но так как мы внутри транзакции, проверяем еще раз перед логированием
                $violationExists = ConnectionLimitViolation::where('id', $violationId)->exists();
                if (!$violationExists) {
                    throw new \Exception("Нарушение с ID {$violationId} не найдено в БД перед логированием");
                }

                // Логируем ПОСЛЕ успешного сохранения нарушения и проверки его существования
                Log::critical("🚫 [KEY: {$oldKeyId}] СТАТУС КЛЮЧА ИЗМЕНЕН НА EXPIRED (замена ключа из-за нарушения лимита подключений - автоматическая замена) | KEY_ID: {$oldKeyId} | {$oldKeyId}", [
                    'source' => 'vpn',
                    'action' => 'update_status_to_expired',
                    'key_id' => $oldKey->id,
                    'search_key' => $oldKey->id, // Для быстрого поиска
                    'search_tag' => 'KEY_EXPIRED',
                    'user_tg_id' => $oldKey->user_tg_id,
                    'old_status' => $oldStatus,
                    'old_status_text' => $this->getStatusTextByCode($oldStatus),
                    'new_status' => KeyActivate::EXPIRED,
                    'new_status_text' => 'EXPIRED (Просрочен)',
                    'reason' => 'Замена ключа из-за нарушения лимита подключений (автоматическая замена)',
                    'violation_id' => $violationId,
                    'violation_exists' => $violationExists, // Проверка что нарушение существует в БД
                    'violation_status' => $violation->status,
                    'violation_key_replaced_at' => $violation->key_replaced_at ? $violation->key_replaced_at->format('Y-m-d H:i:s') : null,
                    'violation_replaced_key_id' => $violation->replaced_key_id,
                    'new_key_id' => $newKeyId,
                    'old_key_finish_at' => $oldKeyFinishAt,
                    'old_key_finish_at_date' => $oldKeyFinishAt ? date('Y-m-d H:i:s', $oldKeyFinishAt) : null,
                    'old_key_deleted_at' => $oldKeyDeletedAt,
                    'old_key_deleted_at_date' => $oldKeyDeletedAt ? date('Y-m-d H:i:s', $oldKeyDeletedAt) : null,
                    'old_key_traffic_limit' => $oldKeyTrafficLimit,
                    'old_key_remaining_traffic' => $remainingTraffic,
                    'old_key_remaining_time_seconds' => $remainingTime,
                    'old_key_remaining_time_days' => round($remainingTime / 86400, 1),
                    'new_key_finish_at' => $newFinishAt,
                    'new_key_finish_at_date' => date('Y-m-d H:i:s', $newFinishAt),
                    'new_key_traffic_limit' => $remainingTraffic,
                    'pack_salesman_id' => $packSalesmanId,
                    'module_salesman_id' => $moduleSalesmanId,
                    'current_time' => $currentTimeForLog,
                    'current_date' => $currentDateForLog,
                    'has_server_user' => $hasServerUser,
                    'server_user_id' => $serverUserId,
                    'panel_id' => $panelId,
                    'admin_action' => false,
                    'method' => 'replaceKeyAutomatically',
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);

                $this->logger->warning('Ключ перевыпущен с учетом оставшегося времени и трафика', [
                    'old_key_id' => $oldKey->id,
                    'new_key_id' => $newKey->id,
                    'violation_id' => $violation->id,
                    'violation_exists' => $violation->exists,
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
            // Проверяем, существует ли нарушение в БД
            $violationExists = false;
            $violationId = $violation->id ?? 'unknown';
            try {
                $violationExists = ConnectionLimitViolation::where('id', $violationId)->exists();
            } catch (\Exception $checkException) {
                // Игнорируем ошибку проверки
            }

            Log::error('Ошибка перевыпуска ключа', [
                'violation_id' => $violationId,
                'violation_exists_in_db' => $violationExists,
                'violation_key_activate_id' => $violation->key_activate_id ?? null,
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

    /**
     * Получить текстовое представление статуса по коду
     *
     * @param int $statusCode
     * @return string
     */
    private function getStatusTextByCode(int $statusCode): string
    {
        switch ($statusCode) {
            case KeyActivate::EXPIRED:
                return 'EXPIRED (Просрочен)';
            case KeyActivate::ACTIVE:
                return 'ACTIVE (Активирован)';
            case KeyActivate::PAID:
                return 'PAID (Оплачен)';
            case KeyActivate::DELETED:
                return 'DELETED (Удален)';
            default:
                return "Unknown ({$statusCode})";
        }
    }
}
