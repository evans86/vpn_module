<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Panel\Panel;
use App\Models\ServerUser\ServerUser;
use App\Repositories\Panel\PanelRepository;
use App\Jobs\MultiProviderMigrationBatchJob;
use App\Services\Key\KeyActivateService;
use App\Services\Key\MultiProviderMigrationService;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ServerUserTransferController extends Controller
{

    /**
     * Слоты ключа (панели/провайдеры) для выбора при переносе при мульти-провайдере.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getKeySlots(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'key_id' => 'required|uuid|exists:key_activate,id',
            ]);

            $slots = KeyActivateUser::query()
                ->where('key_activate_id', $validated['key_id'])
                ->with(['serverUser.panel.server:id,name,provider', 'serverUser.panel:id,panel_adress,server_id'])
                ->get()
                ->map(function (KeyActivateUser $kau) {
                    $panel = $kau->serverUser ? $kau->serverUser->panel : null;
                    $server = $panel ? $panel->server : null;
                    return [
                        'panel_id' => $panel ? $panel->id : null,
                        'server_name' => $server ? $server->name : ('Панель #' . ($panel ? $panel->id : '?')),
                        'provider' => $server ? $server->provider : '',
                    ];
                })
                ->filter(fn ($s) => !empty($s['panel_id']))
                ->values()
                ->all();

            return response()->json(['slots' => $slots]);
        } catch (Exception $e) {
            Log::error('Failed to get key slots', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);
            return response()->json(['message' => $e->getMessage(), 'slots' => []], 500);
        }
    }

    /**
     * Одним запросом: слоты ключа и список панелей для переноса (исключены панели, где ключ уже есть).
     */
    public function getTransferData(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'key_id' => 'required|uuid|exists:key_activate,id',
            ]);
            $keyId = $validated['key_id'];

            $slots = KeyActivateUser::query()
                ->where('key_activate_id', $keyId)
                ->with(['serverUser.panel.server:id,name,provider', 'serverUser.panel:id,panel_adress,server_id'])
                ->get()
                ->map(function (KeyActivateUser $kau) {
                    $panel = $kau->serverUser ? $kau->serverUser->panel : null;
                    $server = $panel ? $panel->server : null;
                    return [
                        'panel_id' => $panel ? $panel->id : null,
                        'server_name' => $server ? $server->name : ('Панель #' . ($panel ? $panel->id : '?')),
                        'provider' => $server ? $server->provider : '',
                    ];
                })
                ->filter(function ($s) {
                    return !empty($s['panel_id']);
                })
                ->values()
                ->all();

            $panelIdsWithKey = KeyActivateUser::query()
                ->where('key_activate_id', $keyId)
                ->join('server_user', 'key_activate_user.server_user_id', '=', 'server_user.id')
                ->select('server_user.panel_id')
                ->pluck('panel_id')
                ->unique()
                ->values()
                ->all();

            $panels = Panel::select('id', 'panel_status', 'server_id')
                ->with(['server' => function ($query) {
                    $query->select('id', 'name', 'provider');
                }])
                ->where('panel_status', 2)
                ->when(!empty($panelIdsWithKey), function ($query) use ($panelIdsWithKey) {
                    return $query->whereNotIn('id', $panelIdsWithKey);
                })
                ->get()
                ->map(function ($panel) {
                    $server = $panel->server;
                    return [
                        'id' => $panel->id,
                        'server_name' => $server ? $server->name : 'Неизвестный сервер',
                        'provider' => $server ? $server->provider : '',
                    ];
                });

            return response()->json(['slots' => $slots, 'panels' => $panels]);
        } catch (Exception $e) {
            Log::error('Failed to get transfer data', [
                'error' => $e->getMessage(),
                'key_id' => $request->input('key_id'),
            ]);
            return response()->json([
                'message' => $e->getMessage(),
                'slots' => [],
                'panels' => [],
            ], 500);
        }
    }

    /**
     * Получить список доступных панелей для переноса
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPanels(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'key_id' => 'required|uuid|exists:key_activate,id',
                'source_panel_id' => 'nullable|integer|exists:panel,id',
            ]);

            // Исключаем все панели, на которых у этого ключа уже есть слот (не только исходную)
            $panelIdsWithKey = KeyActivateUser::query()
                ->where('key_activate_id', $validated['key_id'])
                ->join('server_user', 'key_activate_user.server_user_id', '=', 'server_user.id')
                ->select('server_user.panel_id')
                ->pluck('panel_id')
                ->unique()
                ->values()
                ->all();

            $panels = Panel::select('id', 'panel_status', 'server_id')
                ->with(['server' => function ($query) {
                    $query->select('id', 'name', 'provider');
                }])
                ->where('panel_status', 2)
                ->when(!empty($panelIdsWithKey), function ($query) use ($panelIdsWithKey) {
                    return $query->whereNotIn('id', $panelIdsWithKey);
                })
                ->get();

            $result = $panels->map(function ($panel) {
                $server = $panel->server;
                return [
                    'id' => $panel->id,
                    'server_name' => $server ? $server->name : 'Неизвестный сервер',
                    'provider' => $server ? $server->provider : '',
                ];
            });

            return response()->json(['panels' => $result]);
        } catch (Exception $e) {
            Log::error('Failed to get panels list', [
                'error' => $e->getMessage(),
                'key_id' => $request->input('key_id'),
            ]);
            return response()->json(['message' => 'Failed to get panels list: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Перенести ключ на другую панель
     *
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function transfer(Request $request): JsonResponse
    {
        // Увеличиваем лимит памяти для операции переноса
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '256M');

        try {
            Log::info('Incoming request data:', [
                'all' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            $validated = $request->validate([
                'key_id' => 'required|uuid|exists:key_activate,id',
                'target_panel_id' => 'required|integer|exists:panel,id',
                'source_panel_id' => 'nullable|integer|exists:panel,id',
            ]);

            $keyId = $validated['key_id'];

            // При мульти-провайдере переносим слот с выбранной исходной панели; иначе — первый слот ключа
            $keyActivateUser = KeyActivateUser::query()
                ->select('server_user_id')
                ->where('key_activate_id', $keyId);
            if (!empty($validated['source_panel_id'])) {
                $keyActivateUser = $keyActivateUser->whereHas(
                    'serverUser',
                    fn ($q) => $q->where('panel_id', (int) $validated['source_panel_id'])
                );
            }
            $keyActivateUser = $keyActivateUser->firstOrFail();

            $serverUser = ServerUser::select('panel_id')->where('id', $keyActivateUser->server_user_id)->firstOrFail();
            $sourcePanel_id = (int) $serverUser->panel_id;
            
            // Загружаем только необходимые поля панелей
            // api_address - это accessor, используем panel_adress для select
            $sourcePanel = Panel::select('id', 'panel', 'panel_adress', 'auth_token', 'server_id')
                ->findOrFail($sourcePanel_id);
            $targetPanel = Panel::select('id', 'panel', 'panel_adress', 'auth_token', 'server_id')
                ->findOrFail($validated['target_panel_id']);

            // Проверяем, что панели разные
            if ($sourcePanel_id === $validated['target_panel_id']) {
                return response()->json(['message' => 'Source and target panels are the same'], 400);
            }

            // Проверяем, что обе панели одного типа (перенос между разными типами не поддерживается)
            if ($sourcePanel->panel !== $targetPanel->panel) {
                return response()->json(['message' => 'Cannot transfer between different panel types'], 400);
            }

            // Используем стратегию для переноса пользователя (передаём server_user_id выбранного слота)
            $panelStrategy = new PanelStrategy($sourcePanel->panel);
            $panelStrategy->transferUser(
                $sourcePanel_id,
                (int) $validated['target_panel_id'],
                $keyActivateUser->server_user_id
            );

            // Освобождаем память
            unset($keyActivateUser, $serverUser, $sourcePanel, $targetPanel, $panelStrategy);
            
            return response()->json([
                'message' => 'Key transferred successfully',
                'key' => [
                    'id' => $validated['key_id'],
                    'new_panel_id' => $validated['target_panel_id'],
                ]
            ]);

        } catch (RuntimeException $e) {
            Log::error('Failed to transfer key', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json(['message' => $e->getMessage()], 500);
        } catch (Exception $e) {
            Log::error('Unexpected error during key transfer', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
            ]);
            return response()->json(['message' => 'Failed to transfer key'], 500);
        } finally {
            // Восстанавливаем оригинальный лимит памяти
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Страница массового переноса ключей (исходная панель недоступна — перенос только по данным из БД).
     */
    public function massTransferPage(): View
    {
        $panels = Panel::query()
            ->select('id', 'panel', 'panel_adress', 'panel_status', 'server_id')
            ->with(['server:id,name'])
            ->orderBy('id')
            ->get();

        return view('module.server-user-transfer.mass-transfer', [
            'panels' => $panels,
        ]);
    }

    /**
     * Количество активных ключей на панели (для отображения на форме массового переноса).
     */
    public function massTransferKeyCount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'panel_id' => 'required|integer|exists:panel,id',
        ]);

        $marzbanService = app(MarzbanService::class);
        $keyIds = $marzbanService->getActiveKeyIdsOnPanel((int) $validated['panel_id']);

        return response()->json(['count' => $keyIds->count()]);
    }

    /**
     * Выполнить массовый перенос ключей с исходной панели на целевую (без обращения к исходной панели).
     */
    public function massTransfer(Request $request): JsonResponse
    {
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $validated = $request->validate([
                'source_panel_id' => 'required|integer|exists:panel,id',
                'target_panel_id' => 'required|integer|exists:panel,id',
            ]);

            $sourcePanelId = (int) $validated['source_panel_id'];
            $targetPanelId = (int) $validated['target_panel_id'];

            if ($sourcePanelId === $targetPanelId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Исходная и целевая панели должны отличаться.',
                ], 400);
            }

            $targetPanel = Panel::find($targetPanelId);
            if (!$targetPanel || $targetPanel->panel !== \App\Models\Panel\Panel::MARZBAN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Целевая панель должна быть типа Marzban.',
                ], 400);
            }

            $marzbanService = app(MarzbanService::class);
            $keyIds = $marzbanService->getActiveKeyIdsOnPanel($sourcePanelId);

            if ($keyIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'На исходной панели нет активных ключей для переноса.',
                    'transferred' => 0,
                    'failed' => 0,
                    'errors' => [],
                ]);
            }

            $transferred = 0;
            $errors = [];

            foreach ($keyIds as $keyActivateId) {
                try {
                    $marzbanService->transferUserWithoutSourcePanel($sourcePanelId, $targetPanelId, $keyActivateId);
                    $transferred++;
                } catch (Exception $e) {
                    Log::warning('Mass transfer: failed key', [
                        'key_activate_id' => $keyActivateId,
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = [
                        'key_id' => $keyActivateId,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Перенос завершён: {$transferred} из " . $keyIds->count() . " ключей.",
                'transferred' => $transferred,
                'failed' => count($errors),
                'errors' => $errors,
            ]);
        } catch (Exception $e) {
            Log::error('Mass transfer failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка массового переноса: ' . $e->getMessage(),
                'transferred' => 0,
                'failed' => 0,
                'errors' => [],
            ], 500);
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Обработать одну порцию ключей (для массового переноса без таймаута при 5000+ ключах).
     * Каждый запрос переносит до batch_size ключей. Фронтенд вызывает повторно, пока done !== true.
     */
    public function massTransferBatch(Request $request): JsonResponse
    {
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $validated = $request->validate([
                'source_panel_id' => 'required|integer|exists:panel,id',
                'target_panel_id' => 'required|integer|exists:panel,id',
                'batch_size' => 'sometimes|integer|min:10|max:200',
                'max_total' => 'nullable|integer|min:1|max:20', // тест: перенести не больше N ключей; пусто = без лимита
            ]);

            $sourcePanelId = (int) $validated['source_panel_id'];
            $targetPanelId = (int) $validated['target_panel_id'];
            $batchSize = (int) ($validated['batch_size'] ?? 100);
            $maxTotal = !empty($validated['max_total']) ? (int) $validated['max_total'] : null;

            if ($sourcePanelId === $targetPanelId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Исходная и целевая панели должны отличаться.',
                ], 400);
            }

            $targetPanel = Panel::find($targetPanelId);
            if (!$targetPanel || $targetPanel->panel !== \App\Models\Panel\Panel::MARZBAN) {
                return response()->json([
                    'success' => false,
                    'message' => 'Целевая панель должна быть типа Marzban.',
                ], 400);
            }

            $marzbanService = app(MarzbanService::class);
            $keyIds = $marzbanService->getActiveKeyIdsOnPanel($sourcePanelId);
            $limit = $maxTotal !== null ? min($batchSize, $maxTotal) : $batchSize;
            $chunk = $keyIds->take($limit);

            if ($chunk->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'done' => true,
                    'transferred' => 0,
                    'failed' => 0,
                    'errors' => [],
                    'transferred_keys' => [],
                    'message' => 'Нет ключей для переноса в этой порции.',
                ]);
            }

            $transferred = 0;
            $errors = [];
            $transferredReport = [];

            foreach ($chunk as $keyActivateId) {
                try {
                    $serverUser = $marzbanService->transferUserWithoutSourcePanel($sourcePanelId, $targetPanelId, $keyActivateId);
                    $keyActivate = KeyActivate::select('traffic_limit', 'finish_at', 'user_tg_id')->find($keyActivateId);
                    $transferred++;
                    $transferredReport[] = [
                        'key_activate_id' => $keyActivateId,
                        'server_user_id' => $serverUser->id,
                        'traffic_limit_bytes' => (int) ($keyActivate->traffic_limit ?? 0),
                        'traffic_limit_mb' => round((int) ($keyActivate->traffic_limit ?? 0) / 1024 / 1024, 2),
                        'finish_at' => $keyActivate->finish_at ? (int) $keyActivate->finish_at : null,
                        'expire_date' => $keyActivate->finish_at ? date('Y-m-d H:i', (int) $keyActivate->finish_at) : null,
                        'user_tg_id' => $keyActivate->user_tg_id ?? null,
                    ];
                } catch (Exception $e) {
                    Log::warning('Mass transfer batch: failed key', [
                        'key_activate_id' => $keyActivateId,
                        'error' => $e->getMessage(),
                    ]);
                    $errors[] = [
                        'key_id' => $keyActivateId,
                        'message' => $e->getMessage(),
                    ];
                }
            }

            $remainingAfterBatch = $keyIds->count() - $chunk->count();
            $isTestRun = $maxTotal !== null;

            return response()->json([
                'success' => true,
                'done' => $isTestRun || $remainingAfterBatch <= 0,
                'test_run' => $isTestRun,
                'transferred' => $transferred,
                'failed' => count($errors),
                'errors' => $errors,
                'transferred_keys' => $transferredReport,
                'processed_in_batch' => $chunk->count(),
                'remaining' => $remainingAfterBatch,
                'message' => $remainingAfterBatch > 0
                    ? "Обработано {$chunk->count()} ключей, осталось примерно {$remainingAfterBatch}."
                    : "Порция завершена. Перенесено: {$transferred}, ошибок: " . count($errors) . ".",
            ]);
        } catch (Exception $e) {
            Log::error('Mass transfer batch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'done' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
                'transferred' => 0,
                'failed' => 0,
                'errors' => [],
                'transferred_keys' => [],
            ], 500);
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Текущая статистика по панелям (активных пользователей из статистики Marzban — как в «Детальная информация по панелям»).
     */
    public function balanceStats(Request $request): JsonResponse
    {
        $panelRepository = app(PanelRepository::class);
        $counts = $panelRepository->getActiveUserCountPerPanelFromStats();

        $total = array_sum($counts);
        $panelCount = count($counts);
        $average = $panelCount > 0 ? round($total / $panelCount, 0) : 0;

        $maxPanelId = null;
        $minPanelId = null;
        $maxCount = 0;
        $minCount = PHP_INT_MAX;
        foreach ($counts as $panelId => $cnt) {
            if ($cnt > $maxCount) {
                $maxCount = $cnt;
                $maxPanelId = (int) $panelId;
            }
            if ($cnt < $minCount) {
                $minCount = $cnt;
                $minPanelId = (int) $panelId;
            }
        }
        if ($minCount === PHP_INT_MAX) {
            $minCount = 0;
        }

        return response()->json([
            'success' => true,
            'counts' => $counts,
            'total' => $total,
            'average' => $average,
            'max_panel_id' => $maxPanelId,
            'min_panel_id' => $minPanelId,
            'max_count' => $maxCount,
            'min_count' => $minCount,
            'diff' => $maxPanelId !== null ? $maxCount - $minCount : 0,
        ]);
    }

    /**
     * Один шаг выравнивания: перенос порции ключей с самой загруженной панели на наименее загруженную.
     */
    public function balanceStep(Request $request): JsonResponse
    {
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $validated = $request->validate([
                'batch_size' => 'sometimes|integer|min:10|max:150',
                'max_diff_threshold' => 'sometimes|integer|min:0|max:5000',
            ]);

            $batchSize = (int) ($validated['batch_size'] ?? 50);
            $maxDiffThreshold = (int) ($validated['max_diff_threshold'] ?? 100);

            $panelRepository = app(PanelRepository::class);
            $counts = $panelRepository->getActiveUserCountPerPanelFromStats();

            if (count($counts) < 2) {
                return response()->json([
                    'success' => true,
                    'done' => true,
                    'moved' => 0,
                    'message' => 'Нужно минимум 2 панели для выравнивания.',
                    'counts' => $panelRepository->getActiveUserCountPerPanelFromStats(),
                ]);
            }

            $maxPanelId = null;
            $minPanelId = null;
            $maxCount = 0;
            $minCount = PHP_INT_MAX;
            foreach ($counts as $panelId => $cnt) {
                if ($cnt > $maxCount) {
                    $maxCount = $cnt;
                    $maxPanelId = (int) $panelId;
                }
                if ($cnt < $minCount) {
                    $minCount = $cnt;
                    $minPanelId = (int) $panelId;
                }
            }
            if ($minCount === PHP_INT_MAX) {
                $minCount = 0;
            }

            $diff = $maxCount - $minCount;
            if ($diff <= $maxDiffThreshold || $maxPanelId === null || $minPanelId === null || $maxPanelId === $minPanelId) {
                return response()->json([
                    'success' => true,
                    'done' => true,
                    'moved' => 0,
                    'message' => $diff <= $maxDiffThreshold ? 'Нагрузка уже выровнена (разница ≤ ' . $maxDiffThreshold . ').' : 'Нечего переносить.',
                    'counts' => $panelRepository->getActiveUserCountPerPanelFromStats(),
                ]);
            }

            $marzbanService = app(MarzbanService::class);
            $keyIds = $marzbanService->getActiveKeyIdsOnPanel($maxPanelId);
            $chunk = $keyIds->take($batchSize);

            if ($chunk->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'done' => true,
                    'moved' => 0,
                    'message' => 'Нет ключей для переноса на самой загруженной панели.',
                    'counts' => $panelRepository->getActiveUserCountPerPanelFromStats(),
                ]);
            }

            $moved = 0;
            $errors = [];
            foreach ($chunk as $keyActivateId) {
                try {
                    $marzbanService->transferUserWithoutSourcePanel($maxPanelId, $minPanelId, $keyActivateId);
                    $moved++;
                } catch (Exception $e) {
                    Log::warning('Balance step: failed key', ['key_activate_id' => $keyActivateId, 'error' => $e->getMessage()]);
                    $errors[] = ['key_id' => $keyActivateId, 'message' => $e->getMessage()];
                }
            }

            $newCounts = $panelRepository->getActiveUserCountPerPanelFromStats();
            $newMax = $newCounts ? max($newCounts) : 0;
            $newMin = $newCounts ? min($newCounts) : 0;
            $newDiff = $newMax - $newMin;

            return response()->json([
                'success' => true,
                'done' => $newDiff <= $maxDiffThreshold,
                'moved' => $moved,
                'from_panel_id' => $maxPanelId,
                'to_panel_id' => $minPanelId,
                'counts' => $newCounts,
                'message' => "Перенесено {$moved} ключей с панели #{$maxPanelId} на панель #{$minPanelId}. Разница: {$newDiff}.",
                'errors' => $errors,
            ]);
        } catch (Exception $e) {
            Log::error('Balance step failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json([
                'success' => false,
                'done' => false,
                'moved' => 0,
                'message' => 'Ошибка: ' . $e->getMessage(),
                'counts' => [],
            ], 500);
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Проверить один ключ для мульти-провайдера: существует, активен, есть слоты; показать текущие и недостающие провайдеры.
     */
    public function multiProviderMigrationCheckKey(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'key_id' => 'required|string|max:64',
            ]);
            $keyId = trim($validated['key_id']);

            $slots = config('panel.multi_provider_slots', []);
            $slots = is_array($slots) ? $slots : [];
            if (empty($slots)) {
                return response()->json([
                    'success' => false,
                    'valid' => false,
                    'message' => 'Мульти-провайдер отключён. Задайте PANEL_MULTI_PROVIDER_SLOTS в .env.',
                ], 400);
            }

            $key = KeyActivate::query()
                ->where('id', $keyId)
                ->with(['keyActivateUsers.serverUser.panel.server'])
                ->first();

            if (!$key) {
                return response()->json([
                    'success' => true,
                    'valid' => false,
                    'key_id' => $keyId,
                    'message' => 'Ключ не найден.',
                ]);
            }

            if ($key->status !== KeyActivate::ACTIVE) {
                return response()->json([
                    'success' => true,
                    'valid' => false,
                    'key_id' => $keyId,
                    'message' => 'Ключ не активен (статус: ' . $key->status . '). Подходят только ключи со статусом ACTIVE.',
                ]);
            }

            if (!$key->user_tg_id) {
                return response()->json([
                    'success' => true,
                    'valid' => false,
                    'key_id' => $keyId,
                    'message' => 'У ключа нет user_tg_id (не активирован пользователем).',
                ]);
            }

            $existingProviders = [];
            foreach ($key->keyActivateUsers as $kau) {
                if ($kau->serverUser && $kau->serverUser->panel && $kau->serverUser->panel->server) {
                    $p = $kau->serverUser->panel->server->provider;
                    if ($p !== null && $p !== '') {
                        $existingProviders[$p] = true;
                    }
                }
            }
            $existingProviders = array_keys($existingProviders);
            $missingSlots = array_values(array_diff($slots, $existingProviders));
            $canAdd = count($missingSlots) > 0;

            return response()->json([
                'success' => true,
                'valid' => true,
                'key_id' => $key->id,
                'existing_providers' => $existingProviders,
                'required_slots' => $slots,
                'missing_slots' => $missingSlots,
                'can_add' => $canAdd,
                'message' => $canAdd
                    ? 'Ключ подходит. Есть слоты: ' . implode(', ', $existingProviders) . '. Добавить: ' . implode(', ', $missingSlots) . '.'
                    : 'Ключ уже имеет все провайдеры: ' . implode(', ', $existingProviders) . '.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('multiProviderMigrationCheckKey failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Добавить мульти-провайдер к одному ключу (добавить недостающие слоты).
     */
    public function multiProviderMigrationSingleKey(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'key_id' => 'required|string|max:64',
                'dry_run' => 'nullable|boolean',
            ]);
            $keyId = trim($validated['key_id']);
            $dryRun = !empty($validated['dry_run']);

            $slots = config('panel.multi_provider_slots', []);
            $slots = is_array($slots) ? $slots : [];
            if (empty($slots)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Мульти-провайдер отключён. Задайте PANEL_MULTI_PROVIDER_SLOTS в .env.',
                    'added' => 0,
                ], 400);
            }

            $key = KeyActivate::query()
                ->where('id', $keyId)
                ->where('status', KeyActivate::ACTIVE)
                ->whereNotNull('user_tg_id')
                ->whereHas('keyActivateUsers')
                ->first();

            if (!$key) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ключ не найден, не активен или без слотов. Сначала нажмите «Проверить ключ».',
                    'added' => 0,
                ], 404);
            }

            $keyActivateService = app(KeyActivateService::class);
            $added = $keyActivateService->addMissingProviderSlots($key, $dryRun);
            return response()->json([
                'success' => true,
                'added' => $added,
                'dry_run' => $dryRun,
                'message' => $dryRun
                    ? "Проверка: было бы добавлено слотов: {$added}."
                    : "Добавлено слотов: {$added}. Обновите подписку в боте — в ней появятся конфиги со всех провайдеров.",
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Multi-provider single key failed', [
                'key_id' => $request->input('key_id'),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
                'added' => 0,
            ], 500);
        }
    }

    /**
     * Количество активных ключей — кандидатов на миграцию на мульти-провайдер
     * (активные, с user_tg_id и хотя бы одним слотом; для них добавляются недостающие провайдеры).
     */
    public function multiProviderMigrationCount(Request $request): JsonResponse
    {
        try {
            $slots = config('panel.multi_provider_slots', []);
            $slots = is_array($slots) ? $slots : [];
            if (empty($slots)) {
                return response()->json([
                    'success' => true,
                    'count' => 0,
                    'slots' => [],
                    'message' => 'Мульти-провайдер отключён (panel.multi_provider_slots пуст).',
                ]);
            }

            $count = KeyActivate::query()
                ->where('status', KeyActivate::ACTIVE)
                ->whereNotNull('user_tg_id')
                ->whereHas('keyActivateUsers')
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count,
                'slots' => $slots,
            ]);
        } catch (\Throwable $e) {
            Log::error('multiProviderMigrationCount failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'count' => 0,
                'slots' => [],
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Одна порция миграции ключей на мульти-провайдер (добавление недостающих слотов).
     * По принципу массового переноса: порциями, с dry-run и лимитом для теста.
     */
    public function multiProviderMigrationBatch(Request $request): JsonResponse
    {
        $originalMemoryLimit = ini_get('memory_limit');
        ini_set('memory_limit', '2048M');

        try {
            $validated = $request->validate([
                'offset' => 'nullable|integer|min:0',
                'batch_size' => 'sometimes|integer|min:1|max:200',
                'max_total' => 'nullable|integer|min:1', // тест: обработать не больше N ключей
                'dry_run' => 'nullable|boolean',
            ]);

            $slots = config('panel.multi_provider_slots', []);
            $slots = is_array($slots) ? $slots : [];
            if (empty($slots)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Мульти-провайдер отключён. Задайте PANEL_MULTI_PROVIDER_SLOTS в .env.',
                    'done' => true,
                    'added_total' => 0,
                    'processed' => 0,
                    'errors' => [],
                ], 400);
            }

            $offset = (int) ($validated['offset'] ?? 0);
            $batchSize = (int) ($validated['batch_size'] ?? 50);
            $maxTotal = isset($validated['max_total']) && $validated['max_total'] !== '' ? (int) $validated['max_total'] : null;
            $dryRun = !empty($validated['dry_run']);

            $service = app(MultiProviderMigrationService::class);
            $result = $service->runOneBatch($offset, $batchSize, $dryRun, $maxTotal);

            if (!$result['success']) {
                return response()->json(array_merge($result, ['dry_run' => $dryRun]), 400);
            }

            return response()->json([
                'success' => true,
                'done' => $result['done'],
                'dry_run' => $dryRun,
                'added_total' => $result['added_total'],
                'processed' => $result['processed'],
                'next_offset' => $result['next_offset'],
                'remaining' => $result['remaining'],
                'total' => $result['total'],
                'errors' => $result['errors'],
                'message' => $result['message'],
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Multi-provider migration batch failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'success' => false,
                'done' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
                'added_total' => 0,
                'processed' => 0,
                'errors' => [],
            ], 500);
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }

    /**
     * Запустить миграцию в фоне (очередь). Возвращает run_id для опроса статуса.
     * Требуется: QUEUE_CONNECTION=database (или redis) и запущенный php artisan queue:work.
     */
    public function multiProviderMigrationStart(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'batch_size' => 'sometimes|integer|min:1|max:200',
                'dry_run' => 'nullable|boolean',
            ]);
            $batchSize = (int) ($validated['batch_size'] ?? 50);
            $dryRun = !empty($validated['dry_run']);

            $slots = config('panel.multi_provider_slots', []);
            $slots = is_array($slots) ? $slots : [];
            if (empty($slots)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Мульти-провайдер отключён. Задайте PANEL_MULTI_PROVIDER_SLOTS в .env.',
                ], 400);
            }

            $existingRunId = Cache::get('multi_provider_migration_latest_run_id');
            if ($existingRunId) {
                $existing = Cache::get('multi_provider_migration_' . $existingRunId);
                if (!empty($existing) && empty($existing['done'])) {
                    $total = (int) ($existing['total'] ?? 0);
                    $processed = (int) ($existing['processed'] ?? 0);
                    return response()->json([
                        'success' => true,
                        'run_id' => $existingRunId,
                        'message' => 'Миграция уже запущена (' . $processed . ' из ' . $total . '). Чтобы продолжить, запустите воркер на сервере: bash scripts/start-queue-worker-loop.sh',
                    ]);
                }
            }

            $runId = 'mp_' . Str::random(16);
            $cacheKey = 'multi_provider_migration_' . $runId;
            Cache::put($cacheKey, [
                'total' => 0,
                'processed' => 0,
                'added_total' => 0,
                'errors' => [],
                'done' => false,
                'message' => 'Запуск…',
                'started_at' => now()->toIso8601String(),
            ], now()->addHours(3));

            Cache::put('multi_provider_migration_latest_run_id', $runId, now()->addHours(3));

            MultiProviderMigrationBatchJob::dispatch($runId, 0, $batchSize, $dryRun);

            return response()->json([
                'success' => true,
                'run_id' => $runId,
                'message' => 'Миграция поставлена в очередь. Наблюдайте за прогрессом ниже.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Multi-provider migration start failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Запустить параллельную миграцию: N воркеров (срезов), каждый обрабатывает свой диапазон ключей.
     * Требуется очередь (queue:work). Возвращает run_id для опроса — статус агрегируется по всем срезам.
     */
    public function multiProviderMigrationStartParallel(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'num_workers' => 'sometimes|integer|min:2|max:20',
                'batch_size' => 'sometimes|integer|min:50|max:500',
                'dry_run' => 'nullable|boolean',
            ]);
            $numWorkers = (int) ($validated['num_workers'] ?? 10);
            $batchSize = (int) ($validated['batch_size'] ?? 500);
            $dryRun = !empty($validated['dry_run']);

            $slots = config('panel.multi_provider_slots', []);
            $slots = is_array($slots) ? $slots : [];
            if (empty($slots)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Мульти-провайдер отключён. Задайте PANEL_MULTI_PROVIDER_SLOTS в .env.',
                ], 400);
            }

            $service = app(MultiProviderMigrationService::class);
            $totalCount = $service->getTotalCount();
            if ($totalCount < 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'Нет ключей-кандидатов для миграции.',
                ], 400);
            }

            $existingRunId = Cache::get('multi_provider_migration_latest_run_id');
            if ($existingRunId) {
                $parallelMeta = Cache::get('multi_provider_migration_parallel_' . $existingRunId);
                if ($parallelMeta) {
                    $runIds = $parallelMeta['run_ids'] ?? [];
                    $agg = ['processed' => 0, 'added_total' => 0, 'done' => true];
                    foreach ($runIds as $rid) {
                        $p = Cache::get('multi_provider_migration_' . $rid, []);
                        $agg['processed'] += (int) ($p['processed'] ?? 0);
                        $agg['added_total'] += (int) ($p['added_total'] ?? 0);
                        if (empty($p['done'])) {
                            $agg['done'] = false;
                        }
                    }
                    if (!$agg['done']) {
                        return response()->json([
                            'success' => true,
                            'run_id' => $existingRunId,
                            'message' => 'Параллельная миграция уже запущена (' . $agg['processed'] . ' из ' . ($parallelMeta['total'] ?? 0) . '). Запустите воркер: bash scripts/start-queue-worker-loop.sh',
                        ]);
                    }
                } else {
                    $existing = Cache::get('multi_provider_migration_' . $existingRunId);
                    if (!empty($existing) && empty($existing['done'])) {
                        return response()->json([
                            'success' => true,
                            'run_id' => $existingRunId,
                            'message' => 'Миграция уже запущена. Запустите воркер: bash scripts/start-queue-worker-loop.sh',
                        ]);
                    }
                }
            }

            $parentRunId = 'mp_par_' . Str::random(12);
            $sliceSize = (int) ceil($totalCount / $numWorkers);
            $runIds = [];

            for ($i = 0; $i < $numWorkers; $i++) {
                $offset = $i * $sliceSize;
                $endOffset = min(($i + 1) * $sliceSize, $totalCount);
                if ($offset >= $endOffset) {
                    continue;
                }
                $runId = $parentRunId . '_' . $i;
                $runIds[] = $runId;
                Cache::put('multi_provider_migration_' . $runId, [
                    'total' => 0,
                    'processed' => 0,
                    'added_total' => 0,
                    'errors' => [],
                    'done' => false,
                    'message' => 'Срез ' . ($i + 1) . '…',
                    'started_at' => now()->toIso8601String(),
                ], now()->addHours(3));
                MultiProviderMigrationBatchJob::dispatch($runId, $offset, $batchSize, $dryRun, $endOffset);
            }

            Cache::put('multi_provider_migration_parallel_' . $parentRunId, [
                'run_ids' => $runIds,
                'total' => $totalCount,
                'started_at' => now()->toIso8601String(),
            ], now()->addHours(3));
            Cache::put('multi_provider_migration_latest_run_id', $parentRunId, now()->addHours(3));

            return response()->json([
                'success' => true,
                'run_id' => $parentRunId,
                'message' => 'Параллельная миграция запущена: ' . count($runIds) . ' воркеров. Запустите воркер на сервере: bash scripts/start-queue-worker-loop.sh',
            ]);
        } catch (\Throwable $e) {
            Log::error('Multi-provider migration start-parallel failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Отмена текущей или указанной миграции. Останавливает постановку следующих порций в очередь;
     * уже обработанные ключи остаются. Для параллельного запуска отменяются все срезы.
     */
    public function multiProviderMigrationCancel(Request $request): JsonResponse
    {
        $runId = $request->input('run_id');
        if (!$runId || !is_string($runId)) {
            $runId = Cache::get('multi_provider_migration_latest_run_id');
        }
        if (!$runId || !is_string($runId)) {
            return response()->json([
                'success' => false,
                'message' => 'Нет активного запуска для отмены.',
            ], 400);
        }

        $parallelMeta = Cache::get('multi_provider_migration_parallel_' . $runId);
        if ($parallelMeta && !empty($parallelMeta['run_ids'])) {
            foreach ($parallelMeta['run_ids'] as $rid) {
                Cache::put('multi_provider_migration_cancel_' . $rid, true, now()->addHours(1));
            }
        } else {
            Cache::put('multi_provider_migration_cancel_' . $runId, true, now()->addHours(1));
        }

        return response()->json([
            'success' => true,
            'message' => 'Запрос на отмену отправлен. Текущие порции доработают, новые не будут поставлены в очередь.',
        ]);
    }

    /**
     * Статус фоновой миграции по run_id (для опроса с интерфейса).
     * Если run_id не передан — возвращается статус последнего запуска (после перезагрузки страницы).
     */
    public function multiProviderMigrationStatus(Request $request): JsonResponse
    {
        $runId = $request->input('run_id');
        if (!$runId || !is_string($runId)) {
            $runId = Cache::get('multi_provider_migration_latest_run_id');
        }
        if (!$runId || !is_string($runId)) {
            return response()->json([
                'success' => true,
                'run_id' => null,
                'found' => false,
                'message' => 'Нет активного или последнего запуска миграции.',
            ]);
        }

        $parallelMeta = Cache::get('multi_provider_migration_parallel_' . $runId);
        if ($parallelMeta && !empty($parallelMeta['run_ids'])) {
            $total = (int) ($parallelMeta['total'] ?? 0);
            $processed = 0;
            $addedTotal = 0;
            $errors = [];
            $allDone = true;
            $cancelled = false;
            $startedAt = $parallelMeta['started_at'] ?? null;
            foreach ($parallelMeta['run_ids'] as $rid) {
                $p = Cache::get('multi_provider_migration_' . $rid, []);
                $processed += (int) ($p['processed'] ?? 0);
                $addedTotal += (int) ($p['added_total'] ?? 0);
                $errors = array_merge($errors, $p['errors'] ?? []);
                if (empty($p['done'])) {
                    $allDone = false;
                }
                if (!empty($p['cancelled'])) {
                    $cancelled = true;
                }
            }
            $msg = $allDone
                ? ($cancelled ? 'Миграция отменена.' : 'Параллельная миграция завершена.')
                : ('Обработано ' . $processed . ' из ' . $total);
            return response()->json([
                'success' => true,
                'run_id' => $runId,
                'found' => true,
                'total' => $total,
                'processed' => $processed,
                'added_total' => $addedTotal,
                'errors' => $errors,
                'done' => $allDone,
                'cancelled' => $cancelled,
                'message' => $msg,
                'error' => null,
                'started_at' => $startedAt,
            ]);
        }

        $cacheKey = 'multi_provider_migration_' . $runId;
        $progress = Cache::get($cacheKey);
        if (!$progress) {
            return response()->json([
                'success' => true,
                'run_id' => $runId,
                'found' => false,
                'message' => 'Сессия не найдена или уже удалена.',
            ]);
        }
        $total = (int) ($progress['total'] ?? 0);
        $processed = (int) ($progress['processed'] ?? 0);
        $startedAt = $progress['started_at'] ?? null;
        if ($total === 0 && $processed === 0 && $startedAt) {
            try {
                $started = new \DateTimeImmutable($startedAt);
                if ($started->modify('+15 minutes') < new \DateTimeImmutable('now')) {
                    return response()->json([
                        'success' => true,
                        'run_id' => $runId,
                        'found' => false,
                        'message' => 'Предыдущий запуск не обновился (возможно, завершился с ошибкой). Нажмите «Запустить в фоне» для нового запуска.',
                    ]);
                }
            } catch (\Throwable $e) {
                // игнорируем ошибки парсинга даты
            }
        }
        return response()->json([
            'success' => true,
            'run_id' => $runId,
            'found' => true,
            'total' => (int) ($progress['total'] ?? 0),
            'processed' => (int) ($progress['processed'] ?? 0),
            'added_total' => (int) ($progress['added_total'] ?? 0),
            'errors' => $progress['errors'] ?? [],
            'done' => (bool) ($progress['done'] ?? false),
            'cancelled' => (bool) ($progress['cancelled'] ?? false),
            'message' => $progress['message'] ?? '',
            'error' => $progress['error'] ?? null,
            'started_at' => $progress['started_at'] ?? null,
        ]);
    }
}
