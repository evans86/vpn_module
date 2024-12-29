<?php

namespace App\Services\Key;

use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Panel\PanelRepository;
use App\Logging\DatabaseLogger;
use App\Services\Panel\PanelStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use RuntimeException;
use Exception;

class KeyActivateService
{
    private KeyActivateRepository $keyActivateRepository;
    private PackSalesmanRepository $packSalesmanRepository;
    private DatabaseLogger $logger;
    private PanelRepository $panelRepository;

    public function __construct(
        KeyActivateRepository  $keyActivateRepository,
        PackSalesmanRepository $packSalesmanRepository,
        PanelRepository       $panelRepository,
        DatabaseLogger        $logger
    )
    {
        $this->keyActivateRepository = $keyActivateRepository;
        $this->packSalesmanRepository = $packSalesmanRepository;
        $this->panelRepository = $panelRepository;
        $this->logger = $logger;
    }

    /**
     * Создание ключа
     *
     * @param int|null $traffic_limit
     * @param int $pack_salesman_id
     * @param int $finish_at
     * @param int $deleted_at
     * @return KeyActivate
     * @throws Exception
     */
    public function create(?int $traffic_limit, int $pack_salesman_id, int $finish_at, int $deleted_at): KeyActivate
    {
        try {
            $packSalesman = $this->packSalesmanRepository->findByIdOrFail($pack_salesman_id);

            $keyData = [
                'id' => Str::uuid()->toString(),
                'traffic_limit' => $traffic_limit,
                'pack_salesman_id' => $packSalesman->id,
                'finish_at' => $finish_at,
                'deleted_at' => $deleted_at,
                'status' => KeyActivate::PAID
            ];

            $keyActivate = $this->keyActivateRepository->createKey($keyData);

            $this->logger->info('Ключ успешно создан', [
                'source' => 'key_activate',
                'action' => 'create',
                'key_id' => $keyActivate->id,
                'pack_salesman_id' => $packSalesman->id,
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

//@TODO Не работает панель

            // Получаем активную панель Marzban
            $panel = $this->panelRepository->getConfiguredMarzbanPanel();

            if (!$panel) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            // Создаем стратегию для работы с панелью
            $this->panelStrategy = new PanelStrategy(Panel::MARZBAN);

            // Добавляем пользователя на сервер
            $serverUser = $this->panelStrategy->addServerUser(
                $panel->id,
                $key->traffic_limit,
                $key->finish_at,
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
                'server_user_id' => $serverUser->id, //@TODO Не работает панель
//                'server_user_id' => 123,
                'panel_id' => 1,
                'traffic_limit' => $key->traffic_limit,
                'finish_at' => $key->finish_at
            ]);

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
            // Проверяем срок действия для активных ключей
            if ($key->status === KeyActivate::ACTIVE && $this->keyActivateRepository->isExpiredByTime($key)) {
                $key = $this->keyActivateRepository->updateStatus($key, KeyActivate::EXPIRED);

                $this->logger->info('Статус ключа обновлен на EXPIRED (истек срок действия)', [
                    'source' => 'key_activate',
                    'action' => 'update_status',
                    'key_id' => $key->id,
                    'old_status' => $originalStatus,
                    'new_status' => $key->status,
                    'finish_at' => $key->finish_at
                ]);
            }

            // Проверяем срок активации для оплаченных ключей
            if ($key->status === KeyActivate::PAID && $this->keyActivateRepository->isActivationPeriodExpired($key)) {
                $key = $this->keyActivateRepository->updateStatus($key, KeyActivate::EXPIRED);

                $this->logger->info('Статус ключа обновлен на EXPIRED (истек срок активации)', [
                    'source' => 'key_activate',
                    'action' => 'update_status',
                    'key_id' => $key->id,
                    'old_status' => $originalStatus,
                    'new_status' => $key->status,
                    'deleted_at' => $key->deleted_at
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
     * Get paginated key activates with pack relations and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getPaginatedWithPack(array $filters = [], int $perPage = 10)
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
