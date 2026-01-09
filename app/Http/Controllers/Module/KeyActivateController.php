<?php

namespace App\Http\Controllers\Module;

use App\Models\KeyActivate\KeyActivate;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;
use App\Models\Pack\Pack;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Services\Key\KeyActivateService;
use App\Services\Panel\PanelStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Exception;
use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class KeyActivateController extends Controller
{
    /**
     * @var DatabaseLogger
     */
    private DatabaseLogger $logger;
    /**
     * @var KeyActivateService
     */
    private KeyActivateService $keyActivateService;
    /**
     * @var KeyActivateRepository
     */
    private KeyActivateRepository $keyActivateRepository;

    // Константы для оптимизации
    private const PACK_CACHE_TIME = 3600; // 1 час кэша для пакетов

    public function __construct(
        DatabaseLogger        $logger,
        KeyActivateService    $keyActivateService,
        KeyActivateRepository $keyActivateRepository
    )
    {
        $this->logger = $logger;
        $this->keyActivateService = $keyActivateService;
        $this->keyActivateRepository = $keyActivateRepository;
    }

    /**
     * Display a listing of key activates
     *
     * @param Request $request
     * @return Application|Factory|View
     * @throws Exception
     */
    public function index(Request $request)
    {
        try {
            // Временное увеличение лимита памяти для диагностики
            if (app()->environment('local')) {
                ini_set('memory_limit', '256M');
            }

            $filters = array_filter($request->only(['id', 'pack_id', 'status', 'user_tg_id', 'telegram_id']));

            // Добавляем pack_salesman_id в фильтры, если он есть
            if ($request->has('pack_salesman_id')) {
                $filters['pack_salesman_id'] = $request->pack_salesman_id;
            }

            // ОПТИМИЗАЦИЯ: Используем кэширование для packs, но сохраняем объекты для совместимости
            $packs = Cache::remember('packs_list_full', self::PACK_CACHE_TIME, function () {
                return Pack::select(['id', 'title', 'price', 'period', 'traffic_limit', 'status'])
                    ->orderBy('title')
                    ->get();
            });

            $statuses = [
                KeyActivate::EXPIRED => 'Просрочен',
                KeyActivate::ACTIVE => 'Активирован',
                KeyActivate::PAID => 'Оплачен',
                KeyActivate::DELETED => 'Удален'
            ];

            // Получаем данные с пагинацией (добавляем лимит записей)
            $activate_keys = $this->keyActivateService->getPaginatedWithPack($filters, 25); // Ограничиваем 50 записями на странице

            // Логируем использование памяти для отладки
            if (app()->environment('local')) {
                Log::debug('Memory usage after query: ' . round(memory_get_usage() / 1024 / 1024, 2) . ' MB');
                Log::debug('Records count: ' . $activate_keys->total());
            }

            return view('module.key-activate.index', [
                'activate_keys' => $activate_keys,
                'packs' => $packs,
                'statuses' => $statuses,
                'filters' => $filters
            ]);

        } catch (Exception $e) {
            $this->logger->error('Ошибка при просмотре списка активированных ключей', [
                'source' => 'key_activate',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Показываем страницу с пустыми данными и сообщением об ошибке
            return view('module.key-activate.index', [
                'activate_keys' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50),
                'packs' => [],
                'statuses' => [
                    KeyActivate::EXPIRED => 'Просрочен',
                    KeyActivate::ACTIVE => 'Активирован',
                    KeyActivate::PAID => 'Оплачен',
                    KeyActivate::DELETED => 'Удален'
                ],
                'filters' => $filters ?? [],
                'error' => 'Произошла ошибка при загрузке данных: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Display the specified key activate
     *
     * @param KeyActivate $key
     * @return Application|Factory|View
     */
    public function show(KeyActivate $key): View
    {
        // ОПТИМИЗАЦИЯ: Загружаем отношения с выбором только нужных полей
        $key->load([
            'packSalesman:id,pack_id,salesman_id' => [
                'pack:id,title,price,period',
                'salesman:id,name,telegram_id'
            ],
            'keyActivateUser:id,server_user_id,key_activate_id' => [
                'serverUser:id,panel_id,username' => [
                    'panel:id,name,panel'
                ]
            ]
        ]);
        return view('module.key-activate.show', compact('key'));
    }

    /**
     * Remove the specified key activate
     * @param string $id
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            /**
             * @var KeyActivate $key
             */
            // ОПТИМИЗАЦИЯ: Выбираем только необходимые поля и отношения
            $key = KeyActivate::with([
                'keyActivateUser:id,server_user_id,key_activate_id' => [
                    'serverUser:id,panel_id,username' => [
                        'panel:id,panel'
                    ]
                ]
            ])->findOrFail($id);

            $this->logger->info('Начало удаления ключа активации', [
                'key_id' => $id,
                'key_activate_user_exists' => $key->keyActivateUser ? 'yes' : 'no',
                'server_user_exists' => $key->keyActivateUser && $key->keyActivateUser->serverUser ? 'yes' : 'no'
            ]);

            // Если есть связанный пользователь на сервере, удаляем его
            if ($key->keyActivateUser && $key->keyActivateUser->serverUser && $key->keyActivateUser->serverUser->panel) {
                $serverUser = $key->keyActivateUser->serverUser;
                $panel = $serverUser->panel;

                try {
                    $panelStrategy = new PanelStrategy($panel->panel);
                    $panelStrategy->deleteServerUser($panel->id, $serverUser->id);

                    $this->logger->info('Удаление пользователя из панели выполнено', [
                        'key_id' => $id,
                        'panel_id' => $panel->id,
                        'server_user_id' => $serverUser->id
                    ]);
                } catch (Exception $e) {
                    $this->logger->error('Ошибка при удалении пользователя из панели', [
                        'key_id' => $id,
                        'panel_id' => $panel->id,
                        'server_user_id' => $serverUser->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw $e; // Прерываем процесс, если не удалось удалить пользователя из панели
                }
            }

            // Удаляем KeyActivate
            try {
                $key->delete();
                $this->logger->info('KeyActivate удален', [
                    'key_id' => $id
                ]);
            } catch (Exception $e) {
                $this->logger->error('Ошибка при удалении KeyActivate', [
                    'key_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

            return response()->json(['message' => 'Ключ успешно удален']);
        } catch (Exception $e) {
            $this->logger->error('Общая ошибка при удалении ключа активации', [
                'source' => 'key_activate',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'key_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Ошибка при удалении ключа: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Test activation of the key (development only)
     * @param KeyActivate $key
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function testActivate(KeyActivate $key): JsonResponse
    {
        try {
            // Проверяем статус ключа
            if (!$this->keyActivateRepository->hasCorrectStatusForActivation($key)) {
                $this->logger->warning('Попытка активации ключа с неверным статусом', [
                    'source' => 'key_activate',
                    'action' => 'test_activate',
                    'user_id' => auth()->id(),
                    'key_id' => $key->id,
                    'current_status' => $key->status
                ]);
                return response()->json(['message' => 'Ключ не может быть активирован (неверный статус)'], 400);
            }

            // Тестовый Telegram ID для активации
            $testTgId = rand(10000000, 99999999);

            $this->logger->info('Начало тестовой активации ключа', [
                'source' => 'key_activate',
                'action' => 'test_activate',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'test_tg_id' => $testTgId,
                'key_status' => $key->status,
                'deleted_at' => $key->deleted_at
            ]);

            $activatedKey = $this->keyActivateService->activate($key, $testTgId);

            $this->logger->info('Тестовая активация ключа выполнена успешно', [
                'source' => 'key_activate',
                'action' => 'test_activate',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'test_tg_id' => $testTgId,
                'new_status' => $activatedKey->status
            ]);

            return response()->json([
                'message' => 'Ключ успешно активирован',
                'key' => [
                    'id' => $activatedKey->id,
                    'status' => $activatedKey->status,
                    'user_tg_id' => $activatedKey->user_tg_id,
                    'deleted_at' => $activatedKey->deleted_at,
                    // 'activated_at' => $activatedKey->activated_at // поле не существует в БД
                ]
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при тестовой активации ключа', [
                'source' => 'key_activate',
                'action' => 'test_activate',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'key_status' => $key->status,
                'deleted_at' => $key->deleted_at
            ]);

            return response()->json([
                'message' => $e->getMessage(),
                'key' => [
                    'id' => $key->id,
                    'status' => $key->status,
                    'deleted_at' => $key->deleted_at
                ]
            ], 400);
        }
    }

    /**
     * Перевыпуск просроченного ключа
     *
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function renew(Request $request): JsonResponse
    {
        // КРИТИЧНО: Логируем САМЫМ ПЕРВЫМ действием
        error_log("=== RENEW START ===");
        error_log("Request data: " . json_encode($request->all()));
        
        Log::emergency('🚨 RENEW КОНТРОЛЛЕР ВЫЗВАН', [
            'request_data' => $request->all(),
            'user_id' => auth()->id(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        try {
            error_log("Validation start");
            $validated = $request->validate([
                'key_id' => 'required|uuid|exists:key_activate,id'
            ]);
            error_log("Validation passed: " . json_encode($validated));

            Log::emergency('🔍 Валидация пройдена', ['key_id' => $validated['key_id']]);

            // Загружаем ключ БЕЗ отношений сначала
            error_log("Loading key WITHOUT relations: " . $validated['key_id']);
            Log::emergency('🔄 Начинаем загрузку ключа БЕЗ отношений', ['key_id' => $validated['key_id']]);
            
            /** @var KeyActivate $key */
            $key = KeyActivate::findOrFail($validated['key_id']);
            
            error_log("Key loaded (basic): " . $key->id . ", status: " . $key->status);
            Log::emergency('📦 Базовый ключ загружен', [
                'key_id' => $key->id,
                'status' => $key->status,
                'user_tg_id' => $key->user_tg_id,
                'pack_salesman_id' => $key->pack_salesman_id
            ]);
            
            // Теперь загружаем отношения по одному
            error_log("Loading keyActivateUser relation");
            Log::emergency('🔄 Загружаем keyActivateUser');
            try {
                $key->load('keyActivateUser');
                Log::emergency('✅ keyActivateUser загружен', ['has_relation' => $key->keyActivateUser !== null]);
            } catch (\Throwable $e) {
                Log::emergency('❌ Ошибка загрузки keyActivateUser', ['error' => $e->getMessage()]);
            }
            
            error_log("Loading packSalesman relation");
            Log::emergency('🔄 Загружаем packSalesman');
            try {
                $key->load('packSalesman.salesman');
                Log::emergency('✅ packSalesman загружен', ['has_relation' => $key->packSalesman !== null]);
            } catch (\Throwable $e) {
                Log::emergency('❌ Ошибка загрузки packSalesman', ['error' => $e->getMessage()]);
            }
            
            Log::emergency('🎯 Все отношения обработаны');

            // Проверяем, что ключ просрочен
            error_log("Checking key status: " . $key->status . " (EXPIRED = " . KeyActivate::EXPIRED . ")");
            if ($key->status !== KeyActivate::EXPIRED) {
                error_log("Status check FAILED - key is not expired");
                Log::emergency('❌ Ключ не просрочен', ['status' => $key->status]);
                return response()->json([
                    'success' => false,
                    'message' => 'Ключ не может быть перевыпущен. Только просроченные ключи могут быть перевыпущены.'
                ], 400);
            }
            error_log("Status check passed - key is expired");

            // Проверяем, что есть user_tg_id
            error_log("Checking user_tg_id: " . ($key->user_tg_id ?? 'NULL'));
            if (!$key->user_tg_id) {
                error_log("user_tg_id check FAILED");
                Log::emergency('❌ Нет user_tg_id', ['key_id' => $key->id]);
                return response()->json([
                    'success' => false,
                    'message' => 'Нельзя перевыпустить ключ без привязки к пользователю Telegram'
                ], 400);
            }
            error_log("user_tg_id check passed");

            $this->logger->info('Начало перевыпуска ключа', [
                'source' => 'key_activate',
                'action' => 'renew',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'user_tg_id' => $key->user_tg_id,
                'traffic_limit' => $key->traffic_limit,
                'finish_at' => $key->finish_at
            ]);

            error_log("Calling KeyActivateService->renew()");
            Log::emergency('🔄 Вызов сервиса renew', ['key_id' => $key->id]);
            
            try {
                $renewedKey = $this->keyActivateService->renew($key);
                error_log("Service renew SUCCESS");
                Log::emergency('✅ Сервис renew выполнен успешно', ['key_id' => $renewedKey->id]);
            } catch (\Throwable $serviceException) {
                error_log("Service renew FAILED: " . $serviceException->getMessage());
                error_log("Exception class: " . get_class($serviceException));
                error_log("File: " . $serviceException->getFile() . ":" . $serviceException->getLine());
                error_log("Trace: " . $serviceException->getTraceAsString());
                
                Log::emergency('❌❌❌ ОШИБКА В СЕРВИСЕ RENEW', [
                    'source' => 'key_activate',
                    'action' => 'renew',
                    'user_id' => auth()->id(),
                    'key_id' => $key->id,
                    'error' => $serviceException->getMessage(),
                    'error_class' => get_class($serviceException),
                    'file' => $serviceException->getFile(),
                    'line' => $serviceException->getLine(),
                    'trace' => substr($serviceException->getTraceAsString(), 0, 1000)
                ]);
                
                $this->logger->error('Ошибка в KeyActivateService->renew()', [
                    'source' => 'key_activate',
                    'action' => 'renew',
                    'user_id' => auth()->id(),
                    'key_id' => $key->id,
                    'error' => $serviceException->getMessage(),
                    'error_class' => get_class($serviceException),
                    'file' => $serviceException->getFile(),
                    'line' => $serviceException->getLine()
                ]);
                throw $serviceException;
            }

            $this->logger->info('Перевыпуск ключа выполнен успешно', [
                'source' => 'key_activate',
                'action' => 'renew',
                'user_id' => auth()->id(),
                'key_id' => $renewedKey->id,
                'new_status' => $renewedKey->status
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ключ успешно перевыпущен',
                'key' => [
                    'id' => $renewedKey->id,
                    'status' => $renewedKey->status,
                    'status_text' => $renewedKey->getStatusText()
                ]
            ]);

        } catch (\Throwable $e) {
            error_log("=== CATCH IN CONTROLLER ===");
            error_log("Error: " . $e->getMessage());
            error_log("Class: " . get_class($e));
            error_log("File: " . $e->getFile() . ":" . $e->getLine());
            
            $errorMessage = $e->getMessage();
            $errorClass = get_class($e);
            
            // МНОЖЕСТВЕННОЕ ЛОГИРОВАНИЕ для гарантии
            
            // 1. Laravel Log
            Log::emergency('❌❌❌ ОШИБКА ПЕРЕВЫПУСКА В КОНТРОЛЛЕРЕ', [
                'source' => 'key_activate',
                'action' => 'renew',
                'user_id' => auth()->id(),
                'key_id' => $request->input('key_id'),
                'error' => $errorMessage,
                'error_class' => $errorClass,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 2. DatabaseLogger
            $this->logger->error('ОШИБКА ПЕРЕВЫПУСКА (DatabaseLogger)', [
                'source' => 'key_activate',
                'action' => 'renew',
                'user_id' => auth()->id(),
                'key_id' => $request->input('key_id'),
                'error' => $errorMessage,
                'error_class' => $errorClass,
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            
            // 3. PHP error_log (на случай если Laravel логи не пишутся)
            error_log("[RENEW ERROR] {$errorClass}: {$errorMessage} in {$e->getFile()}:{$e->getLine()}");
            error_log("[RENEW ERROR TRACE] " . $e->getTraceAsString());

            // Более понятное сообщение для пользователя
            $userMessage = 'Ошибка при перевыпуске ключа';
            if (!empty($errorMessage)) {
                $userMessage .= ': ' . $errorMessage;
            } else {
                $userMessage .= ' (тип ошибки: ' . $errorClass . ')';
            }

            return response()->json([
                'success' => false,
                'message' => $userMessage,
                'debug' => [
                    'error_class' => $errorClass,
                    'file' => basename($e->getFile()),
                    'line' => $e->getLine()
                ]
            ], 500);
        }
    }

    /**
     * Update date for the specified key activate
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateDate(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'id' => 'required|uuid|exists:key_activate,id',
                'type' => 'required|in:finish_at',
                'value' => 'required|integer'
            ]);

            // ОПТИМИЗАЦИЯ: Обновляем напрямую через запрос
            $affected = KeyActivate::where('id', $validated['id'])
                ->update([$validated['type'] => $validated['value']]);

            if ($affected) {
                $this->logger->info('Обновление даты для ключа активации', [
                    'source' => 'key_activate',
                    'action' => 'update_date',
                    'user_id' => auth()->id(),
                    'key_id' => $validated['id'],
                    'field' => $validated['type'],
                    'new_value' => $validated['value']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Дата успешно обновлена'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении даты ключа активации', [
                'source' => 'key_activate',
                'action' => 'update_date',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении даты'
            ], 500);
        }
    }
}
