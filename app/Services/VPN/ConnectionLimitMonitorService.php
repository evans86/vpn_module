<?php

namespace App\Services\VPN;

use App\Models\VPN\ConnectionLimitViolation;
use App\Models\KeyActivate\KeyActivate;
use App\Logging\DatabaseLogger;
use App\Dto\Notification\NotificationResult;
use Illuminate\Support\Facades\Log;
use App\Services\Notification\TelegramNotificationService;
use App\Services\VPN\ViolationManualService;

class ConnectionLimitMonitorService
{
    private DatabaseLogger $logger;
    private TelegramNotificationService $notificationService;


    public function __construct(
        DatabaseLogger $logger,
        TelegramNotificationService $notificationService
    ) {
        $this->logger = $logger;
        $this->notificationService = $notificationService;
    }

    /**
     * Запись нарушения лимита подключений
     * Улучшенная логика: если есть активное нарушение для этого ключа, увеличиваем счетчик
     */
    public function recordViolation(
        KeyActivate $keyActivate,
        int $uniqueIpCount,
        array $ipAddresses = [],
        ?int $panelId = null
    ): ConnectionLimitViolation {
        try {
            // ВАЖНО: Не фиксируем нарушения для просроченных или неактивных ключей
            // Если ключ был перевыпущен или деактивирован, нарушения не должны фиксироваться
            if ($keyActivate->status !== KeyActivate::ACTIVE) {
                $this->logger->info('Пропущена фиксация нарушения - ключ не активен', [
                    'key_id' => $keyActivate->id,
                    'key_status' => $keyActivate->status,
                    'user_tg_id' => $keyActivate->user_tg_id
                ]);

                // Если есть активное нарушение для этого ключа, помечаем его как решенное
                // так как ключ больше не активен и нарушения не должны фиксироваться
                $existingViolation = ConnectionLimitViolation::where([
                    'key_activate_id' => $keyActivate->id,
                    'status' => ConnectionLimitViolation::STATUS_ACTIVE
                ])->first();

                if ($existingViolation) {
                    // Помечаем нарушение как решенное, так как ключ больше не активен
                    $existingViolation->status = ConnectionLimitViolation::STATUS_RESOLVED;
                    $existingViolation->resolved_at = now();
                    $existingViolation->save();

                    $this->logger->info('Нарушение помечено как решенное - ключ не активен', [
                        'violation_id' => $existingViolation->id,
                        'key_id' => $keyActivate->id,
                        'key_status' => $keyActivate->status
                    ]);

                    return $existingViolation;
                }

                // Если нарушения нет, выбрасываем исключение
                // Вызывающий код должен обработать это и не фиксировать нарушение
                throw new \Exception('Ключ не активен (статус: ' . $keyActivate->status . '), нарушение не может быть зафиксировано');
            }

            $allowedConnections = 3; // Лимит подключений
            $serverUser = $keyActivate->keyActivateUser->serverUser;

            // Если panelId не указан, используем панель пользователя
            if (!$panelId) {
                $panel = $serverUser->panel;
                $panelId = $panel->id;
            }

            // Проверяем есть ли уже активное нарушение для этого ключа
            // Учитываем перекрытие окон: если нарушение создано в последние 20 минут, это может быть дубликат
            $existingViolation = ConnectionLimitViolation::where([
                'key_activate_id' => $keyActivate->id,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ])
            ->where('created_at', '>=', now()->subMinutes(20)) // Защита от дублирования при перекрытии окон
            ->first();

            if ($existingViolation) {
                // Если нарушение создано недавно (в последние 20 минут), это может быть перекрытие окон
                // Проверяем, изменились ли IP адреса - если те же, это дубликат, пропускаем
                $existingIps = $existingViolation->ip_addresses ?? [];
                $currentIps = array_values(array_unique($ipAddresses));

                // Сортируем для сравнения
                sort($existingIps);
                sort($currentIps);

                // Если IP адреса идентичны и нарушение создано недавно (менее 15 минут назад), это дубликат
                // ДОПОЛНИТЕЛЬНО: если уведомление было отправлено недавно (менее 2 минут назад), это тоже дубликат
                $isDuplicateIps = $existingIps === $currentIps;
                $isRecentViolation = $existingViolation->created_at->diffInMinutes(now()) < 15;
                $isRecentNotification = $existingViolation->last_notification_sent_at &&
                                       $existingViolation->last_notification_sent_at->diffInSeconds(now()) < 120;

                if (($isDuplicateIps && $isRecentViolation) || $isRecentNotification) {
                    // Это дубликат из-за перекрытия окон или недавней отправки - просто обновляем время, но не увеличиваем счетчик
                    $existingViolation->actual_connections = $uniqueIpCount; // Обновляем количество на случай изменений
                    $existingViolation->created_at = now(); // Обновляем время последней проверки
                    $existingViolation->save();

                    $this->logger->info('Пропущено дублирующее нарушение (перекрытие окон или недавняя отправка)', [
                        'key_id' => $keyActivate->id,
                        'violation_id' => $existingViolation->id,
                        'created_at' => $existingViolation->created_at->format('Y-m-d H:i:s'),
                        'last_notification_sent_at' => $existingViolation->last_notification_sent_at ? $existingViolation->last_notification_sent_at->format('Y-m-d H:i:s') : null,
                        'is_duplicate_ips' => $isDuplicateIps,
                        'is_recent_notification' => $isRecentNotification
                    ]);

                    return $existingViolation;
                }

                // IP изменились или прошло достаточно времени - это новое нарушение
                // Увеличиваем счетчик нарушений и обновляем данные
                $existingViolation->violation_count += 1;
                $newViolationCount = $existingViolation->violation_count;
                $existingViolation->actual_connections = $uniqueIpCount;
                // Храним только IP текущего нарушения, не объединяем с предыдущими
                $existingViolation->ip_addresses = $currentIps; // Сохраняем только IP текущего нарушения
                $existingViolation->created_at = now(); // Обновляем время последнего нарушения
                $existingViolation->save();

                $this->logger->warning('Обновлено нарушение лимита подключений (повторное)', [
                    'key_id' => $keyActivate->id,
                    'user_tg_id' => $keyActivate->user_tg_id,
                    'violation_count' => $newViolationCount,
                    'actual_ips' => $uniqueIpCount,
                    'violation_id' => $existingViolation->id
                ]);

                // Отправляем уведомление сразу при увеличении счетчика нарушений
                // Проверяем, что уведомление еще не отправлено для текущего количества нарушений
                // ВАЖНО: Проверяем ПЕРЕД отправкой, чтобы избежать дублирования
                $notificationsSent = $existingViolation->getNotificationsSentCount();
                if ($notificationsSent < $newViolationCount) {
                    // Дополнительная проверка: если уведомление было отправлено недавно (менее 1 минуты назад),
                    // и violation_count не изменился, не отправляем повторно (защита от гонок)
                    if ($existingViolation->last_notification_sent_at &&
                        $existingViolation->last_notification_sent_at->diffInSeconds(now()) < 60 &&
                        $notificationsSent >= ($newViolationCount - 1)) {
                        $this->logger->info('Пропущена отправка уведомления - недавно уже было отправлено', [
                            'violation_id' => $existingViolation->id,
                            'violation_count' => $newViolationCount,
                            'notifications_sent' => $notificationsSent,
                            'last_sent_at' => $existingViolation->last_notification_sent_at->format('Y-m-d H:i:s')
                        ]);
                        return $existingViolation;
                    }

                    try {
                        $result = $this->sendViolationNotificationWithResult($existingViolation);
                        if ($result->shouldCountAsSent) {
                            $existingViolation->incrementNotifications();
                            $existingViolation->last_notification_status = $result->status;
                            $existingViolation->last_notification_error = $result->errorMessage;
                            $existingViolation->save();

                            $this->logger->info('Уведомление отправлено сразу при фиксации нарушения', [
                                'violation_id' => $existingViolation->id,
                                'violation_count' => $newViolationCount,
                                'status' => $result->status
                            ]);

                            // При 3-м нарушении сразу перевыпускаем ключ
                            if ($newViolationCount >= 3 && is_null($existingViolation->key_replaced_at)) {
                                try {
                                    $manualService = app(ViolationManualService::class);
                                    $newKey = $manualService->reissueKey($existingViolation->fresh());
                                    if ($newKey) {
                                        $this->logger->warning('Ключ автоматически перевыпущен при 3-м нарушении', [
                                            'violation_id' => $existingViolation->id,
                                            'old_key_id' => $existingViolation->key_activate_id,
                                            'new_key_id' => $newKey->id
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    Log::error('Ошибка автоматического перевыпуска ключа при 3-м нарушении', [
                                        'violation_id' => $existingViolation->id,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        } else {
                            // Техническая ошибка - сохраняем для повторной попытки через ProcessViolationsCommand
                            $existingViolation->last_notification_status = $result->status;
                            $existingViolation->last_notification_error = $result->errorMessage;
                            $existingViolation->notification_retry_count = ($existingViolation->notification_retry_count ?? 0) + 1;
                            $existingViolation->save();

                            $this->logger->warning('Не удалось отправить уведомление при фиксации нарушения (будет повторная попытка)', [
                                'violation_id' => $existingViolation->id,
                                'violation_count' => $newViolationCount,
                                'status' => $result->status,
                                'error' => $result->errorMessage
                            ]);
                        }
                    } catch (\Exception $e) {
                        // Логируем ошибку, но не прерываем процесс
                        Log::error('Ошибка отправки уведомления при фиксации нарушения', [
                            'violation_id' => $existingViolation->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                return $existingViolation;
            }

            // Создаем новое нарушение
            $violation = ConnectionLimitViolation::create([
                'key_activate_id' => $keyActivate->id,
                'server_user_id' => $serverUser->id,
                'panel_id' => $panelId,
                'user_tg_id' => $keyActivate->user_tg_id,
                'allowed_connections' => $allowedConnections,
                'actual_connections' => $uniqueIpCount, // Количество уникальных IP
                'ip_addresses' => $ipAddresses,
                'violation_count' => 1,
                'status' => ConnectionLimitViolation::STATUS_ACTIVE
            ]);

            $this->logger->warning('Зафиксировано нарушение лимита подключений', [
                'key_id' => $keyActivate->id,
                'user_tg_id' => $keyActivate->user_tg_id,
                'allowed_connections' => $allowedConnections,
                'actual_ips' => $uniqueIpCount,
                'ip_addresses' => $ipAddresses,
                'violation_id' => $violation->id
            ]);

            // Отправляем уведомление сразу при создании первого нарушения
            try {
                $result = $this->sendViolationNotificationWithResult($violation);
                if ($result->shouldCountAsSent) {
                    $violation->incrementNotifications();
                    $violation->last_notification_status = $result->status;
                    $violation->last_notification_error = $result->errorMessage;
                    $violation->save();

                    $this->logger->info('Уведомление отправлено сразу при фиксации первого нарушения', [
                        'violation_id' => $violation->id,
                        'status' => $result->status
                    ]);

                    // При 3-м нарушении сразу перевыпускаем ключ (хотя для первого нарушения это маловероятно)
                    if ($violation->violation_count >= 3 && is_null($violation->key_replaced_at)) {
                        try {
                            $manualService = app(ViolationManualService::class);
                            $newKey = $manualService->reissueKey($violation->fresh());
                            if ($newKey) {
                                $this->logger->warning('Ключ автоматически перевыпущен при 3-м нарушении', [
                                    'violation_id' => $violation->id,
                                    'old_key_id' => $violation->key_activate_id,
                                    'new_key_id' => $newKey->id
                                ]);
                            }
                        } catch (\Exception $e) {
                            Log::error('Ошибка автоматического перевыпуска ключа при 3-м нарушении', [
                                'violation_id' => $violation->id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    }
                } else {
                    // Техническая ошибка - сохраняем для повторной попытки через ProcessViolationsCommand
                    $violation->last_notification_status = $result->status;
                    $violation->last_notification_error = $result->errorMessage;
                    $violation->notification_retry_count = 1;
                    $violation->save();

                    $this->logger->warning('Не удалось отправить уведомление при фиксации первого нарушения (будет повторная попытка)', [
                        'violation_id' => $violation->id,
                        'status' => $result->status,
                        'error' => $result->errorMessage
                    ]);
                }
            } catch (\Exception $e) {
                // Логируем ошибку, но не прерываем процесс
                Log::error('Ошибка отправки уведомления при фиксации первого нарушения', [
                    'violation_id' => $violation->id,
                    'error' => $e->getMessage()
                ]);
            }

            return $violation;

        } catch (\Exception $e) {
            Log::error('Ошибка записи нарушения лимита подключений', [
                'key_id' => $keyActivate->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Запись нарушения с дополнительной информацией
     */
    public function recordViolationWithDetails(
        KeyActivate $keyActivate,
        int $uniqueIpCount,
        array $ipAddresses = [],
        ?int $panelId = null,
        array $violationDetails = []
    ): ConnectionLimitViolation {

        $violation = $this->recordViolation($keyActivate, $uniqueIpCount, $ipAddresses, $panelId);

        // Логируем детали нарушения
        $this->logger->warning('Зафиксировано нарушение с деталями', [
            'key_id' => $keyActivate->id,
            'user_tg_id' => $keyActivate->user_tg_id,
            'unique_ips_count' => $uniqueIpCount,
            'network_count' => $violationDetails['network_count'] ?? 0,
            'violation_type' => $violationDetails['type'] ?? 'multiple_networks',
            'violation_id' => $violation->id
        ]);

        return $violation;
    }

    /**
     * Получить статистику нарушений
     */
    public function getViolationStats(): array
    {
        $total = ConnectionLimitViolation::count();
        $active = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_ACTIVE)->count();
        $today = ConnectionLimitViolation::whereDate('created_at', today())->count();
        $critical = ConnectionLimitViolation::where('violation_count', '>=', 3)
            ->where('status', ConnectionLimitViolation::STATUS_ACTIVE)
            ->count();
        $resolved = ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_RESOLVED)->count();

        $topViolators = ConnectionLimitViolation::with('keyActivate')
            ->select('key_activate_id')
            ->selectRaw('COUNT(*) as violation_count')
            ->groupBy('key_activate_id')
            ->orderBy('violation_count', 'desc')
            ->limit(5)
            ->get();

        return [
            'total' => $total,
            'active' => $active,
            'today' => $today,
            'critical' => $critical,
            'resolved' => $resolved,
            'top_violators' => $topViolators
        ];
    }

    /**
     * Пометить нарушение как решенное
     */
    public function resolveViolation(ConnectionLimitViolation $violation): bool
    {
        try {
            $violation->update([
                'status' => ConnectionLimitViolation::STATUS_RESOLVED,
                'resolved_at' => now()
            ]);

            $this->logger->info('Нарушение лимита помечено как решенное', [
                'violation_id' => $violation->id,
                'key_id' => $violation->key_activate_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Ошибка при разрешении нарушения', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Пометить нарушение как проигнорированное
     */
    public function ignoreViolation(ConnectionLimitViolation $violation): bool
    {
        try {
            $violation->update([
                'status' => ConnectionLimitViolation::STATUS_IGNORED,
                'resolved_at' => now()
            ]);

            $this->logger->info('Нарушение лимита помечено как проигнорированное', [
                'violation_id' => $violation->id,
                'key_id' => $violation->key_activate_id
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Ошибка при игнорировании нарушения', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Отправка уведомления пользователю о нарушении (старый метод для обратной совместимости)
     */
    public function sendViolationNotification(ConnectionLimitViolation $violation): bool
    {
        $result = $this->sendViolationNotificationWithResult($violation);
        return $result->shouldCountAsSent;
    }

    /**
     * Отправка уведомления пользователю о нарушении с детальным результатом
     */
    public function sendViolationNotificationWithResult(ConnectionLimitViolation $violation): NotificationResult
    {
        try {
            $keyActivate = $violation->keyActivate;

            if (!$keyActivate || !$keyActivate->user_tg_id) {
                Log::warning('Cannot send violation notification: user not found', [
                    'violation_id' => $violation->id,
                    'key_activate_id' => $violation->key_activate_id
                ]);
                return NotificationResult::userNotFound();
            }

            $message = $this->formatViolationMessage($violation);
            $keyboard = $this->getViolationKeyboard($violation);

            // Отправляем уведомление пользователю с детальным результатом
            $result = $this->notificationService->sendToUserWithResult($keyActivate, $message, $keyboard);

            if ($result->isSuccess()) {
                $this->logger->info('Уведомление о нарушении отправлено успешно', [
                    'violation_id' => $violation->id,
                    'user_tg_id' => $keyActivate->user_tg_id,
                    'violation_count' => $violation->violation_count
                ]);
            } elseif ($result->isBlocked()) {
                $this->logger->warning('Уведомление не доставлено: пользователь заблокировал бота', [
                    'violation_id' => $violation->id,
                    'user_tg_id' => $keyActivate->user_tg_id,
                    'violation_count' => $violation->violation_count,
                    'error' => $result->errorMessage
                ]);
            } else {
                $this->logger->error('Уведомление не доставлено: техническая ошибка', [
                    'violation_id' => $violation->id,
                    'user_tg_id' => $keyActivate->user_tg_id,
                    'violation_count' => $violation->violation_count,
                    'error' => $result->errorMessage
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Failed to send violation notification', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage()
            ]);
            return NotificationResult::technicalError($e->getMessage());
        }
    }

    /**
     * Отправка уведомления продавцу о нарушении его пользователя
     */
    public function sendViolationNotificationToSalesman(ConnectionLimitViolation $violation): bool
    {
        try {
            $keyActivate = $violation->keyActivate;

            if (!$keyActivate) {
                Log::warning('Cannot send notification to salesman: keyActivate not found', [
                    'violation_id' => $violation->id
                ]);
                return false;
            }

            // Определяем продавца
            $salesman = null;
            if (!is_null($keyActivate->module_salesman_id)) {
                $salesman = $keyActivate->moduleSalesman;
            } else if (!is_null($keyActivate->pack_salesman_id)) {
                // Проверяем наличие packSalesman перед доступом к salesman
                if ($keyActivate->packSalesman) {
                $salesman = $keyActivate->packSalesman->salesman;
                } else {
                    Log::warning('Cannot send notification to salesman: packSalesman not found', [
                        'violation_id' => $violation->id,
                        'pack_salesman_id' => $keyActivate->pack_salesman_id
                    ]);
                    return false;
                }
            }

            if (!$salesman || !$salesman->telegram_id) {
                Log::warning('Cannot send notification to salesman: salesman not found or no telegram_id', [
                    'violation_id' => $violation->id,
                    'salesman_id' => $salesman ? $salesman->id : null
                ]);
                return false;
            }

            $message = $this->formatSalesmanViolationMessage($violation);

            return $this->notificationService->sendToSalesman($salesman, $message);

        } catch (\Exception $e) {
            Log::error('Failed to send violation notification to salesman', [
                'violation_id' => $violation->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Форматирование сообщения о нарушении для пользователя
     */
    private function formatViolationMessage(ConnectionLimitViolation $violation): string
    {
        $violationCount = $violation->violation_count;
        $ipCount = $violation->actual_connections;
        $allowedCount = $violation->allowed_connections;

        $messages = [
            1 => "⚠️ <b>Предупреждение о нарушении</b>\n\n"
                . "Обнаружено превышение лимита одновременных подключений:\n"
                . "• Разрешено: <b>{$allowedCount} подключения</b>\n"
                . "• Обнаружено: <b>{$ipCount} подключений</b>\n\n"
                . "Следующие нарушения приведут к смене ключа доступа.",

            2 => "🚨 <b>Второе предупреждение</b>\n\n"
                . "Повторное превышение лимита подключений!\n"
                . "• Разрешено: <b>{$allowedCount} подключения</b>\n"
                . "• Обнаружено: <b>{$ipCount} подключений</b>\n\n"
                . "При следующем нарушении ваш ключ будет автоматически заменен.",

            3 => "🔴 <b>Третье нарушение - ключ будет заменен</b>\n\n"
                . "Превышен лимит нарушений правил использования.\n"
                . "Ваш ключ доступа будет автоматически заменен в ближайшее время.\n\n"
                . "Вы получите уведомление с новым ключом после его перевыпуска."
        ];

        return $messages[$violationCount] ?? $messages[1];
    }

    /**
     * Форматирование сообщения о нарушении для продавца
     */
    private function formatSalesmanViolationMessage(ConnectionLimitViolation $violation): string
    {
        $keyActivate = $violation->keyActivate;
        $violationCount = $violation->violation_count;
        $ipCount = $violation->actual_connections;

        return "📊 <b>Уведомление о нарушении</b>\n\n"
            . "У вашего пользователя обнаружено нарушение:\n"
            . "• Пользователь: <code>{$keyActivate->user_tg_id}</code>\n"
            . "• Ключ: <code>{$keyActivate->id}</code>\n"
            . "• Нарушений: <b>{$violationCount}</b>\n"
            . "• Подключений: <b>{$ipCount}</b>\n"
            . "• Время: {$violation->created_at->format('d.m.Y H:i')}";
    }

    /**
     * Получение клавиатуры для уведомления
     */
    private function getViolationKeyboard(ConnectionLimitViolation $violation): array
    {
        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🔗 Открыть конфигурацию',
                        'url' => "https://vpn-telegram.com/config/{$violation->keyActivate->id}"
                    ]
                ]
            ]
        ];

        // Для 3-го нарушения добавляем кнопку с новым ключом
        if ($violation->violation_count >= 3) {
            $keyboard['inline_keyboard'][] = [
                [
                    'text' => '🆕 Новый ключ',
                    'url' => "https://vpn-telegram.com/config/{$violation->keyActivate->id}"
                ]
            ];
        }

        return $keyboard;
    }


    /**
     * Получить расширенную статистику
     */
    public function getAdvancedViolationStats(): array
    {
        $baseStats = $this->getViolationStats();

        // Статистика по дням
        $dailyStats = ConnectionLimitViolation::selectRaw('
            DATE(created_at) as date,
            COUNT(*) as total,
            SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN violation_count >= 3 THEN 1 ELSE 0 END) as critical
        ')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date', 'desc')
            ->get();

        // Топ нарушителей
        $topViolators = ConnectionLimitViolation::with('keyActivate')
            ->select('user_tg_id')
            ->selectRaw('COUNT(*) as violation_count, MAX(violation_count) as max_severity')
            ->groupBy('user_tg_id')
            ->orderBy('violation_count', 'desc')
            ->limit(10)
            ->get();

        return array_merge($baseStats, [
            'daily_stats' => $dailyStats,
            'top_violators' => $topViolators,
            'critical' => ConnectionLimitViolation::where('violation_count', '>=', 3)
                ->where('status', ConnectionLimitViolation::STATUS_ACTIVE)
                ->count(),
            'resolved' => ConnectionLimitViolation::where('status', ConnectionLimitViolation::STATUS_RESOLVED)->count(),
            'auto_resolved_today' => ConnectionLimitViolation::whereDate('resolved_at', today())
                ->where('status', ConnectionLimitViolation::STATUS_RESOLVED)
                ->count()
        ]);
    }
}
