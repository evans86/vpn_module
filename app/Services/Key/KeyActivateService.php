<?php

namespace App\Services\Key;

use App\Dto\Bot\BotModuleDto;
use App\Helpers\OrderHelper;
use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
use App\Models\Salesman\Salesman;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Panel\PanelRepository;
use App\Logging\DatabaseLogger;
use App\Services\External\BottApi;
use App\Services\Panel\PanelStrategy;
use App\Services\Notification\NotificationService;
use App\Services\Server\ServerStrategy;
use Carbon\Carbon;
use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Exception;

class KeyActivateService
{
    private KeyActivateRepository $keyActivateRepository;
    private PackSalesmanRepository $packSalesmanRepository;
    private DatabaseLogger $logger;
    private PanelRepository $panelRepository;
    private NotificationService $notificationService;

    public function __construct(
        KeyActivateRepository  $keyActivateRepository,
        PackSalesmanRepository $packSalesmanRepository,
        PanelRepository        $panelRepository,
        DatabaseLogger         $logger,
        NotificationService    $notificationService
    )
    {
        $this->keyActivateRepository = $keyActivateRepository;
        $this->packSalesmanRepository = $packSalesmanRepository;
        $this->panelRepository = $panelRepository;
        $this->logger = $logger;
        $this->notificationService = $notificationService;
    }

    /**
     * Создание ключа
     *
     * @param int|null $traffic_limit
     * @param int|null $pack_salesman_id
     * @param int|null $finish_at
     * @param int|null $deleted_at
     * @return KeyActivate
     * @throws Exception
     */
    public function create(?int $traffic_limit, ?int $pack_salesman_id, ?int $finish_at, ?int $deleted_at): KeyActivate
    {
        try {
            if (!is_null($pack_salesman_id)) {
                $packSalesman = $this->packSalesmanRepository->findByIdOrFail($pack_salesman_id);
                $pack_salesman_id = $packSalesman->id;
            }

            $keyData = [
                'id' => Str::uuid()->toString(),
                'traffic_limit' => $traffic_limit,
                'pack_salesman_id' => $pack_salesman_id,
                'finish_at' => $finish_at,
                'deleted_at' => $deleted_at,
                'status' => KeyActivate::PAID
            ];

            $keyActivate = $this->keyActivateRepository->createKey($keyData);

            $this->logger->info('Ключ успешно создан', [
                'source' => 'key_activate',
                'action' => 'create',
                'key_id' => $keyActivate->id,
                'pack_salesman_id' => $pack_salesman_id,
                'traffic_limit' => $traffic_limit,
                'finish_at' => $finish_at,
                'deleted_at' => $deleted_at
            ]);

            return $keyActivate;
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при создании ключа (RuntimeException)', [
                'source' => 'key_activate',
                'action' => 'create',
                'pack_salesman_id' => $pack_salesman_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } catch (Exception $e) {
            $this->logger->error('Ошибка при создании ключа', [
                'source' => 'key_activate',
                'action' => 'create',
                'pack_salesman_id' => $pack_salesman_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Покупка ключа в боте продаж
     *
     * @param BotModuleDto $botModuleDto
     * @param int $product_id
     * @param array $userData
     * @return KeyActivate
     * @throws GuzzleException
     */
    public function buyKey(BotModuleDto $botModuleDto, int $product_id, array $userData): KeyActivate
    {
        try {
            // Определение категории товара
            $categoryMap = [
                1 => \App\Constants\ProductConstants::CATEGORY_1_MONTH,
                3 => \App\Constants\ProductConstants::CATEGORY_3_MONTHS,
                6 => \App\Constants\ProductConstants::CATEGORY_6_MONTHS,
                12 => \App\Constants\ProductConstants::CATEGORY_12_MONTHS
            ];

            if (!isset($categoryMap[$product_id])) {
                throw new DomainException('VPN продукт не найден');
            }
            $category_id = $categoryMap[$product_id];

            // Получение цены из строки tariff_cost (в рублях)
            $key_price_rub = null;
            foreach (explode(',', $botModuleDto->tariff_cost) as $priceEntry) {
                [$period, $cost] = explode('-', $priceEntry);
                if ((int)$period === $product_id) {
                    $key_price_rub = (int)$cost;
                    break;
                }
            }

            if ($key_price_rub === null) {
                throw new DomainException('Цена для выбранного VPN продукта не найдена');
            }

            $key_price_kopecks = $key_price_rub * 100;

            if ($key_price_kopecks > $userData['money']) {
                throw new RuntimeException('Недостаточно средств на балансе. Требуется: ' . $key_price_rub . ' руб.');
            }

            // Списание средств
            $paymentResult = BottApi::subtractBalance(
                $botModuleDto,
                $userData,
                $key_price_kopecks,
                'Списание баланса для ключа VPN'
            );

            if (!$paymentResult['result']) {
                throw new RuntimeException('Ошибка при списании баланса: ' . $paymentResult['message']);
            }

            // Создание заказа
            $order = BottApi::createOrderSalesman($botModuleDto, $category_id, 1);

            if (!$order['result']) {
                BottApi::addBalance(
                    $botModuleDto,
                    $userData,
                    $key_price_kopecks,
                    'Возврат баланса (ошибка при создании заказа) ' . OrderHelper::formingError($order['message'])
                );

                throw new RuntimeException('Ошибка при списании баланса, ' . OrderHelper::formingError($order['message']));
            } else {
                $this->logger->warning('ORDER', [
                    'ORDER' => $order,
                ]);

                $keyID = $order['data']['product']['data'];

                BottApi::createOrder($botModuleDto, $userData, $key_price_kopecks,
                    'Покупка VPN доступа: ' . $keyID);
            }

            $salesman = Salesman::query()->where('module_bot_id', $botModuleDto->id)->first();

            $keyActivate = $this->keyActivateRepository->findById($keyID);

            if (!$keyActivate) {
                throw new RuntimeException("Key activate with ID {$keyID} not found");
            }

            if ($salesman) {
                $keyActivate->module_salesman_id = $salesman->id;
                $keyActivate->save();
            } else {
                // Логируем только один раз, что ключ создан без привязки к продавцу
                // Это не критическая ошибка, так как процесс продолжается успешно
                $this->logger->info('Ключ создан без привязки к продавцу (модуль не привязан к продавцу)', [
                    'key_id' => $keyID,
                    'module_bot_id' => $botModuleDto->id,
                    'source' => 'key'
                ]);
            }

            return $keyActivate;
        } catch (Exception $e) {
            $this->logger->error('Ошибка при покупке ключа', [
                'source' => 'key_activate',
                'action' => 'buy_key',
                'product_id' => $product_id,
                'user_tg_id' => $userData['user_tg_id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Активация ключа купленного в боте продаж
     *
     * @param KeyActivate $key
     * @param int $userTgId
     * @return KeyActivate
     * @throws GuzzleException
     */
    public function activateModuleKey(KeyActivate $key, int $userTgId): KeyActivate
    {
        try {
            // Проверяем текущий статус
            if (!$this->keyActivateRepository->hasCorrectStatusForActivation($key)) {
                throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
            }

            // Проверяем, не истек ли срок для активации
//            if ($this->keyActivateRepository->isActivationPeriodExpired($key)) {
//                throw new RuntimeException('Срок активации ключа истек');
//            }

            // Проверяем, не занят ли уже ключ другим пользователем
            if ($this->keyActivateRepository->isUsedByAnotherUser($key, $userTgId)) {
                throw new RuntimeException('Ключ уже используется другим пользователем');
            }

            // Определение панели для активации
            // Проверяем наличие связанных данных
            if (!$key->packSalesman || !$key->packSalesman->salesman) {
                throw new RuntimeException('Не найдена связь ключа с продавцом');
            }

            $panel = $key->packSalesman->salesman->panel_id
                ? $key->packSalesman->salesman->panel
                : $this->panelRepository->getOptimizedMarzbanPanel();

            if (!$panel) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

//            $serverStrategy = new ServerStrategy($panel->server->provider);
//            if (!$serverStrategy->ping($panel->server)) {
//                $this->logger->error('Ошибка активации ключа', [
//                    'key_id' => $key->id,
//                    'user_id' => $userTgId,
//                    'server_id' => $panel->server->id
//                ]);
//                throw new RuntimeException('Сервер не доступен');
//            }else{
//                $this->logger->warning('CЕРВЕР ПРОВЕРЕН И ДОСТУПЕН', [
//                    'key_id' => $key->id,
//                    'user_id' => $userTgId,
//                    'server_id' => $panel->server->id,
//                ]);
//            }

            $finishAt = time() + ($key->packSalesman->pack->period * \App\Constants\TimeConstants::SECONDS_IN_DAY);

            // Создаем стратегию для работы с панелью (используем тип панели из объекта)
            $panelStrategy = new PanelStrategy($panel->panel ?? Panel::MARZBAN);
            // Добавляем пользователя на сервер
            $serverUser = $panelStrategy->addServerUser(
                $panel->id,
                $userTgId,
                $key->traffic_limit,
                $finishAt,
                $key->id,
                ['max_connections' => 3] // ← ДОБАВЛЯЕМ ЛИМИТ
            );

            // Обновляем данные активации
            $activatedKey = $this->keyActivateRepository->updateActivationData(
                $key,
                $userTgId,
                KeyActivate::ACTIVE
            );

            // Логирование
            $this->logger->info('Ключ успешно активирован', [
                'key_id' => $activatedKey->id,
                'user_id' => $userTgId,
                'server_user_id' => $serverUser->id,
                'panel_id' => $panel->id,
                'traffic_limit' => $key->traffic_limit,
                'finish_at' => $key->finish_at
            ]);;

            // Отправляем уведомление продавцу об активации ключа
            $this->notificationService->sendKeyActivatedNotification(
                $key->packSalesman->salesman->telegram_id,
                $key->id
            );

            return $activatedKey;
        } catch (Exception $e) {
            $this->logger->error('Ошибка активации ключа', [
                'key_id' => $key->id,
                'user_id' => $userTgId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new RuntimeException($e->getMessage());
        }
    }


    /**
     * Активация ключа
     *
     * @param KeyActivate $key
     * @param int $userTgId
     * @return KeyActivate
     * @throws RuntimeException|GuzzleException
     */
    public function activate(KeyActivate $key, int $userTgId): KeyActivate
    {
        try {
            // Проверяем текущий статус
            if (!$this->keyActivateRepository->hasCorrectStatusForActivation($key)) {
                throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
            }

            // Проверяем, не истек ли срок для активации
//            if ($this->keyActivateRepository->isActivationPeriodExpired($key)) {
//                throw new RuntimeException('Срок активации ключа истек');
//            }

            // Проверяем, не занят ли уже ключ другим пользователем
            if ($this->keyActivateRepository->isUsedByAnotherUser($key, $userTgId)) {
                throw new RuntimeException('Ключ уже используется другим пользователем');
            }

            // Определяем finish_at
            if (!is_null($key->pack_salesman_id)) {
                $finishAt = time() + ($key->packSalesman->pack->period * \App\Constants\TimeConstants::SECONDS_IN_DAY);
            } else {
                $finishAt = Carbon::now()->addMonth()->startOfMonth()->timestamp;
            }

            // Создаем стратегию для работы с панелью (будет определена после выбора панели)
            $panelStrategy = null;

            // Список ID панелей, которые уже пробовали (чтобы не повторяться)
            $attemptedPanelIds = [];
            $maxAttempts = 10; // Максимум попыток на разных панелях
            $serverUser = null;
            $lastError = null;

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                try {
                    // Выбираем панель
                    if (!is_null($key->pack_salesman_id)) {
                        if (!is_null($key->packSalesman->salesman->panel_id)) {
                            // Если панель привязана к продавцу, используем её (только на первой попытке)
                            if ($attempt === 0) {
                                $panel = $key->packSalesman->salesman->panel;
                            } else {
                                // На последующих попытках используем общий алгоритм
                                $panel = $this->panelRepository->getOptimizedMarzbanPanel();
                            }
                        } else {
                            $panel = $this->panelRepository->getOptimizedMarzbanPanel();
                        }
                    } else {
                        $panel = $this->panelRepository->getOptimizedMarzbanPanel();
                    }

                    if (!$panel) {
                        throw new RuntimeException('Активная панель Marzban не найдена');
                    }

                    // Проверяем, не пытались ли мы уже использовать эту панель
                    if (in_array($panel->id, $attemptedPanelIds)) {
                        // Если все панели уже пробовали, выбрасываем ошибку
                        throw new RuntimeException('Все доступные панели уже были опробованы');
                    }

                    $attemptedPanelIds[] = $panel->id;

                    // Создаем стратегию для работы с выбранной панелью
                    $panelStrategy = new PanelStrategy($panel->panel ?? Panel::MARZBAN);

                    // Добавляем пользователя на сервер
                    $serverUser = $panelStrategy->addServerUser(
                        $panel->id,
                        $userTgId,
                        $key->traffic_limit,
                        $finishAt,
                        $key->id,
                        ['max_connections' => 3]
                    );

                    // Если успешно, выходим из цикла
                    break;

                } catch (Exception $e) {
                    $lastError = $e;

                    // Сразу помечаем панель как имеющую ошибку и убираем из ротации
                    if (isset($panel) && $panel) {
                        $this->panelRepository->markPanelWithError(
                            $panel->id,
                            'Ошибка при создании пользователя: ' . $e->getMessage()
                        );

                        Log::warning('Panel marked with error and removed from rotation', [
                            'panel_id' => $panel->id,
                            'error' => $e->getMessage(),
                            'source' => 'key',
                            'attempt' => $attempt + 1,
                        ]);
                    }

                    // Очищаем кэш выбора панелей, чтобы исключенная панель не выбиралась снова
                    Cache::forget('optimized_marzban_panel_balanced');
                    Cache::forget('optimized_marzban_panel_traffic_based');
                    Cache::forget('optimized_marzban_panel_intelligent');

                    // Продолжаем попытки на другой панели
                    // Если это последняя попытка, выбрасываем исключение
                    if ($attempt === $maxAttempts - 1) {
                        throw new RuntimeException('Не удалось создать пользователя после попыток на ' . count($attemptedPanelIds) . ' панелях. Последняя ошибка: ' . $e->getMessage());
                    }
                }
            }

            if (!$serverUser) {
                $errorMessage = 'Не удалось создать пользователя на панели';
                if ($lastError) {
                    $errorMessage .= ': ' . $lastError->getMessage();
                } elseif (!empty($attemptedPanelIds)) {
                    $errorMessage .= '. Попытки на панелях: ' . implode(', ', $attemptedPanelIds);
                } else {
                    $errorMessage .= '. Нет доступных панелей';
                }
                throw new RuntimeException($errorMessage);
            }

            // Обновляем данные активации
            $activatedKey = $this->keyActivateRepository->updateActivationData(
                $key,
                $userTgId,
                KeyActivate::ACTIVE
            );

            $this->logger->info('Ключ успешно активирован', [
                'source' => 'key_activate',
                'action' => 'activate',
                'key_id' => $activatedKey->id,
                'user_tg_id' => $userTgId,
                'server_user_id' => $serverUser->id,
                'panel_id' => $serverUser->panel_id,
                'traffic_limit' => $key->traffic_limit,
                'finish_at' => $key->finish_at
            ]);

            if (!is_null($key->pack_salesman_id)) {
                // Отправляем уведомление продавцу об активации ключа
                $packSalesman = $this->packSalesmanRepository->findByIdOrFail($key->pack_salesman_id);
                $this->notificationService->sendKeyActivatedNotification($packSalesman->salesman->telegram_id, $key->id);
            }

            return $activatedKey;
        } catch (Exception $e) {
            $this->logger->error('Ошибка при активации ключа', [
                'source' => 'key_activate',
                'action' => 'activate',
                'key_id' => $key->id,
                'user_tg_id' => $userTgId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Активация ключа с указанным finish_at (для перевыпуска с учетом оставшегося времени)
     *
     * @param KeyActivate $key
     * @param int $userTgId
     * @param int $finishAt Unix timestamp даты окончания
     * @return KeyActivate
     * @throws RuntimeException|GuzzleException
     */
    public function activateWithFinishAt(KeyActivate $key, int $userTgId, int $finishAt): KeyActivate
    {
        try {
            // Проверяем текущий статус
            if (!$this->keyActivateRepository->hasCorrectStatusForActivation($key)) {
                throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
            }

            // Проверяем, не занят ли уже ключ другим пользователем
            if ($this->keyActivateRepository->isUsedByAnotherUser($key, $userTgId)) {
                throw new RuntimeException('Ключ уже используется другим пользователем');
            }

            // Создаем стратегию для работы с панелью (будет определена после выбора панели)
            $panelStrategy = null;

            // Список ID панелей, которые уже пробовали (чтобы не повторяться)
            $attemptedPanelIds = [];
            $maxAttempts = 10; // Максимум попыток на разных панелях
            $serverUser = null;
            $lastError = null;

            for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
                try {
                    // Выбираем панель
                    if (!is_null($key->pack_salesman_id)) {
                        if (!is_null($key->packSalesman->salesman->panel_id)) {
                            // Если панель привязана к продавцу, используем её (только на первой попытке)
                            if ($attempt === 0) {
                                $panel = $key->packSalesman->salesman->panel;
                            } else {
                                // На последующих попытках используем общий алгоритм
                                $panel = $this->panelRepository->getOptimizedMarzbanPanel();
                            }
                        } else {
                            $panel = $this->panelRepository->getOptimizedMarzbanPanel();
                        }
                    } else {
                        $panel = $this->panelRepository->getOptimizedMarzbanPanel();
                    }

                    if (!$panel) {
                        throw new RuntimeException('Активная панель Marzban не найдена');
                    }

                    // Проверяем, не пытались ли мы уже использовать эту панель
                    if (in_array($panel->id, $attemptedPanelIds)) {
                        // Если все панели уже пробовали, выбрасываем ошибку
                        throw new RuntimeException('Все доступные панели уже были опробованы');
                    }

                    $attemptedPanelIds[] = $panel->id;

                    // Создаем стратегию для работы с выбранной панелью
                    $panelStrategy = new PanelStrategy($panel->panel ?? Panel::MARZBAN);

                    // Используем переданный finish_at вместо пересчета
                    // Добавляем пользователя на сервер
                    $serverUser = $panelStrategy->addServerUser(
                        $panel->id,
                        $userTgId,
                        $key->traffic_limit,
                        $finishAt,
                        $key->id,
                        ['max_connections' => 3]
                    );

                    // Если успешно, выходим из цикла
                    break;

                } catch (Exception $e) {
                    $lastError = $e;

                    // Сразу помечаем панель как имеющую ошибку и убираем из ротации
                    if (isset($panel) && $panel) {
                        $this->panelRepository->markPanelWithError(
                            $panel->id,
                            'Ошибка при создании пользователя: ' . $e->getMessage()
                        );

                        Log::warning('Panel marked with error and removed from rotation', [
                            'panel_id' => $panel->id,
                            'error' => $e->getMessage(),
                            'source' => 'key',
                            'attempt' => $attempt + 1,
                        ]);
                    }

                    // Очищаем кэш выбора панелей, чтобы исключенная панель не выбиралась снова
                    Cache::forget('optimized_marzban_panel_balanced');
                    Cache::forget('optimized_marzban_panel_traffic_based');
                    Cache::forget('optimized_marzban_panel_intelligent');

                    // Продолжаем попытки на другой панели
                    // Если это последняя попытка, выбрасываем исключение
                    if ($attempt === $maxAttempts - 1) {
                        throw new RuntimeException('Не удалось создать пользователя после попыток на ' . count($attemptedPanelIds) . ' панелях. Последняя ошибка: ' . $e->getMessage());
                    }
                }
            }

            if (!$serverUser) {
                $errorMessage = 'Не удалось создать пользователя на панели';
                if ($lastError) {
                    $errorMessage .= ': ' . $lastError->getMessage();
                } elseif (!empty($attemptedPanelIds)) {
                    $errorMessage .= '. Попытки на панелях: ' . implode(', ', $attemptedPanelIds);
                } else {
                    $errorMessage .= '. Нет доступных панелей';
                }
                throw new RuntimeException($errorMessage);
            }

            // Обновляем данные активации
            $activatedKey = $this->keyActivateRepository->updateActivationData(
                $key,
                $userTgId,
                KeyActivate::ACTIVE
            );

            // Обновляем finish_at в ключе
            $activatedKey->finish_at = $finishAt;
            $activatedKey->save();

            $this->logger->info('Ключ успешно активирован с указанным finish_at', [
                'source' => 'key_activate',
                'action' => 'activate_with_finish_at',
                'key_id' => $activatedKey->id,
                'user_tg_id' => $userTgId,
                'server_user_id' => $serverUser->id,
                'traffic_limit' => $key->traffic_limit,
                'finish_at' => $finishAt
            ]);

            if (!is_null($key->pack_salesman_id)) {
                $packSalesman = $this->packSalesmanRepository->findByIdOrFail($key->pack_salesman_id);
                $this->notificationService->sendKeyActivatedNotification($packSalesman->salesman->telegram_id, $key->id);
            }

            return $activatedKey;
        } catch (Exception $e) {
            $this->logger->error('Ошибка при активации ключа с finish_at', [
                'source' => 'key_activate',
                'action' => 'activate_with_finish_at',
                'key_id' => $key->id,
                'user_tg_id' => $userTgId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Проверка и обновление статуса ключа
     *
     * @param KeyActivate $key
     * @return KeyActivate
     */
    public function checkAndUpdateStatus(KeyActivate $key): KeyActivate
    {
        $originalStatus = $key->status;
        $currentTime = time();

        try {
            $statusChanged = false;

            // Проверяем срок активации для оплаченных ключей (deleted_at)
            if ($key->status === KeyActivate::PAID && $key->deleted_at && $currentTime > $key->deleted_at) {
                $key->status = KeyActivate::EXPIRED;
                $statusChanged = true;

                $this->logger->info('Статус ключа обновлен на EXPIRED (истек срок активации для оплаченного ключа)', [
                    'source' => 'key_activate',
                    'action' => 'update_status',
                    'key_id' => $key->id,
                    'old_status' => $originalStatus,
                    'new_status' => $key->status,
                    'deleted_at' => $key->deleted_at,
                    'current_time' => $currentTime
                ]);
            }

            // Проверяем срок действия для активных ключей (finish_at)
            if ($key->status === KeyActivate::ACTIVE && $key->finish_at && $currentTime > $key->finish_at) {
                $key->status = KeyActivate::EXPIRED;
                $statusChanged = true;

                $this->logger->info('Статус ключа обновлен на EXPIRED (истек срок действия активного ключа)', [
                    'source' => 'key_activate',
                    'action' => 'update_status',
                    'key_id' => $key->id,
                    'old_status' => $originalStatus,
                    'new_status' => $key->status,
                    'finish_at' => $key->finish_at,
                    'current_time' => $currentTime
                ]);
            }

            // Сохраняем только если статус изменился
            if ($statusChanged) {
                $key->save();

                // Отправляем уведомление продавцу о деактивации ключа
//                if ($key->pack_salesman_id) {
//                    $packSalesman = $this->packSalesmanRepository->findByIdOrFail($key->pack_salesman_id);
//                    $this->notificationService->sendKeyDeactivatedNotification($packSalesman->salesman->telegram_id, $key->id);
//                }
            } else {
                $this->logger->debug('Статус ключа не требует обновления', [
                    'source' => 'key_activate',
                    'action' => 'check_status',
                    'key_id' => $key->id,
                    'status' => $key->status,
                    'finish_at' => $key->finish_at,
                    'deleted_at' => $key->deleted_at,
                    'current_time' => $currentTime
                ]);
            }

            return $key;
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении статуса ключа', [
                'source' => 'key_activate',
                'action' => 'update_status',
                'key_id' => $key->id,
                'old_status' => $originalStatus,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException('Ошибка при обновлении статуса ключа: ' . $e->getMessage());
        }
    }

    /**
     * Перевыпуск просроченного ключа
     * Создает нового пользователя сервера с теми же параметрами и возвращает ключ в статус ACTIVE
     *
     * @param KeyActivate $key
     * @return KeyActivate
     * @throws RuntimeException|GuzzleException
     */
    public function renew(KeyActivate $key): KeyActivate
    {
        try {
            $this->logger->info('renew() начало выполнения', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $key->id,
                'status' => $key->status
            ]);

            // Проверяем, что ключ просрочен
            if ($key->status !== KeyActivate::EXPIRED) {
                throw new RuntimeException('Ключ не может быть перевыпущен. Только просроченные ключи могут быть перевыпущены.');
            }

            // Проверяем, что есть user_tg_id
            if (!$key->user_tg_id) {
                throw new RuntimeException('Нельзя перевыпустить ключ без привязки к пользователю Telegram');
            }

            $this->logger->info('renew() загрузка связей', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $key->id
            ]);

            // Загружаем связи
            $key->load(['keyActivateUser.serverUser.panel', 'packSalesman.salesman']);

            // Удаляем старого пользователя сервера, если он существует
            if ($key->keyActivateUser && $key->keyActivateUser->serverUser) {
                $oldServerUser = $key->keyActivateUser->serverUser;
                $oldPanel = $oldServerUser->panel;

                if ($oldPanel) {
                    try {
                        $panelStrategy = new PanelStrategy($oldPanel->panel ?? Panel::MARZBAN);
                        $panelStrategy->deleteServerUser($oldPanel->id, $oldServerUser->id);

                        $this->logger->info('Старый пользователь сервера удален при перевыпуске', [
                            'source' => 'key_activate',
                            'action' => 'renew',
                            'key_id' => $key->id,
                            'old_server_user_id' => $oldServerUser->id,
                            'panel_id' => $oldPanel->id
                        ]);
                    } catch (Exception $e) {
                        $this->logger->warning('Ошибка при удалении старого пользователя сервера (продолжаем перевыпуск)', [
                            'source' => 'key_activate',
                            'action' => 'renew',
                            'key_id' => $key->id,
                            'old_server_user_id' => $oldServerUser->id,
                            'error' => $e->getMessage()
                        ]);
                        // Не прерываем процесс, если не удалось удалить старого пользователя
                    }
                }
            }

            $this->logger->info('renew() определение панели', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $key->id
            ]);

            // Определяем панель для создания нового пользователя
            $panel = null;
            if ($key->packSalesman && $key->packSalesman->salesman && $key->packSalesman->salesman->panel_id) {
                $panel = $key->packSalesman->salesman->panel;
                $this->logger->info('renew() используется панель продавца', [
                    'source' => 'key_activate',
                    'action' => 'renew',
                    'key_id' => $key->id,
                    'panel_id' => $panel->id
                ]);
            } else {
                $panel = $this->panelRepository->getOptimizedMarzbanPanel();
                $this->logger->info('renew() используется оптимизированная панель', [
                    'source' => 'key_activate',
                    'action' => 'renew',
                    'key_id' => $key->id,
                    'panel_id' => $panel ? $panel->id : null
                ]);
            }

            if (!$panel) {
                $this->logger->error('renew() активная панель не найдена', [
                    'source' => 'key_activate',
                    'action' => 'renew',
                    'key_id' => $key->id
                ]);
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            // Используем существующие параметры ключа
            $trafficLimit = $key->traffic_limit ?? 0;
            $finishAt = $key->finish_at;

            // Если finish_at не установлен, устанавливаем его на основе периода пакета
            if (!$finishAt && $key->packSalesman && $key->packSalesman->pack) {
                $finishAt = time() + ($key->packSalesman->pack->period * \App\Constants\TimeConstants::SECONDS_IN_DAY);
            } elseif (!$finishAt) {
                // Если нет пакета, устанавливаем на месяц вперед
                $finishAt = Carbon::now()->addMonth()->startOfMonth()->timestamp;
            }

            $this->logger->info('renew() создание пользователя на панели', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $key->id,
                'panel_id' => $panel->id,
                'traffic_limit' => $trafficLimit,
                'finish_at' => $finishAt
            ]);

            // Создаем стратегию для работы с панелью
            $panelStrategy = new PanelStrategy($panel->panel ?? Panel::MARZBAN);

            // Создаем нового пользователя на сервере с теми же параметрами
            try {
                $serverUser = $panelStrategy->addServerUser(
                    $panel->id,
                    $key->user_tg_id,
                    $trafficLimit,
                    $finishAt,
                    $key->id,
                    ['max_connections' => 3]
                );

                $this->logger->info('renew() пользователь создан на панели', [
                    'source' => 'key_activate',
                    'action' => 'renew',
                    'key_id' => $key->id,
                    'server_user_id' => $serverUser->id
                ]);
            } catch (\Throwable $e) {
                $this->logger->error('renew() ошибка создания пользователя на панели', [
                    'source' => 'key_activate',
                    'action' => 'renew',
                    'key_id' => $key->id,
                    'panel_id' => $panel->id,
                    'error' => $e->getMessage(),
                    'error_class' => get_class($e)
                ]);
                throw $e;
            }

            // Обновляем данные активации - возвращаем ключ в статус ACTIVE
            $activatedKey = $this->keyActivateRepository->updateActivationData(
                $key,
                $key->user_tg_id,
                KeyActivate::ACTIVE
            );

            // Обновляем finish_at в ключе (на случай, если он был пересчитан)
            $activatedKey->finish_at = $finishAt;
            $activatedKey->save();

            $this->logger->info('Ключ успешно перевыпущен', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $activatedKey->id,
                'user_tg_id' => $key->user_tg_id,
                'server_user_id' => $serverUser->id,
                'panel_id' => $serverUser->panel_id,
                'traffic_limit' => $trafficLimit,
                'finish_at' => $finishAt
            ]);

            // Отправляем уведомление продавцу о перевыпуске ключа
            if ($key->pack_salesman_id) {
                $packSalesman = $this->packSalesmanRepository->findByIdOrFail($key->pack_salesman_id);
                $this->notificationService->sendKeyActivatedNotification($packSalesman->salesman->telegram_id, $key->id);
            }

            return $activatedKey;
        } catch (\Throwable $e) {
            $errorMessage = $e->getMessage();
            $errorClass = get_class($e);

            $this->logger->error('Ошибка при перевыпуске ключа (catch в renew)', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $key->id,
                'error' => $errorMessage,
                'error_class' => $errorClass,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            // Формируем понятное сообщение об ошибке
            $detailedMessage = 'Ошибка при перевыпуске ключа';
            if (!empty($errorMessage)) {
                $detailedMessage .= ': ' . $errorMessage;
            } else {
                $detailedMessage .= ' (тип: ' . $errorClass . ')';
            }

            throw new RuntimeException($detailedMessage);
        }
    }

    /**
     * Get paginated key activates with pack relations and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithPack(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        try {
            $this->logger->info('Getting paginated key activates with filters', [
                'source' => 'key_activate',
                'action' => 'get_paginated',
                'filters' => $filters
            ]);

            return $this->keyActivateRepository->getPaginatedWithPack($filters, $perPage);
        } catch (Exception $e) {
            $this->logger->error('Failed to get paginated key activates', [
                'source' => 'key_activate',
                'action' => 'get_paginated',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Failed to get key activates: {$e->getMessage()}");
        }
    }
}
