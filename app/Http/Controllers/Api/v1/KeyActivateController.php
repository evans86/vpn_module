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
     * Покупка и активация ключа в боте продаж (активация ключа в системе)
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
            $userCheck = BottApi::checkUser(
                $request->user_tg_id,
                $request->user_secret_key,
                $botModule->public_key,
                $botModule->private_key
            );
            $tAfterCheckUser = microtime(true);
            if (!$userCheck['result']) {
                throw new RuntimeException($userCheck['message']);
            }
            if ($userCheck['data']['money'] == 0) {
                throw new RuntimeException('Пополните баланс в боте');
            }

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
            $key = $this->keyActivateService->buyKey($botModuleDto, $request->product_id, $userCheck['data']);
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

            return ApiHelpers::success(array_merge([
                'key' => $activatedKey->id,
                'traffic_limit' => $activatedKey->traffic_limit,
                'traffic_limit_gb' => round($activatedKey->traffic_limit / 1024 / 1024 / 1024, 1),
                'finish_at' => $activatedKey->finish_at,
                // 'activated_at' => $activatedKey->activated_at, // поле не существует в БД
                'status' => $activatedKey->status,
                'status_text' => $activatedKey->getStatusText(),
            ], \App\Helpers\UrlHelper::configUrlsPayload($activatedKey->id)));
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

            // Если ключ не найден
            if (!$key) {
                return ApiHelpers::error('Ключ не найден');
            }

            // Проверяем, что ключ принадлежит этому пользователю
            // Приводим к строке для корректного сравнения (может быть разный тип)
            if ((string)$key->user_tg_id !== (string)$request->user_tg_id) {
                return ApiHelpers::error('Доступ запрещен');
            }

            // Получаем salesman, связанный с этим модулем
            $salesman = Salesman::where('module_bot_id', $botModule->id)->first();

            // Если продавец не найден, пытаемся создать/обновить связь
            if (!$salesman) {
                // Получаем информацию о создателе модуля из API
                try {
                    $creator = \App\Services\External\BottApi::getCreator($botModule->public_key, $botModule->private_key);
                    
                    if (isset($creator['data']['user']['telegram_id'])) {
                        $telegramId = $creator['data']['user']['telegram_id'];
                        $username = $creator['data']['user']['username'] ?? null;
                        
                        // Ищем продавца по telegram_id
                        $salesman = Salesman::where('telegram_id', $telegramId)->first();
                        
                        // Если продавец не найден, создаем его
                        if (!$salesman) {
                            try {
                                $salesmanService = app(\App\Services\Salesman\SalesmanService::class);
                                $salesmanDto = $salesmanService->create($telegramId, $username);
                                // После создания ищем продавца по telegram_id
                                $salesman = Salesman::where('telegram_id', $telegramId)->first();
                            } catch (\Exception $e) {
                                // Если продавец уже существует, просто находим его
                                if (str_contains($e->getMessage(), 'already exists')) {
                                    $salesman = Salesman::where('telegram_id', $telegramId)->first();
                                } else {
                                    Log::error('Ошибка при создании продавца', [
                                        'telegram_id' => $telegramId,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        }
                        
                        // Устанавливаем связь с модулем
                        if ($salesman && !$salesman->module_bot_id) {
                            $salesman->module_bot_id = $botModule->id;
                            $salesman->save();
                            
                            Log::info('Автоматически установлена связь продавца с модулем', [
                                'salesman_id' => $salesman->id,
                                'module_id' => $botModule->id,
                                'telegram_id' => $telegramId
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Ошибка при попытке автоматически создать/обновить связь продавца с модулем', [
                        'module_id' => $botModule->id,
                        'public_key' => $botModule->public_key,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Повторно ищем продавца
                if (!$salesman) {
                    $salesman = Salesman::where('module_bot_id', $botModule->id)->first();
                }
                
                if (!$salesman) {
                    throw new RuntimeException('Продавец не найден для данного модуля');
                }
            }

            // Проверяем, что ключ принадлежит этому модулю
            $keyBelongsToModule = false;

            // Проверка через packSalesman
            if ($key->pack_salesman_id) {
                $keyBelongsToModule = $key->packSalesman
                    && $key->packSalesman->salesman_id == $salesman->id
                    && $key->packSalesman->pack
                    && $key->packSalesman->pack->module_key == true;
            }

            // Проверка через moduleSalesman
            if (!$keyBelongsToModule && $key->module_salesman_id) {
                $keyBelongsToModule = $key->module_salesman_id == $salesman->id;
            }

            if (!$keyBelongsToModule) {
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

            // Получаем salesman, связанный с этим модулем
            $salesman = Salesman::where('module_bot_id', $botModule->id)->first();

            // Если продавец не найден, пытаемся создать/обновить связь
            if (!$salesman) {
                // Получаем информацию о создателе модуля из API
                try {
                    $creator = \App\Services\External\BottApi::getCreator($botModule->public_key, $botModule->private_key);
                    
                    if (isset($creator['data']['user']['telegram_id'])) {
                        $telegramId = $creator['data']['user']['telegram_id'];
                        $username = $creator['data']['user']['username'] ?? null;
                        
                        // Ищем продавца по telegram_id
                        $salesman = Salesman::where('telegram_id', $telegramId)->first();
                        
                        // Если продавец не найден, создаем его
                        if (!$salesman) {
                            try {
                                $salesmanService = app(\App\Services\Salesman\SalesmanService::class);
                                $salesmanDto = $salesmanService->create($telegramId, $username);
                                // После создания ищем продавца по telegram_id
                                $salesman = Salesman::where('telegram_id', $telegramId)->first();
                            } catch (\Exception $e) {
                                // Если продавец уже существует, просто находим его
                                if (str_contains($e->getMessage(), 'already exists')) {
                                    $salesman = Salesman::where('telegram_id', $telegramId)->first();
                                } else {
                                    Log::error('Ошибка при создании продавца', [
                                        'telegram_id' => $telegramId,
                                        'error' => $e->getMessage()
                                    ]);
                                }
                            }
                        }
                        
                        // Устанавливаем связь с модулем
                        if ($salesman && !$salesman->module_bot_id) {
                            $salesman->module_bot_id = $botModule->id;
                            $salesman->save();
                            
                            Log::info('Автоматически установлена связь продавца с модулем', [
                                'salesman_id' => $salesman->id,
                                'module_id' => $botModule->id,
                                'telegram_id' => $telegramId
                            ]);
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Ошибка при попытке автоматически создать/обновить связь продавца с модулем', [
                        'module_id' => $botModule->id,
                        'public_key' => $botModule->public_key,
                        'error' => $e->getMessage()
                    ]);
                }
                
                // Повторно ищем продавца
                if (!$salesman) {
                    $salesman = Salesman::where('module_bot_id', $botModule->id)->first();
                }
                
                if (!$salesman) {
                    throw new RuntimeException('Продавец не найден для данного модуля');
                }
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

            // По умолчанию — по дате окончания (finish_at): при массовой выдаче ключей created_at у всех одинаковый, порядок «ломается».
            // sort=purchase — по дате создания (как раньше по умолчанию).
            $sort = (string) $request->input('sort', 'expires');
            if ($sort === 'purchase') {
                $query->orderByDesc('created_at')
                    ->orderByDesc('id');
            } else {
                $query->orderByRaw('CASE WHEN finish_at IS NULL THEN 1 ELSE 0 END ASC')
                    ->orderBy('finish_at', 'asc')
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
