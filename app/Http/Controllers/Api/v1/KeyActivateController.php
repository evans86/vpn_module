<?php

namespace App\Http\Controllers\Api\v1;

use App\Dto\Bot\BotModuleFactory;
use App\Helpers\ApiHelpers;
use App\Http\Controllers\Controller;
use App\Http\Requests\BotModule\BotModuleInstructionsRequest;
use App\Http\Requests\KeyActivate\KeyActivateRequest;
use App\Http\Requests\PackSalesman\PackSalesmanBuyKeyRequest;
use App\Http\Requests\PackSalesman\PackSalesmanFreeKeyRequest;
use App\Http\Requests\PackSalesman\PackSalesmanUserKeysRequest;
use App\Models\Bot\BotModule;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Salesman\Salesman;
use App\Logging\DatabaseLogger;
use App\Services\External\BottApi;
use App\Services\Key\KeyActivateService;
use App\Services\Salesman\SalesmanService;
use Carbon\Carbon;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class KeyActivateController extends Controller
{
    /**
     * @var KeyActivateService
     */
    private KeyActivateService $keyActivateService;

    private DatabaseLogger $dbLogger;

    public function __construct(KeyActivateService $keyActivateService, DatabaseLogger $dbLogger)
    {
        $this->middleware('api');
        $this->keyActivateService = $keyActivateService;
        $this->dbLogger = $dbLogger;
    }

    /**
     * Покупка и активация ключа в боте продаж (активация ключа в системе).
     * Используется также маршрутами activate-key и activate (без передачи UUID ключа — ключ выдаёт Bott по заказу).
     *
     * @param PackSalesmanBuyKeyRequest $request
     * @return array|string
     * @throws GuzzleException
     */
    public function buyKey(PackSalesmanBuyKeyRequest $request)
    {
        try {
            $t0 = microtime(true);

            $this->dbLogger->info('Запрос на покупку ключа (модуль)', [
                'source' => 'key_activate',
                'action' => 'buy_key_request',
                'user_tg_id' => $request->user_tg_id,
                'product_id' => $request->product_id,
                'public_key_prefix' => substr((string) $request->public_key, 0, 12),
            ]);

            // Проверка существования модуля бота
            $tBeforeModule = microtime(true);
            $botModule = BotModule::where('public_key', $request->public_key)->first();
            if (!$botModule) {
                throw new RuntimeException('Модуль бота не найден');
            }

            $botModuleDto = BotModuleFactory::fromEntity($botModule);

            $tBeforeCheckUser = microtime(true);
            $userBottData = $this->assertBottUserForWebModule(
                $botModule,
                $request->user_tg_id,
                (string) $request->user_secret_key,
                true
            );
            $tAfterCheckUser = microtime(true);

            $this->dbLogger->info('Покупка (модуль): тайминг до buyKey', [
                'source' => 'key_activate',
                'action' => 'buy_key_timing_before_purchase',
                'user_tg_id' => $request->user_tg_id,
                'product_id' => $request->product_id,
                'ms_db_module' => (int) round(($tBeforeCheckUser - $tBeforeModule) * 1000),
                'ms_bott_check_user' => (int) round(($tAfterCheckUser - $tBeforeCheckUser) * 1000),
                'ms_subtotal' => (int) round(($tAfterCheckUser - $t0) * 1000),
            ]);

            // Покупка ключа в боте продаж
            $tBeforeBuyKey = microtime(true);
            $key = $this->keyActivateService->buyKey($botModuleDto, $request->product_id, $userBottData);
            $tAfterBuyKey = microtime(true);

            $this->dbLogger->info('Покупка (модуль): тайминг buyKey (Bott + ключ в БД)', [
                'source' => 'key_activate',
                'action' => 'buy_key_timing_bott_purchase',
                'user_tg_id' => $request->user_tg_id,
                'key_id' => $key->id,
                'ms_buy_key' => (int) round(($tAfterBuyKey - $tBeforeBuyKey) * 1000),
                'ms_total' => (int) round(($tAfterBuyKey - $t0) * 1000),
            ]);

            // Активация ключа в системе
            $tBeforeActivate = microtime(true);
            $activatedKey = $this->keyActivateService->activateModuleKey($key, $request->user_tg_id);
            $tAfterActivate = microtime(true);

            $this->dbLogger->info('Покупка (модуль): тайминг activateModuleKey', [
                'source' => 'key_activate',
                'action' => 'buy_key_timing_activation',
                'user_tg_id' => $request->user_tg_id,
                'key_id' => $activatedKey->id,
                'ms_activate_module_key' => (int) round(($tAfterActivate - $tBeforeActivate) * 1000),
                'ms_total_request' => (int) round(($tAfterActivate - $t0) * 1000),
            ]);

            return ApiHelpers::success($this->webModuleActivationSuccessPayload($activatedKey));
        } catch (RuntimeException $r) {
            return ApiHelpers::error($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при покупке ключа', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_tg_id' => $request->user_tg_id ?? null,
                'product_id' => $request->product_id ?? null,
                'public_key' => $request->public_key ?? null
            ]);
            return ApiHelpers::error('Произошла ошибка при покупке ключа');
        }
    }

    /**
     * Тело успешного ответа после {@see KeyActivateService::activateModuleKey}.
     */
    private function webModuleActivationSuccessPayload(KeyActivate $activatedKey): array
    {
        return array_merge([
            'key' => $activatedKey->id,
            'traffic_limit' => $activatedKey->traffic_limit,
            'traffic_limit_gb' => round($activatedKey->traffic_limit / 1024 / 1024 / 1024, 1),
            'finish_at' => $activatedKey->finish_at,
            'status' => $activatedKey->status,
            'status_text' => $activatedKey->getStatusText(),
        ], \App\Helpers\UrlHelper::configUrlsPayload($activatedKey->id));
    }

    /**
     * Проверка пользователя Bott для веб-модуля (как buyKey, без дублирования кода).
     *
     * @return array Данные пользователя Bott для {@see KeyActivateService::buyKey}
     */
    private function assertBottUserForWebModule(
        BotModule $botModule,
        $userTgId,
        string $userSecretKey,
        bool $requirePositiveBalance
    ): array {
        $userCheck = BottApi::checkUser(
            $userTgId,
            $userSecretKey,
            $botModule->public_key,
            $botModule->private_key
        );
        if (!$userCheck['result']) {
            throw new RuntimeException($userCheck['message'] ?? 'Ошибка авторизации пользователя');
        }
        $data = $userCheck['data'] ?? [];
        if (! is_array($data)) {
            $data = [];
        }
        if ($requirePositiveBalance && (($data['money'] ?? 0) == 0)) {
            throw new RuntimeException('Пополните баланс в боте');
        }

        return $data;
    }

    /**
     * Продавец веб-модуля: как buyKey ({@see Salesman::where('module_bot_id')}),
     * затем создатель Bott; при переданном ключе — прежние fallback по ключу (без смены правил принадлежности).
     *
     * @param KeyActivate|null $key Если задан — доп. разрешение продавца по module_salesman_id / pack (как было в resolveSalesmanForModuleActivation).
     */
    private function resolveSalesmanForWebModule(BotModule $botModule, ?KeyActivate $key = null): ?Salesman
    {
        $moduleId = (int) $botModule->id;

        $salesman = Salesman::where('module_bot_id', $moduleId)->first();
        if ($salesman) {
            return $salesman;
        }

        $fromCreator = $this->tryLinkSalesmanFromBottModuleCreator($botModule);
        if ($fromCreator) {
            return $fromCreator;
        }

        if ($key instanceof KeyActivate && $key->module_salesman_id) {
            $candidate = Salesman::find($key->module_salesman_id);
            if ($candidate instanceof Salesman) {
                if ((int) $candidate->module_bot_id === $moduleId) {
                    return $candidate;
                }
                if ($candidate->module_bot_id === null || (int) $candidate->module_bot_id === 0) {
                    $candidate->module_bot_id = $moduleId;
                    $candidate->save();
                    Log::info('Восстановлена связь продавца с модулем по module_salesman_id ключа', [
                        'salesman_id' => $candidate->id,
                        'module_id' => $moduleId,
                        'key_id' => $key->id,
                    ]);

                    return $candidate;
                }
            }
        }

        if ($key instanceof KeyActivate && $key->pack_salesman_id) {
            $key->loadMissing('packSalesman.salesman');
            $packSalesman = $key->packSalesman;
            $candidate = $packSalesman !== null ? $packSalesman->salesman : null;
            if ($candidate instanceof Salesman && (int) $candidate->module_bot_id === $moduleId) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Ключ принадлежит модулю продавца (та же логика, что getUserKey).
     */
    private function keyBelongsToWebModule(KeyActivate $key, Salesman $salesman): bool
    {
        $keyBelongsToModule = false;
        if ($key->pack_salesman_id) {
            $keyBelongsToModule = $key->packSalesman
                && (int) $key->packSalesman->salesman_id === (int) $salesman->id
                && $key->packSalesman->pack
                && $key->packSalesman->pack->module_key == true;
        }
        if (! $keyBelongsToModule && $key->module_salesman_id) {
            $keyBelongsToModule = (int) $key->module_salesman_id === (int) $salesman->id;
        }

        return $keyBelongsToModule;
    }

    /**
     * Как в getUserKey: создатель модуля в Bott → Salesman.module_bot_id (если в БД ещё не привязали).
     */
    private function tryLinkSalesmanFromBottModuleCreator(BotModule $botModule): ?Salesman
    {
        $moduleId = (int) $botModule->id;

        try {
            $creator = BottApi::getCreator($botModule->public_key, $botModule->private_key);

            $user = is_array($creator['data']['user'] ?? null) ? $creator['data']['user'] : null;
            $telegramId = $user['telegram_id'] ?? null;
            if ($telegramId === null || $telegramId === '') {
                Log::warning('getCreator без telegram_id (activate-key)', [
                    'module_id' => $moduleId,
                    'creator_result' => $creator['result'] ?? null,
                    'creator_keys' => is_array($creator['data'] ?? null) ? array_keys($creator['data']) : [],
                ]);

                return null;
            }

            $username = $user['username'] ?? null;
            $telegramIdNorm = (string) $telegramId;

            $salesman = Salesman::where('telegram_id', $telegramIdNorm)->first();

            if (!$salesman) {
                try {
                    app(SalesmanService::class)->create((int) $telegramId, $username);
                    $salesman = Salesman::where('telegram_id', $telegramIdNorm)->first();
                } catch (Exception $e) {
                    if (str_contains($e->getMessage(), 'already exists')) {
                        $salesman = Salesman::where('telegram_id', $telegramIdNorm)->first();
                    } else {
                        Log::error('Ошибка при создании продавца (activate-key)', [
                            'telegram_id' => $telegramIdNorm,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Как в BotModuleService::update: модуль принадлежит создателю — привязываем всегда, иначе застреваем
            // на старом неверном module_bot_id (раньше здесь обновляли только при null).
            if ($salesman) {
                $otherOwner = Salesman::where('module_bot_id', $moduleId)
                    ->where('id', '!=', $salesman->id)
                    ->first();
                if ($otherOwner) {
                    Log::warning('Модуль уже привязан к другому продавцу, getCreator не перезаписывает (activate-key)', [
                        'module_id' => $moduleId,
                        'existing_salesman_id' => $otherOwner->id,
                        'creator_salesman_id' => $salesman->id,
                    ]);

                    return $otherOwner;
                }

                $prevModuleId = $salesman->module_bot_id;
                $salesman->module_bot_id = $moduleId;
                $salesman->save();

                Log::info('Связь продавца с модулем по создателю Bott (activate-key)', [
                    'salesman_id' => $salesman->id,
                    'module_id' => $moduleId,
                    'telegram_id' => $telegramIdNorm,
                    'previous_module_bot_id' => $prevModuleId,
                ]);
            }
        } catch (Exception $e) {
            Log::error('Ошибка при автопривязке продавца к модулю (activate-key)', [
                'module_id' => $moduleId,
                'error' => $e->getMessage(),
            ]);
        }

        return Salesman::where('module_bot_id', $moduleId)->first();
    }

    /**
     * Получение бесплатного ключа на 5GB
     *
     * @param PackSalesmanFreeKeyRequest $request
     * @return array|string
     * @throws GuzzleException
     */
    public function getFreeKey(PackSalesmanFreeKeyRequest $request)
    {
        try {
            $this->dbLogger->info('Запрос бесплатного ключа (модуль)', [
                'source' => 'key_activate',
                'action' => 'free_key_request',
                'user_tg_id' => $request->user_tg_id,
                'public_key_prefix' => substr((string) $request->public_key, 0, 12),
            ]);

            $botModule = BotModule::where('public_key', $request->public_key)->first();
            if (!$botModule) {
                throw new RuntimeException('Модуль бота не найден');
            }

            // Проверка существующего ключа
            $currentMonth = Carbon::now()->startOfMonth();
            $nextMonth = Carbon::now()->addMonth()->startOfMonth();

            // Используем константу для размера бесплатного ключа (5GB)
            $freeKeySize = \App\Constants\ProductConstants::FREE_KEY_SIZE_GB * \App\Constants\DataConstants::BYTES_IN_GB;

            $hasExistingKey = KeyActivate::where('user_tg_id', $request->user_tg_id)
                ->where('traffic_limit', $freeKeySize)
                ->whereBetween('created_at', [$currentMonth, $nextMonth])
                ->whereNull('pack_salesman_id')->first();

            if ($hasExistingKey) {
                return ApiHelpers::success(array_merge([
                    'key' => $hasExistingKey->id,
                    'traffic_limit' => $hasExistingKey->traffic_limit,
                    'traffic_limit_gb' => \App\Constants\ProductConstants::FREE_KEY_SIZE_GB,
                    'finish_at' => $hasExistingKey->finish_at,
                    // 'activated_at' => $hasExistingKey->activated_at, // поле не существует в БД
                    'status' => $hasExistingKey->status,
                    'status_text' => $hasExistingKey->getStatusText(),
                    'is_free' => true,
                ], \App\Helpers\UrlHelper::configUrlsPayload($hasExistingKey->id)));
            }

            // Создание и активация ключа (5GB бесплатный ключ)
            $freeKeySize = \App\Constants\ProductConstants::FREE_KEY_SIZE_GB * \App\Constants\DataConstants::BYTES_IN_GB;
            $key = $this->keyActivateService->create($freeKeySize, null, null, null);
            $activatedKey = $this->keyActivateService->activate($key, $request->user_tg_id);

            return ApiHelpers::success(array_merge([
                'key' => $activatedKey->id,
                'traffic_limit' => $activatedKey->traffic_limit,
                'traffic_limit_gb' => 5,
                'finish_at' => $activatedKey->finish_at,
                // 'activated_at' => $activatedKey->activated_at, // поле не существует в БД
                'status' => $activatedKey->status,
                'status_text' => $activatedKey->getStatusText(),
                'is_free' => true,
            ], \App\Helpers\UrlHelper::configUrlsPayload($activatedKey->id)));
        } catch (RuntimeException $e) {
            return ApiHelpers::error($e->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при выдаче бесплатного ключа', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_tg_id' => $request->user_tg_id ?? null,
                'public_key' => $request->public_key ?? null
            ]);
            return ApiHelpers::error('Произошла ошибка при выдаче ключа');
        }
    }

    /**
     * Получение ключа
     *
     * @param KeyActivateRequest $request
     * @return array|string
     * @throws GuzzleException
     */
    public function getUserKey(KeyActivateRequest $request)
    {
        try {
            // Проверка существования модуля бота
            $botModule = BotModule::where('public_key', $request->public_key)->first();

            if (!$botModule) {
                throw new RuntimeException('Модуль бота не найден или неверные ключи доступа');
            }

            // Проверка авторизации пользователя
            $userCheck = BottApi::checkUser(
                $request->user_tg_id,
                $request->user_secret_key,
                $botModule->public_key,
                $botModule->private_key
            );

            if (!$userCheck['result']) {
                throw new RuntimeException($userCheck['message'] ?? 'Ошибка авторизации пользователя');
            }

            // Получаем ключ с загрузкой связей для проверки принадлежности
            $key = KeyActivate::with(['packSalesman.pack', 'moduleSalesman'])
                ->where('id', $request->key)
                ->first();

            if (! $key instanceof KeyActivate) {
                return ApiHelpers::error('Ключ не найден');
            }

            // Проверяем, что ключ принадлежит этому пользователю
            // Приводим к строке для корректного сравнения (может быть разный тип)
            if ((string)$key->user_tg_id !== (string)$request->user_tg_id) {
                return ApiHelpers::error('Доступ запрещен');
            }

            $salesman = $this->resolveSalesmanForWebModule($botModule, $key);
            if (!$salesman) {
                throw new RuntimeException('Продавец не найден для данного модуля');
            }

            if (!$this->keyBelongsToWebModule($key, $salesman)) {
                return ApiHelpers::error('Доступ запрещен');
            }

            $resultKey = array_merge([
                'key' => $key->id,
                'traffic_limit' => $key->traffic_limit,
                'traffic_limit_gb' => round($key->traffic_limit / \App\Constants\DataConstants::BYTES_IN_GB, 1),
                'finish_at' => $key->finish_at,
                'status' => $key->status,
                'status_text' => $key->getStatusText(),
            ], \App\Helpers\UrlHelper::configUrlsPayload($key->id));

            return ApiHelpers::success([
                'key' => $resultKey,
            ]);
        } catch (RuntimeException $e) {
            return ApiHelpers::error($e->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при получении ключа', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'key' => $request->key ?? null,
                'user_tg_id' => $request->user_tg_id ?? null,
                'public_key' => $request->public_key ?? null
            ]);
            return ApiHelpers::error('Ошибка при получении ключа');
        }
    }

    /**
     * Получение списка ключей пользователя
     *
     * @param PackSalesmanUserKeysRequest $request
     * @return array|string
     * @throws GuzzleException
     */
    public function getUserKeys(PackSalesmanUserKeysRequest $request)
    {
        try {
            // Проверка существования модуля бота
            $botModule = BotModule::where('public_key', $request->public_key)->first();

            if (!$botModule) {
                throw new RuntimeException('Модуль бота не найден или неверные ключи доступа');
            }

            // Проверка авторизации пользователя
            $userCheck = BottApi::checkUser(
                $request->user_tg_id,
                $request->user_secret_key,
                $botModule->public_key,
                $botModule->private_key
            );

            if (!$userCheck['result']) {
                throw new RuntimeException($userCheck['message'] ?? 'Ошибка авторизации пользователя');
            }

            $salesman = $this->resolveSalesmanForWebModule($botModule);
            if (! $salesman) {
                throw new RuntimeException('Продавец не найден для данного модуля');
            }

            // Запрос ключей пользователя с проверкой принадлежности модулю
            // Ключи могут быть через packSalesman (из бота) или напрямую через moduleSalesman (из модуля)
            $query = KeyActivate::where('user_tg_id', $request->user_tg_id)
                ->where('status', '!=', KeyActivate::DELETED)
                ->where(function ($q) use ($salesman) {
                    // Ключи из бота через packSalesman
                    $q->whereHas('packSalesman', function ($query) use ($salesman) {
                        $query->where('salesman_id', $salesman->id)
                            ->whereHas('pack', function ($packQuery) {
                                $packQuery->where('module_key', true); // Только пакеты для модуля
                            });
                    })
                    ->orWhere('module_salesman_id', $salesman->id);
                });

            $total = $query->count();

            // По умолчанию — по дате окончания: сначала «самые долгие» (позже всего заканчиваются), без даты — в начале.
            // sort=purchase — по дате создания.
            $sort = (string) $request->input('sort', 'expires');
            if ($sort === 'purchase') {
                $query->orderByDesc('created_at')
                    ->orderByDesc('id');
            } else {
                $query->orderByRaw('CASE WHEN finish_at IS NULL THEN 0 ELSE 1 END ASC')
                    ->orderBy('finish_at', 'desc')
                    ->orderByDesc('id');
            }

//            if ($request->has('limit')) {
//                $limit = (int)$request->input('limit', 10);
//                $offset = (int)$request->input('offset', 0);
//
//                $query->limit($limit)->offset($offset);
//            }

            $keys = $query->get()
                ->map(function ($key) {
                    return array_merge([
                        'key' => $key->id,
                        'traffic_limit' => $key->traffic_limit,
                        'traffic_limit_gb' => round($key->traffic_limit / \App\Constants\DataConstants::BYTES_IN_GB, 1),
                        'finish_at' => $key->finish_at,
                        'created_at' => $key->created_at ? $key->created_at->timestamp : null,
                        'status' => $key->status,
                        'status_text' => $key->getStatusText(),
//                        'pack_type' => $key->packSalesman->pack->module_key ? 'module' : 'bot'
                    ], \App\Helpers\UrlHelper::configUrlsPayload($key->id));
                });

            // Если ключей нет, возвращаем пустой массив с пояснением
            if ($keys->isEmpty()) {
                return ApiHelpers::success([
                    'keys' => [],
                    'total' => $total,
                    'message' => 'У пользователя нет активных ключей',
                ]);
            }

            return ApiHelpers::success([
                'keys' => $keys,
                'total' => $total, // Возвращаем общее количество ключей
//                'limit' => $request->input('limit', null),
//                'offset' => $request->input('offset', 0),
            ]);
        } catch (RuntimeException $e) {
            return ApiHelpers::error($e->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при получении списка ключей', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_tg_id' => $request->user_tg_id ?? null
            ]);
            return ApiHelpers::error('Ошибка при получении списка ключей');
        }
    }

    /**
     * Получение инструкций по работе с VPN
     *
     * @return array|string
     */
    public function getVpnInstructions(BotModuleInstructionsRequest $request)
    {
        try {
            $botModule = BotModule::where('public_key', $request->public_key)->first();
            if (!$botModule) {
                throw new RuntimeException('Модуль бота не найден');
            }

            $instructions = $botModule->vpn_instructions;

            return ApiHelpers::success([
                $instructions
            ]);

        } catch (Exception $e) {
            Log::error('Ошибка при получении инструкций', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'public_key' => $request->public_key ?? null
            ]);
            return ApiHelpers::error('Не удалось загрузить инструкции');
        }
    }
}
