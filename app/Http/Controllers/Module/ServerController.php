<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Location\Location;
use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ServerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $servers = Server::with('location')->paginate(10);
        $locations = Location::pluck('code', 'id')->mapWithKeys(function ($code, $id) {
            return [$id => $code . ' ' . Location::find($id)->emoji];
        });

        return view('module.server.index', compact('servers', 'locations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Валидация входных данных
            $validated = $request->validate([
                'location_id' => 'required|integer|exists:location,id',
                'provider' => 'required|string|in:' . Server::VDSINA
            ]);

            $strategy = new ServerStrategy($validated['provider']);
            $server = $strategy->configure($validated['location_id'], $validated['provider'], false);

            return response()->json([
                'success' => true,
                'message' => 'Сервер успешно создан',
                'data' => $server
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации данных',
                'errors' => $e->errors()
            ], 422);

        } catch (\RuntimeException $e) {
            Log::error('Server creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'Server limit reached')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Достигнут максимум серверов в аккаунте VDSina (лимит: 5 серверов). ' .
                                'Пожалуйста, удалите неиспользуемые серверы или пополните баланс.',
                    'error_code' => 'SERVER_LIMIT_REACHED'
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create server: ' . $e->getMessage()
            ], 400);

        } catch (\Exception $e) {
            Log::error('Unexpected error during server creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the server'
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Server $server
     * @return JsonResponse
     */
    public function destroy(Server $server): JsonResponse
    {
        try {
            // Создаем стратегию для провайдера и удаляем сервер
            $strategy = new ServerStrategy($server->provider);
            $strategy->delete($server);

            return response()->json([
                'success' => true,
                'message' => 'Сервер успешно удален'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete server', [
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при удалении сервера: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get server status
     */
    public function getStatus(Server $server): JsonResponse
    {
        return response()->json([
            'status' => $server->server_status,
            'message' => $server->status_label
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Server $server)
    {
        $server->update($request->all());

        return redirect()->route('module.server.index')
            ->with('success', 'Сервер успешно обновлен');
    }
}
