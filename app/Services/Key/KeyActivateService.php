<?php

namespace App\Services\Key;

use App\Dto\Bot\BotModuleDto;
use App\Helpers\OrderHelper;
use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Panel\Panel;
use App\Models\Salesman\Salesman;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Panel\PanelRepository;
use App\Logging\DatabaseLogger;
use App\Models\Log\ApplicationLog;
use App\Services\External\BottApi;
use App\Services\Panel\PanelStrategy;
use App\Services\Notification\NotificationService;
use App\Services\Server\ServerStrategy;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use DomainException;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
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
     * Список панелей для активации: либо одна (старый режим), либо по одной на провайдера из multi_provider_slots.
     *
     * @return Panel[]
     */
    private function getPanelsForActivation(KeyActivate $key, bool $useCacheOnly = true): array
    {
        $slots = config('panel.multi_provider_slots', []);
        $slots = is_array($slots) ? $slots : [];

        if (empty($slots)) {
            $panel = null;
            if ($key->packSalesman && $key->packSalesman->salesman && $key->packSalesman->salesman->panel_id) {
                $panel = $key->packSalesman->salesman->panel;
            }
            if (!$panel) {
                $panel = $this->panelRepository->getOptimizedMarzbanPanel(null, $useCacheOnly);
            }
            return $panel ? [$panel] : [];
        }

        $panels = [];
        $salesmanPanel = ($key->packSalesman && $key->packSalesman->salesman && $key->packSalesman->salesman->panel_id)
            ? $key->packSalesman->salesman->panel
            : null;
        $salesmanProvider = $salesmanPanel && $salesmanPanel->server ? $salesmanPanel->server->provider : null;

        $providersToResolve = [];
        foreach ($slots as $provider) {
            $provider = (string) $provider;
            if (! ($salesmanProvider === $provider && $salesmanPanel)) {
                $providersToResolve[] = $provider;
            }
        }
        $providersToResolve = array_values(array_unique($providersToResolve));
        $fetchedByProvider = $providersToResolve !== []
            ? $this->panelRepository->getOptimizedMarzbanPanelsForProviders($providersToResolve, null, $useCacheOnly)
            : [];

        foreach ($slots as $provider) {
            $provider = (string) $provider;
            $panel = null;
            if ($salesmanProvider === $provider && $salesmanPanel) {
                $panel = $salesmanPanel;
            }
            if (! $panel) {
                $panel = $fetchedByProvider[$provider] ?? null;
            }
            if ($panel && !isset($panels[$panel->id])) {
                $panels[$panel->id] = $panel;
            }
        }
        $list = array_values($panels);
        $maxSlots = (int) config('panel.max_provider_slots', 3);
        if ($maxSlots > 0 && count($list) > $maxSlots) {
            $list = array_slice($list, 0, $maxSlots);
        }
        return $list;
    }

    /**
     * Добавить недостающие слоты провайдеров к уже активному ключу (миграция на мульти-провайдер).
     * Не трогает ключи без user_tg_id или без ни одного KeyActivateUser.
     *
     * @param KeyActivate $key Активный ключ (должен быть status ACTIVE, user_tg_id и хотя бы один слот)
     * @param bool $dryRun Если true — только возвращает, сколько слотов было бы добавлено, без создания
     * @return int Количество добавленных слотов (или при dryRun — сколько бы добавили)
     */
    public function addMissingProviderSlots(KeyActivate $key, bool $dryRun = false): int
    {
        $maxSlots = (int) config('panel.max_provider_slots', 3);
        $slots = config('panel.multi_provider_slots', []);
        $slots = is_array($slots) ? $slots : [];
        if (empty($slots)) {
            return 0;
        }

        if (!$key->user_tg_id) {
            Log::warning('addMissingProviderSlots: key has no user_tg_id', ['key_id' => $key->id]);
            return 0;
        }

        $key->load(['keyActivateUsers.serverUser.panel.server']);
        if ($key->keyActivateUsers->isEmpty()) {
            Log::warning('addMissingProviderSlots: key has no KeyActivateUser (no slots)', ['key_id' => $key->id]);
            return 0;
        }

        // Не добавляем слотов сверх лимита (у всех ключей макс. max_provider_slots)
        if ($maxSlots > 0 && $key->keyActivateUsers->count() >= $maxSlots) {
            return 0;
        }

        // Нормализуем провайдер для сравнения (VDSINA vs vdsina), и запоминаем занятые panel_id
        $existingProviders = [];
        $existingPanelIds = [];
        foreach ($key->keyActivateUsers as $kau) {
            if ($kau->serverUser && $kau->serverUser->panel && $kau->serverUser->panel->server) {
                $p = $kau->serverUser->panel->server->provider;
                if ($p !== null && $p !== '') {
                    $existingProviders[strtolower(trim((string) $p))] = true;
                }
                $existingPanelIds[$kau->serverUser->panel_id] = true;
            }
        }

        $wouldAdd = 0;
        foreach ($slots as $provider) {
            $provider = trim((string) $provider);
            if ($provider === '') {
                continue;
            }
            $providerKey = strtolower($provider);
            if (isset($existingProviders[$providerKey])) {
                continue;
            }
            $panel = $this->panelRepository->getOptimizedMarzbanPanelForProvider($provider, null, true);
            if (!$panel) {
                Log::warning('addMissingProviderSlots: no panel for provider', [
                    'key_id' => $key->id,
                    'provider' => $provider,
                    'source' => 'key_activate',
                ]);
                continue;
            }
            // Уже есть слот на эту панель (другой провайдер в конфиге мог указать на ту же панель)
            if (isset($existingPanelIds[$panel->id])) {
                $existingProviders[$providerKey] = true;
                continue;
            }
            // Не превышаем лимит слотов на ключ
            $currentCount = count($existingPanelIds) + $wouldAdd;
            if ($maxSlots > 0 && $currentCount >= $maxSlots) {
                break;
            }
            if ($dryRun) {
                $wouldAdd++;
                continue;
            }
            $expire = (int) ($key->finish_at ?? 0);
            if ($expire <= time()) {
                $expire = time() + 86400;
            }
            $dataLimit = (int) ($key->traffic_limit ?? 0);
            $userTgId = (int) $key->user_tg_id;
            try {
                $panelStrategy = new PanelStrategy($panel->panel ?? Panel::MARZBAN);
                $panelStrategy->addServerUser(
                    $panel->id,
                    $userTgId,
                    $dataLimit,
                    $expire,
                    $key->id,
                    ['max_connections' => config('panel.max_connections', 4)]
                );
                $wouldAdd++;
                $existingProviders[$providerKey] = true;
                $existingPanelIds[$panel->id] = true;
            } catch (Exception $e) {
                Log::error('addMissingProviderSlots: failed to add slot', [
                    'key_id' => $key->id,
                    'provider' => $provider,
                    'error' => $e->getMessage(),
                    'source' => 'key_activate',
                ]);
            }
        }
        return $wouldAdd;
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
            }

            $keyID = $order['data']['product']['data'];

            BottApi::createOrder($botModuleDto, $userData, $key_price_kopecks,
                'Покупка VPN доступа: ' . $keyID);

            $salesman = Salesman::query()->where('module_bot_id', $botModuleDto->id)->first();

            $keyActivate = $this->keyActivateRepository->findById($keyID);

            if (!$keyActivate) {
                throw new RuntimeException("Key activate with ID {$keyID} not found");
            }

            if ($salesman) {
                $keyActivate->module_salesman_id = $salesman->id;
                $keyActivate->save();
            }

            $this->logger->info('Покупка ключа', [
                'source' => 'key_activate',
                'action' => 'buy_key',
                'key_id' => $keyActivate->id,
                'user_tg_id' => $userData['user_tg_id'] ?? null,
                'product_id' => $product_id,
                'module_bot_id' => $botModuleDto->id,
            ]);

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
            if (!$key->packSalesman || !$key->packSalesman->salesman) {
                throw new RuntimeException('Не найдена связь ключа с продавцом');
            }

            $keyId = (string) $key->id;
            if (!$this->keyActivateRepository->tryClaimActivation($keyId, $userTgId)) {
                return $this->resolveActivationWithoutClaim($keyId, $userTgId);
            }

            /** @var KeyActivate $keyLocked */
            $keyLocked = KeyActivate::with(['packSalesman.pack'])->findOrFail($keyId);

            return $this->runActivationAfterClaim($keyLocked, $userTgId, null, 'activate_module_key');
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
     * Активация ключа (HTTP к Marzban вне долгой транзакции с FOR UPDATE — без lock wait timeout).
     *
     * @param KeyActivate $key
     * @param int $userTgId
     * @return KeyActivate
     * @throws RuntimeException|GuzzleException
     */
    public function activate(KeyActivate $key, int $userTgId): KeyActivate
    {
        try {
            $keyId = (string) $key->id;
            if (!$this->keyActivateRepository->tryClaimActivation($keyId, $userTgId)) {
                return $this->resolveActivationWithoutClaim($keyId, $userTgId);
            }

            $this->logger->info('Активация ключа: статус PAID→ACTIVATING (резервация успешна)', [
                'source' => 'key_activate',
                'action' => 'activation_claimed',
                'key_id' => $keyId,
                'user_tg_id' => $userTgId,
            ]);

            /** @var KeyActivate $keyLocked */
            $keyLocked = KeyActivate::with(['packSalesman.pack'])->findOrFail($keyId);

            return $this->runActivationAfterClaim($keyLocked, $userTgId, null, 'activate');
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
            $keyId = (string) $key->id;
            if (!$this->keyActivateRepository->tryClaimActivation($keyId, $userTgId)) {
                return $this->resolveActivationWithoutClaim($keyId, $userTgId);
            }

            /** @var KeyActivate $keyLocked */
            $keyLocked = KeyActivate::with(['packSalesman.pack'])->findOrFail($keyId);

            return $this->runActivationAfterClaim($keyLocked, $userTgId, $finishAt, 'activate_with_finish_at');
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
     * Повтор запроса: уже ACTIVE, «зависшая» активация с созданными слотами, или конфликт.
     */
    private function resolveActivationWithoutClaim(string $keyId, int $userTgId): KeyActivate
    {
        /** @var KeyActivate|null $existing */
        $existing = KeyActivate::with(['packSalesman.pack', 'keyActivateUsers'])->find($keyId);
        if (!$existing instanceof KeyActivate) {
            throw new RuntimeException('Ключ не найден');
        }

        if ($existing->status === KeyActivate::ACTIVE && (int) $existing->user_tg_id === $userTgId) {
            return $existing;
        }

        if ($existing->status === KeyActivate::ACTIVATING
            && (int) $existing->user_tg_id === $userTgId
            && $existing->keyActivateUsers()->exists()) {
            return $this->finalizeStuckActivation($existing, $userTgId);
        }

        if ($existing->status === KeyActivate::ACTIVATING && (int) $existing->user_tg_id === $userTgId) {
            throw new RuntimeException('Ключ уже активируется, подождите несколько секунд');
        }

        if ($this->keyActivateRepository->isUsedByAnotherUser($existing, $userTgId)) {
            throw new RuntimeException('Ключ уже используется другим пользователем');
        }

        if (!$this->keyActivateRepository->hasCorrectStatusForActivation($existing) && $existing->status !== KeyActivate::ACTIVATING) {
            throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
        }

        throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
    }

    /**
     * Marzban успел создать пользователей, но статус в БД остался ACTIVATING — доводим до ACTIVE.
     */
    private function finalizeStuckActivation(KeyActivate $key, int $userTgId): KeyActivate
    {
        /** @var KeyActivate $activated */
        $activated = DB::transaction(function () use ($key, $userTgId) {
            /** @var KeyActivate $locked */
            $locked = KeyActivate::with(['packSalesman.pack'])->where('id', $key->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === KeyActivate::ACTIVE && (int) $locked->user_tg_id === $userTgId) {
                return $locked;
            }

            if ($locked->status !== KeyActivate::ACTIVATING || (int) $locked->user_tg_id !== $userTgId) {
                throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
            }

            if (!$locked->keyActivateUsers()->exists()) {
                throw new RuntimeException('Ключ уже активируется, подождите несколько секунд');
            }

            return $this->keyActivateRepository->updateActivationData($locked, $userTgId, KeyActivate::ACTIVE);
        });

        $this->logger->info('Ключ успешно активирован', [
            'source' => 'key_activate',
            'action' => 'finalize_stuck_activation',
            'key_id' => $activated->id,
            'user_tg_id' => $userTgId,
        ]);

        if (!is_null($activated->pack_salesman_id)) {
            $packSalesman = $this->packSalesmanRepository->findByIdOrFail($activated->pack_salesman_id);
            $this->notificationService->sendKeyActivatedNotification($packSalesman->salesman->telegram_id, $activated->id);
        }

        $this->scheduleWarmConfigAfterActivation((string) $activated->id);

        return $activated;
    }

    /**
     * @param KeyActivate $keyLocked уже ACTIVATING с корректным user_tg_id
     * @param int|null $finishAtOverride если задан — используется вместо расчёта по пакету (activateWithFinishAt)
     * @param string $logAction метка для логов
     */
    private function runActivationAfterClaim(
        KeyActivate $keyLocked,
        int $userTgId,
        ?int $finishAtOverride,
        string $logAction
    ): KeyActivate {
        $keyId = (string) $keyLocked->id;
        $serverUsers = [];

        try {
            if ($keyLocked->status !== KeyActivate::ACTIVATING || (int) $keyLocked->user_tg_id !== $userTgId) {
                $this->keyActivateRepository->releaseActivationClaim($keyId);
                throw new RuntimeException('Не удалось зарезервировать ключ для активации');
            }

            if ($finishAtOverride !== null) {
                $finishAt = $finishAtOverride;
            } elseif (!is_null($keyLocked->pack_salesman_id)) {
                $finishAt = time() + ($keyLocked->packSalesman->pack->period * \App\Constants\TimeConstants::SECONDS_IN_DAY);
            } else {
                $finishAt = Carbon::now()->addMonth()->startOfMonth()->timestamp;
            }

            $tSelectionStart = microtime(true);
            $this->logger->info('Активация ключа: подбор панелей Marzban (старт, до создания пользователей)', [
                'source' => 'key_activate',
                'action' => 'panel_selection_start',
                'key_id' => $keyId,
                'user_tg_id' => $userTgId,
                'panel_selection_strategy' => config('panel.selection_strategy'),
            ]);

            $panels = $this->getPanelsForActivation($keyLocked, true);
            $tSelectionEnd = microtime(true);
            if (empty($panels)) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            $this->logger->info('Начало активации ключа', [
                'source' => 'key_activate',
                'key_id' => $keyId,
                'user_tg_id' => $userTgId,
                'action' => $logAction,
                'panel_selection_strategy' => config('panel.selection_strategy'),
                'panels_count' => count($panels),
                'ms_panel_selection' => (int) round(($tSelectionEnd - $tSelectionStart) * 1000),
            ]);

            $tMarzbanStart = microtime(true);
            $lastError = null;
            foreach ($panels as $panel) {
                try {
                    $panelStrategy = new PanelStrategy($panel->panel ?? Panel::MARZBAN);
                    $serverUser = $panelStrategy->addServerUser(
                        $panel->id,
                        $userTgId,
                        $keyLocked->traffic_limit,
                        $finishAt,
                        $keyLocked->id,
                        ['max_connections' => config('panel.max_connections', 4)]
                    );
                    $serverUsers[] = $serverUser;
                } catch (Exception $e) {
                    $lastError = $e;
                    $this->panelRepository->markPanelWithError(
                        $panel->id,
                        'Ошибка при создании пользователя: ' . $e->getMessage()
                    );
                    $provider = $panel->server ? $panel->server->provider : '';
                    Log::error('Ошибка панели при активации (слот)', [
                        'panel_id' => $panel->id,
                        'provider' => $provider,
                        'key_id' => $keyId,
                        'error' => $e->getMessage(),
                        'source' => 'key_activate',
                    ]);
                    $this->panelRepository->forgetRotationSelectionCache($provider ?: null);
                }
            }

            if (empty($serverUsers)) {
                $errorMessage = 'Не удалось создать пользователя ни на одной панели';
                if ($lastError) {
                    $errorMessage .= ': ' . $lastError->getMessage();
                }
                throw new RuntimeException($errorMessage);
            }

            $tMarzbanEnd = microtime(true);

            $tDbStart = microtime(true);
            $activatedKey = $this->keyActivateRepository->updateActivationData(
                $keyLocked,
                $userTgId,
                KeyActivate::ACTIVE
            );

            if ($finishAtOverride !== null) {
                $activatedKey->finish_at = $finishAtOverride;
                $activatedKey->save();
            }
            $tDbEnd = microtime(true);

            $this->logger->info('Ключ успешно активирован', [
                'source' => 'key_activate',
                'action' => $logAction,
                'key_id' => $activatedKey->id,
                'user_tg_id' => $userTgId,
                'panel_selection_strategy' => config('panel.selection_strategy'),
                'panel_ids' => array_map(fn ($su) => $su->panel_id, $serverUsers),
                'ms_panel_selection' => (int) round(($tSelectionEnd - $tSelectionStart) * 1000),
                'ms_marzban_slots' => (int) round(($tMarzbanEnd - $tMarzbanStart) * 1000),
                'ms_activation_db' => (int) round(($tDbEnd - $tDbStart) * 1000),
                'ms_activation_after_claim' => (int) round(($tDbEnd - $tSelectionStart) * 1000),
            ]);

            if ($logAction === 'activate_module_key') {
                $refreshed = KeyActivate::with('packSalesman.salesman')->find($activatedKey->id);
                if ($refreshed && $refreshed->packSalesman && $refreshed->packSalesman->salesman) {
                    $this->notificationService->sendKeyActivatedNotification(
                        $refreshed->packSalesman->salesman->telegram_id,
                        $refreshed->id
                    );
                }
            } elseif (!is_null($keyLocked->pack_salesman_id)) {
                $packSalesman = $this->packSalesmanRepository->findByIdOrFail($keyLocked->pack_salesman_id);
                $this->notificationService->sendKeyActivatedNotification($packSalesman->salesman->telegram_id, $keyLocked->id);
            }

            $this->scheduleWarmConfigAfterActivation((string) $activatedKey->id);

            return $activatedKey;
        } catch (\Throwable $e) {
            // Не снимаем бронь, если пользователи уже созданы на панелях — иначе дубли в Marzban при повторе.
            if (count($serverUsers) === 0) {
                $this->keyActivateRepository->releaseActivationClaim($keyId);
            } else {
                $this->logger->error('Ошибка активации после создания пользователей на панелях (ключ ACTIVATING)', [
                    'source' => 'key_activate',
                    'key_id' => $keyId,
                    'user_tg_id' => $userTgId,
                    'error' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Прогрев конфига после активации: по умолчанию после ответа HTTP (terminating), без блокировки Telegram на минуту.
     */
    private function scheduleWarmConfigAfterActivation(string $keyActivateId): void
    {
        if (config('panel.skip_warm_config_after_activation')) {
            return;
        }
        if (config('panel.defer_warm_config_after_activation', true)) {
            $id = $keyActivateId;
            app()->terminating(function () use ($id) {
                $this->warmConfigSync($id);
            });

            return;
        }
        $this->warmConfigSync($keyActivateId);
    }

    /**
     * Запрос к панелям и сохранение ссылок в БД (тяжёлый путь — не держать в критическом пути активации при defer).
     */
    private function warmConfigSync(string $keyActivateId): void
    {
        try {
            $path = route('vpn.config.refresh', ['token' => $keyActivateId], false);
            $url = rtrim(config('app.url'), '/') . $path;
            Http::timeout((int) config('panel.warm_config_http_timeout', 45))
                ->withHeaders(['Accept' => 'application/json'])
                ->get($url);
        } catch (\Throwable $e) {
            Log::error('Ошибка прогрева конфига после активации', [
                'source' => 'key_activate',
                'key_activate_id' => $keyActivateId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Ручная деактивация ключа (админка): снятие пользователей со всех панелей (все слоты),
     * очистка связей в БД через deleteServerUser, статус EXPIRED, finish_at = сейчас (если ещё не в прошлом).
     *
     * @return array{key: KeyActivate, warning: ?string}
     */
    public function deactivate(KeyActivate $key): array
    {
        return DB::transaction(function () use ($key) {
            $warning = null;

            $key->refresh();

            if ($key->status === KeyActivate::DELETED) {
                throw new RuntimeException('Ключ в статусе «Удалён» — деактивация не применяется.');
            }

            if ($key->status === KeyActivate::EXPIRED) {
                $stillLinked = $key->keyActivateUsers()->exists();
                if (! $stillLinked) {
                    return ['key' => $key, 'warning' => null];
                }
            } elseif (! in_array($key->status, [KeyActivate::ACTIVE, KeyActivate::ACTIVATING, KeyActivate::PAID], true)) {
                throw new RuntimeException('Деактивация доступна для статусов: активирован, активация…, оплачен; либо просрочен с оставшимися слотами на панелях.');
            }

            $slots = $key->keyActivateUsers()->with('serverUser.panel')->orderBy('id')->get();

            foreach ($slots as $kau) {
                $serverUser = $kau->serverUser;
                if (! $serverUser) {
                    $kau->delete();

                    continue;
                }

                $panel = $serverUser->panel;
                if (! $panel) {
                    $this->logger->warning('Деактивация: слот без панели, удаляем только связи', [
                        'source' => 'key_activate',
                        'action' => 'deactivate',
                        'key_id' => $key->id,
                        'server_user_id' => $serverUser->id,
                    ]);
                    try {
                        $kau->delete();
                        $serverUser->delete();
                    } catch (Exception $e) {
                        $this->logger->error('Деактивация: не удалось удалить осиротевший server_user', [
                            'key_id' => $key->id,
                            'error' => $e->getMessage(),
                            'source' => 'key_activate',
                        ]);
                    }

                    continue;
                }

                $panelType = $panel->panel ?? Panel::MARZBAN;
                if ($panelType === '') {
                    $panelType = Panel::MARZBAN;
                }

                try {
                    $panelStrategy = new PanelStrategy($panelType);
                    $panelStrategy->deleteServerUser((int) $panel->id, (string) $serverUser->id);
                    $this->logger->info('Деактивация: пользователь снят с панели', [
                        'source' => 'key_activate',
                        'action' => 'deactivate',
                        'key_id' => $key->id,
                        'panel_id' => $panel->id,
                        'server_user_id' => $serverUser->id,
                    ]);
                } catch (Exception $e) {
                    $this->logger->warning('Деактивация: ошибка Marzban/БД при удалении пользователя панели', [
                        'source' => 'key_activate',
                        'action' => 'deactivate',
                        'key_id' => $key->id,
                        'panel_id' => $panel->id,
                        'server_user_id' => $serverUser->id,
                        'error' => $e->getMessage(),
                    ]);
                    try {
                        $this->panelRepository->markPanelWithError(
                            (int) $panel->id,
                            'Деактивация ключа: ' . $e->getMessage()
                        );
                    } catch (Exception $ignore) {
                    }
                    $provider = $panel->server ? (string) $panel->server->provider : '';
                    $this->panelRepository->forgetRotationSelectionCache($provider !== '' ? $provider : null);
                }
            }

            $remaining = KeyActivateUser::where('key_activate_id', $key->id)->count();
            if ($remaining > 0) {
                $warning = "Остались привязки к панелям ({$remaining}): проверьте логи и панели Marzban.";
                $this->logger->error('Деактивация ключа завершена с остаточными слотами', [
                    'source' => 'key_activate',
                    'action' => 'deactivate',
                    'key_id' => $key->id,
                    'remaining_key_activate_user' => $remaining,
                ]);
            }

            $key->refresh();
            $now = time();
            $key->status = KeyActivate::EXPIRED;
            if (! $key->finish_at || $key->finish_at > $now) {
                $key->finish_at = $now;
            }
            $key->save();

            $this->logger->info('Ключ деактивирован (админка)', [
                'source' => 'key_activate',
                'action' => 'deactivate',
                'key_id' => $key->id,
                'had_warning' => $warning !== null,
            ]);

            return ['key' => $key->fresh(), 'warning' => $warning];
        });
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
                // Загружаем связь если не загружена
                if (!$key->relationLoaded('keyActivateUser')) {
                    $key->load('keyActivateUser.serverUser');
                }
                
                $key->status = KeyActivate::EXPIRED;
                $statusChanged = true;

                $daysOverdue = round(($currentTime - $key->deleted_at) / 86400, 1);
                $deletedAtDate = date('Y-m-d H:i:s', $key->deleted_at);
                $currentDate = date('Y-m-d H:i:s', $currentTime);

                $this->logger->critical("🚫 [KEY: {$key->id}] СТАТУС КЛЮЧА ИЗМЕНЕН НА EXPIRED (истек срок активации для оплаченного ключа) | KEY_ID: {$key->id} | {$key->id}", [
                    'source' => 'key_activate',
                    'action' => 'update_status_to_expired',
                    'key_id' => $key->id,
                    'search_key' => $key->id, // Для быстрого поиска
                    'search_tag' => 'KEY_EXPIRED',
                    'user_tg_id' => $key->user_tg_id,
                    'old_status' => $originalStatus,
                    'old_status_text' => $this->getStatusTextByCode($originalStatus),
                    'new_status' => $key->status,
                    'new_status_text' => 'EXPIRED',
                    'reason' => 'Истек срок активации (deleted_at) для оплаченного ключа',
                    'deleted_at' => $key->deleted_at,
                    'deleted_at_date' => $deletedAtDate,
                    'current_time' => $currentTime,
                    'current_date' => $currentDate,
                    'days_overdue' => $daysOverdue,
                    'finish_at' => $key->finish_at,
                    'finish_at_date' => $key->finish_at ? date('Y-m-d H:i:s', $key->finish_at) : null,
                    'pack_salesman_id' => $key->pack_salesman_id,
                    'module_salesman_id' => $key->module_salesman_id,
                    'traffic_limit' => $key->traffic_limit,
                    'has_key_activate_user' => $key->keyActivateUser ? true : false,
                    'key_activate_user_id' => $key->keyActivateUser ? $key->keyActivateUser->id : null,
                    'key_activate_user_server_user_id' => ($key->keyActivateUser && $key->keyActivateUser->serverUser) ? $key->keyActivateUser->serverUser->id : null,
                    'key_created_at' => $key->created_at ? $key->created_at->format('Y-m-d H:i:s') : null,
                    'key_updated_at' => $key->updated_at ? $key->updated_at->format('Y-m-d H:i:s') : null,
                    'note' => 'Для ключей со статусом PAID связь keyActivateUser может отсутствовать (ключ не был активирован)',
                    'warning' => '⚠️ ВАЖНО: При смене статуса на EXPIRED связь keyActivateUser НЕ должна удаляться!',
                    'method' => 'checkAndUpdateStatus',
                    'file' => __FILE__,
                    'line' => __LINE__
                ]);
            }

            // Проверяем срок действия для активных ключей (finish_at)
            if ($key->status === KeyActivate::ACTIVE && $key->finish_at && $currentTime > $key->finish_at) {
                // Загружаем связь если не загружена
                if (!$key->relationLoaded('keyActivateUser')) {
                    $key->load('keyActivateUser.serverUser');
                }
                
                $key->status = KeyActivate::EXPIRED;
                $statusChanged = true;

                $daysOverdue = round(($currentTime - $key->finish_at) / 86400, 1);
                $finishAtDate = date('Y-m-d H:i:s', $key->finish_at);
                $currentDate = date('Y-m-d H:i:s', $currentTime);

                $this->logger->critical("🚫 [KEY: {$key->id}] СТАТУС КЛЮЧА ИЗМЕНЕН НА EXPIRED (истек срок действия активного ключа) | KEY_ID: {$key->id} | {$key->id}", [
                    'source' => 'key_activate',
                    'action' => 'update_status_to_expired',
                    'key_id' => $key->id,
                    'search_key' => $key->id, // Для быстрого поиска
                    'search_tag' => 'KEY_EXPIRED',
                    'user_tg_id' => $key->user_tg_id,
                    'old_status' => $originalStatus,
                    'old_status_text' => $this->getStatusTextByCode($originalStatus),
                    'new_status' => $key->status,
                    'new_status_text' => 'EXPIRED',
                    'reason' => 'Истек срок действия (finish_at) для активного ключа',
                    'finish_at' => $key->finish_at,
                    'finish_at_date' => $finishAtDate,
                    'current_time' => $currentTime,
                    'current_date' => $currentDate,
                    'days_overdue' => $daysOverdue,
                    'deleted_at' => $key->deleted_at,
                    'deleted_at_date' => $key->deleted_at ? date('Y-m-d H:i:s', $key->deleted_at) : null,
                    'pack_salesman_id' => $key->pack_salesman_id,
                    'module_salesman_id' => $key->module_salesman_id,
                    'traffic_limit' => $key->traffic_limit,
                    'has_key_activate_user' => $key->keyActivateUser ? true : false,
                    'key_activate_user_id' => $key->keyActivateUser ? $key->keyActivateUser->id : null,
                    'server_user_id' => ($key->keyActivateUser && $key->keyActivateUser->serverUser) ? $key->keyActivateUser->serverUser->id : null,
                    'panel_id' => ($key->keyActivateUser && $key->keyActivateUser->serverUser) ? $key->keyActivateUser->serverUser->panel_id : null,
                    'key_created_at' => $key->created_at ? $key->created_at->format('Y-m-d H:i:s') : null,
                    'key_updated_at' => $key->updated_at ? $key->updated_at->format('Y-m-d H:i:s') : null,
                    'warning' => '⚠️ ВАЖНО: При смене статуса на EXPIRED связь keyActivateUser НЕ должна удаляться!',
                    'method' => 'checkAndUpdateStatus',
                    'file' => __FILE__,
                    'line' => __LINE__
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
     * Перевыпуск просроченного ключа.
     * Удаляет всех старых пользователей сервера (все слоты), создаёт новых по getPanelsForActivation
     * (как при активации — мульти-провайдер), возвращает ключ в статус ACTIVE.
     *
     * @param KeyActivate $key
     * @return KeyActivate
     * @throws RuntimeException|GuzzleException
     */
    public function renew(KeyActivate $key): KeyActivate
    {
        try {
            // Разрешаем перевыпуск для просроченных и активных (исправление битых ключей)
            if (!in_array($key->status, [KeyActivate::EXPIRED, KeyActivate::ACTIVE], true)) {
                throw new RuntimeException('Перевыпуск доступен только для активированных или просроченных ключей.');
            }

            if (!$key->user_tg_id) {
                throw new RuntimeException('Нельзя перевыпустить ключ без привязки к пользователю Telegram');
            }

            if (!$key->relationLoaded('keyActivateUsers')) {
                $key->load('keyActivateUsers.serverUser.panel');
            }
            if (!$key->relationLoaded('packSalesman')) {
                $key->load('packSalesman.pack', 'packSalesman.salesman.panel.server');
            }

            // Удаляем всех старых пользователей сервера (все слоты)
            foreach ($key->keyActivateUsers as $kau) {
                $oldServerUser = $kau->serverUser;
                if (!$oldServerUser) {
                    continue;
                }
                $oldPanel = $oldServerUser->panel;
                if (!$oldPanel) {
                    continue;
                }
                $panelType = $oldPanel->panel ?? Panel::MARZBAN;
                if ($panelType === '') {
                    $panelType = Panel::MARZBAN;
                }
                try {
                    $panelStrategy = new PanelStrategy($panelType);
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
                }
            }

            // Удаляем старые слоты из БД, иначе новые добавятся к старым и получится 7+ или 13 слотов
            $key->keyActivateUsers()->delete();

            $panels = $this->getPanelsForActivation($key, true);
            if (empty($panels)) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            $trafficLimit = $key->traffic_limit ?? 0;
            $finishAt = $key->finish_at;
            if (!$finishAt && $key->packSalesman && $key->packSalesman->pack) {
                $finishAt = time() + ($key->packSalesman->pack->period * \App\Constants\TimeConstants::SECONDS_IN_DAY);
            } elseif (!$finishAt) {
                $finishAt = Carbon::now()->addMonth()->startOfMonth()->timestamp;
            }

            $serverUsers = [];
            $lastError = null;
            foreach ($panels as $panel) {
                try {
                    $panelStrategy = new PanelStrategy($panel->panel ?? Panel::MARZBAN);
                    $serverUser = $panelStrategy->addServerUser(
                        $panel->id,
                        $key->user_tg_id,
                        $trafficLimit,
                        $finishAt,
                        $key->id,
                        ['max_connections' => config('panel.max_connections', 4)]
                    );
                    $serverUsers[] = $serverUser;
                } catch (Exception $e) {
                    $lastError = $e;
                    $this->panelRepository->markPanelWithError(
                        $panel->id,
                        'Ошибка при создании пользователя при перевыпуске: ' . $e->getMessage()
                    );
                    $provider = $panel->server ? $panel->server->provider : '';
                    Log::error('Ошибка панели при перевыпуске ключа', [
                        'panel_id' => $panel->id,
                        'provider' => $provider,
                        'key_id' => $key->id,
                        'error' => $e->getMessage(),
                        'source' => 'key_activate',
                    ]);
                    $this->panelRepository->forgetRotationSelectionCache($provider ?: null);
                }
            }

            if (empty($serverUsers)) {
                $message = 'Не удалось создать пользователя ни на одной панели';
                if ($lastError) {
                    $message .= ': ' . $lastError->getMessage();
                }
                throw new RuntimeException($message);
            }

            $activatedKey = $this->keyActivateRepository->updateActivationData(
                $key,
                $key->user_tg_id,
                KeyActivate::ACTIVE
            );
            $activatedKey->finish_at = $finishAt;
            $activatedKey->save();

            $this->logger->info('Ключ успешно перевыпущен', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $activatedKey->id,
                'user_tg_id' => $key->user_tg_id,
                'finish_at' => $finishAt,
                'server_user_ids' => array_map(fn ($su) => $su->id, $serverUsers),
            ]);

            if ($key->pack_salesman_id) {
                $packSalesman = $this->packSalesmanRepository->findByIdOrFail($key->pack_salesman_id);
                $this->notificationService->sendKeyActivatedNotification($packSalesman->salesman->telegram_id, $key->id);
            }

            $this->scheduleWarmConfigAfterActivation((string) $activatedKey->id);

            return $activatedKey;
        } catch (\Throwable $e) {
            $msg = 'Перевыпуск ключа — ОШИБКА: ' . $e->getMessage();
            $this->logger->error('Ошибка при перевыпуске ключа', [
                'source' => 'key_activate',
                'action' => 'renew',
                'key_id' => $key->id,
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            // Прямая запись в application_logs, чтобы ошибка точно отображалась в админке (trace обрезаем)
            try {
                $trace = $e->getTraceAsString();
                ApplicationLog::create([
                    'level' => 'error',
                    'source' => 'key_activate',
                    'message' => $msg,
                    'context' => [
                        'action' => 'renew',
                        'key_id' => $key->id,
                        'error_class' => get_class($e),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => strlen($trace) > 8000 ? substr($trace, 0, 8000) . "\n...[обрезано]" : $trace,
                    ],
                    'user_id' => auth()->check() ? (string) auth()->id() : null,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            } catch (\Throwable $logEx) {
                // не ломаем ответ при сбое записи лога
            }

            throw new RuntimeException('Ошибка при перевыпуске ключа: ' . $e->getMessage(), 0, $e);
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
            case KeyActivate::ACTIVATING:
                return 'ACTIVATING (Активация)';
            case KeyActivate::DELETED:
                return 'DELETED (Удален)';
            default:
                return "Unknown ({$statusCode})";
        }
    }
}
