<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Panel\Panel;
use App\Models\ServerUser\ServerUser;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ServerUserTransferController extends Controller
{

    /**
     * Получить список доступных панелей для переноса
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPanels(Request $request): JsonResponse
    {
        try {
            Log::info('Incoming request data:', [
                'all' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            $validated = $request->validate([
                'key_id' => 'required|uuid|exists:key_activate,id'
            ]);

            // Оптимизированная загрузка: получаем panel_id напрямую
            $keyActivateUser = KeyActivateUser::select('server_user_id')
                ->where('key_activate_id', $validated['key_id'])
                ->firstOrFail();
            
            $serverUser = ServerUser::select('panel_id')
                ->where('id', $keyActivateUser->server_user_id)
                ->firstOrFail();

            $currentPanelId = $serverUser->panel_id;

            // Получаем все активные панели, кроме текущей (оптимизированная загрузка)
            $panels = Panel::select('id', 'panel_status', 'panel_adress', 'server_id')
                ->with(['server' => function($query) {
                    $query->select('id', 'name');
                }])
                ->where('panel_status', 2)
                ->when($currentPanelId, function ($query) use ($currentPanelId) {
                    return $query->where('id', '!=', $currentPanelId);
                })
                ->get();

            Log::info('Available panels:', [
                'count' => $panels->count(),
                'panels' => $panels->map(fn($panel) => [
                    'id' => $panel->id,
                    'status' => $panel->panel_status,
                    'server_name' => $panel->server->name ?? 'Неизвестный сервер'
                ])
            ]);

            $result = $panels->map(function ($panel) {
                return [
                    'id' => $panel->id,
                    'server_name' => $panel->server->name ?? 'Неизвестный сервер',
                    'address' => $panel->panel_adress ?? $panel->api_address ?? 'Адрес не указан'
                ];
            });

            return response()->json(['panels' => $result]);
        } catch (Exception $e) {
            Log::error('Failed to get panels list', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all()
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
            ]);

            // Оптимизированная загрузка: только необходимые поля
            $key = KeyActivate::select('id')->findOrFail($validated['key_id']);
            
            // Получаем panel_id напрямую через запрос, без загрузки всех связанных моделей
            $keyActivateUser = KeyActivateUser::select('server_user_id')
                ->where('key_activate_id', $key->id)
                ->firstOrFail();
            
            $serverUser = ServerUser::select('panel_id')
                ->where('id', $keyActivateUser->server_user_id)
                ->firstOrFail();
            
            $sourcePanel_id = $serverUser->panel_id;
            
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

            // Используем стратегию для переноса пользователя
            $panelStrategy = new PanelStrategy($sourcePanel->panel);
            $updatedKey = $panelStrategy->transferUser(
                $sourcePanel_id,
                $validated['target_panel_id'],
                $validated['key_id']
            );

            // Освобождаем память
            unset($keyActivateUser, $serverUser, $sourcePanel, $targetPanel, $updatedKey, $panelStrategy);
            
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
                'max_total' => 'sometimes|integer|min:1|max:20', // тест: перенести не больше N ключей и остановиться
            ]);

            $sourcePanelId = (int) $validated['source_panel_id'];
            $targetPanelId = (int) $validated['target_panel_id'];
            $batchSize = (int) ($validated['batch_size'] ?? 100);
            $maxTotal = isset($validated['max_total']) ? (int) $validated['max_total'] : null;

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
                    'message' => 'Нет ключей для переноса в этой порции.',
                ]);
            }

            $transferred = 0;
            $errors = [];

            foreach ($chunk as $keyActivateId) {
                try {
                    $marzbanService->transferUserWithoutSourcePanel($sourcePanelId, $targetPanelId, $keyActivateId);
                    $transferred++;
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
            ], 500);
        } finally {
            ini_set('memory_limit', $originalMemoryLimit);
        }
    }
}
