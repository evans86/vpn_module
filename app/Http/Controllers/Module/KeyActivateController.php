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

class KeyActivateController extends Controller
{
    private DatabaseLogger $logger;
    private KeyActivateService $keyActivateService;
    private KeyActivateRepository $keyActivateRepository;

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
     * @param Request $request
     * @return Application|Factory|View
     * @throws Exception
     */
    public function index(Request $request)
    {
        try {
            $filters = array_filter($request->only(['id', 'pack_id', 'status', 'user_tg_id', 'telegram_id']));

            // Добавляем pack_salesman_id в фильтры, если он есть
            if ($request->has('pack_salesman_id')) {
                $filters['pack_salesman_id'] = $request->pack_salesman_id;
            }

            $packs = Pack::all();
            $statuses = [
                KeyActivate::EXPIRED => 'Просрочен',
                KeyActivate::ACTIVE => 'Активирован',
                KeyActivate::PAID => 'Оплачен',
                KeyActivate::DELETED => 'Удален'
            ];

            $activate_keys = $this->keyActivateService->getPaginatedWithPack($filters);

            $this->logger->info('Просмотр списка активированных ключей', [
                'source' => 'key_activate',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'total_keys' => $activate_keys->total(),
                'page' => $activate_keys->currentPage(),
                'filters' => $filters
            ]);

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

            throw $e;
        }
    }

    /**
     * Display the specified key activate
     * @param KeyActivate $key
     * @return Application|Factory|View
     */
    public function show(KeyActivate $key): View
    {
        $key->load(['packSalesman.pack', 'packSalesman.salesman', 'keyActivateUser.serverUser']);
        return view('module.key-activate.show', compact('key'));
    }

    /**
     * Remove the specified key activate
     * @param KeyActivate $key
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function destroy(KeyActivate $key): JsonResponse
    {
        try {
            // Загружаем связанные данные
            $key->load(['keyActivateUser.serverUser']);

            // Если есть связанный пользователь сервера
            if ($key->keyActivateUser && $key->keyActivateUser->serverUser) {
                $serverUser = $key->keyActivateUser->serverUser;

                // Получаем панель и сервис для работы с ней
                $panel = $serverUser->server->panel;
                $panelStrategy = new PanelStrategy($serverUser->server->panel->panel);
                $panelStrategy->deleteServerUser($serverUser->server->panel->id, $serverUser->id);

                // Удаляем запись пользователя сервера
                $serverUser->delete();
                $serverUser->save();
            }

            // Удаляем ключ активации
            $this->keyActivateRepository->delete($key);

            $this->logger->info('Удаление ключа активации', [
                'source' => 'key_activate',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'key_id' => $key->id
            ]);

            return response()->json(['message' => 'Ключ успешно удален']);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при удалении ключа активации', [
                'source' => 'key_activate',
                'action' => 'delete',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['message' => 'Ошибка при удалении ключа'], 500);
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
                    'activated_at' => $activatedKey->activated_at
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
     * Update key dates
     * @param Request $request
     * @param KeyActivate $key
     * @return JsonResponse
     */
    public function updateDates(Request $request, KeyActivate $key): JsonResponse
    {
        try {
            $validated = $request->validate([
                'finish_at' => 'sometimes|required|date_format:Y-m-d H:i',
                'deleted_at' => 'sometimes|required|date_format:Y-m-d H:i'
            ]);

            $updates = [];

            if (isset($validated['finish_at'])) {
                $updates['finish_at'] = strtotime($validated['finish_at']);
            }

            if (isset($validated['deleted_at'])) {
                $updates['deleted_at'] = strtotime($validated['deleted_at']);
            }

            $key->update($updates);

            $this->logger->info('Обновление дат ключа активации', [
                'source' => 'key_activate',
                'action' => 'update_dates',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'updates' => $updates
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Даты успешно обновлены'
            ]);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении дат ключа активации', [
                'source' => 'key_activate',
                'action' => 'update_dates',
                'user_id' => auth()->id(),
                'key_id' => $key->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обновлении дат: ' . $e->getMessage()
            ], 422);
        }
    }
}
