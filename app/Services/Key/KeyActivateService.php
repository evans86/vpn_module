<?php

namespace App\Services\Key;

use App\Dto\Bot\BotModuleDto;
use App\Helpers\OrderHelper;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
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
                1 => 2462416,  // 1 месяц
                3 => 2462423,  // 3 месяца
                6 => 2462928,  // 6 месяцев
                12 => 2462929 // 12 месяцев
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

            return $this->keyActivateRepository->findById($keyID);
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
            if ($this->keyActivateRepository->isActivationPeriodExpired($key)) {
                throw new RuntimeException('Срок активации ключа истек');
            }

            // Проверяем, не занят ли уже ключ другим пользователем
            if ($this->keyActivateRepository->isUsedByAnotherUser($key, $userTgId)) {
                throw new RuntimeException('Ключ уже используется другим пользователем');
            }

            // Определение панели для активации
            $panel = $key->packSalesman->salesman->panel_id
                ? $key->packSalesman->salesman->panel
                : $this->panelRepository->getConfiguredMarzbanPanel();

            if (!$panel) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            $serverStrategy = new ServerStrategy($panel->server->provider);
            if (!$serverStrategy->ping($panel->server)) {
                $this->logger->error('Ошибка активации ключа', [
                    'key_id' => $key->id,
                    'user_id' => $userTgId,
                    'server_id' => $panel->server->id
                ]);
                throw new RuntimeException('Сервер не доступен');
            }else{
                $this->logger->warning('CЕРВЕР ПРОВЕРЕН И ДОСТУПЕН', [
                    'key_id' => $key->id,
                    'user_id' => $userTgId,
                    'server_id' => $panel->server->id,
                ]);
            }

            $finishAt = time() + ($key->packSalesman->pack->period * 24 * 60 * 60);

            // Создаем стратегию для работы с панелью
            $panelStrategy = new PanelStrategy(Panel::MARZBAN);
            // Добавляем пользователя на сервер
            $serverUser = $panelStrategy->addServerUser(
                $panel->id,
                $userTgId,
                $key->traffic_limit,
                $finishAt,
                $key->id
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
            if ($this->keyActivateRepository->isActivationPeriodExpired($key)) {
                throw new RuntimeException('Срок активации ключа истек');
            }

            // Проверяем, не занят ли уже ключ другим пользователем
            if ($this->keyActivateRepository->isUsedByAnotherUser($key, $userTgId)) {
                throw new RuntimeException('Ключ уже используется другим пользователем');
            }

            if (!is_null($key->pack_salesman_id)) {
                if (!is_null($key->packSalesman->salesman->panel_id)) {
                    // Получаем активную панель Marzban, привязанную к продавцу
                    $panel = $key->packSalesman->salesman->panel;
                } else {
                    // Получаем активную панель Marzban по алгоритму
                    $panel = $this->panelRepository->getConfiguredMarzbanPanel();
                }
            } else {
                $panel = $this->panelRepository->getConfiguredMarzbanPanel();
            }

            if (!$panel) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            // Создаем стратегию для работы с панелью
            $panelStrategy = new PanelStrategy(Panel::MARZBAN);

            if (!is_null($key->pack_salesman_id)) {
                $finishAt = time() + ($key->packSalesman->pack->period * 24 * 60 * 60);
            } else {
                $finishAt = Carbon::now()->addMonth()->startOfMonth()->timestamp;
            }

            // Добавляем пользователя на сервер
            $serverUser = $panelStrategy->addServerUser(
                $panel->id,
                $userTgId,
                $key->traffic_limit,
                $finishAt,
                $key->id
            );

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
                'panel_id' => 1,
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
     * Проверка и обновление статуса ключа
     *
     * @param KeyActivate $key
     * @return KeyActivate
     */
    public function checkAndUpdateStatus(KeyActivate $key): KeyActivate
    {
        $originalStatus = $key->status;

        try {
            // Проверяем срок активации для оплаченных ключей

            $key->status = KeyActivate::EXPIRED;
            $key->save();

            // Отправляем уведомление продавцу о деактивации ключа
            if ($key->pack_salesman_id) {
                $packSalesman = $this->packSalesmanRepository->findByIdOrFail($key->pack_salesman_id);
                $this->notificationService->sendKeyDeactivatedNotification($packSalesman->salesman->telegram_id, $key->id);
            }

            $this->logger->info('Статус ключа обновлен на EXPIRED (истек срок активации)', [
                'source' => 'key_activate',
                'action' => 'update_status',
                'key_id' => $key->id,
                'old_status' => $originalStatus,
                'new_status' => $key->status,
                'deleted_at' => $key->deleted_at
            ]);

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
