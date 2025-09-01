<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Panel\Panel;
use App\Services\Panel\marzban\MarzbanService;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ServerUserTransferController extends Controller
{
    /**
     * @var MarzbanService
     */
    private MarzbanService $marzbanService;

    public function __construct(MarzbanService $marzbanService)
    {
        $this->marzbanService = $marzbanService;
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

            $currentPanelId = $key->keyActivateUser->serverUser->panel_id;

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

            // Проверяем, что панели разные
            if ($sourcePanel_id === $validated['target_panel_id']) {
                return response()->json(['message' => 'Source and target panels are the same'], 400);
            }

            // Выполняем перенос
            $updatedKey = $this->marzbanService->transferUser(
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
