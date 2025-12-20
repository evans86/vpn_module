<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\KeyActivate\KeyActivate;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Panel\Panel;
use App\Models\ServerUser\ServerUser;
use App\Services\Panel\PanelStrategy;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
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

            // Получаем текущую панель ключа
            /**
             * @var KeyActivate $key
             */
            $key = KeyActivate::with('keyActivateUser.serverUser')->findOrFail($validated['key_id']);

            Log::info('Key data:', [
                'key_id' => $key->id,
                'key_activate_user' => $key->keyActivateUser,
                'server_user' => $key->keyActivateUser->serverUser,
//                'panel_id' => $key->keyActivateUser->serverUser->panel_id
            ]);

            /**
             * @var KeyActivateUser $keyActivateUser
             */
            $keyActivateUser = KeyActivateUser::query()->where('key_activate_id', $key->id)->first();
            /**
             * @var ServerUser $serverUser
             */
            $serverUser = ServerUser::query()->where('id', $keyActivateUser->server_user_id)->first();

            $currentPanelId = $serverUser->panel_id;

            // Получаем все активные панели, кроме текущей
            $panels = Panel::with('server')
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
                    'server_name' => $panel->server->name
                ])
            ]);

            $result = $panels->map(function ($panel) {
                return [
                    'id' => $panel->id,
                    'server_name' => $panel->server->name ?? 'Неизвестный сервер',
                    'address' => $panel->address ?? 'Адрес не указан'
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
        try {
            Log::info('Incoming request data:', [
                'all' => $request->all(),
                'headers' => $request->headers->all()
            ]);

            $validated = $request->validate([
                'key_id' => 'required|uuid|exists:key_activate,id',
                'target_panel_id' => 'required|integer|exists:panel,id',
            ]);

            $key = KeyActivate::findOrFail($validated['key_id']);
            $sourcePanel_id = $key->keyActivateUser->serverUser->panel_id;
            $sourcePanel = \App\Models\Panel\Panel::findOrFail($sourcePanel_id);
            $targetPanel = \App\Models\Panel\Panel::findOrFail($validated['target_panel_id']);

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

            return response()->json([
                'message' => 'Key transferred successfully',
                'key' => [
                    'id' => $updatedKey->id,
                    'new_panel_id' => $updatedKey->panel_id,
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
        }
    }
}
