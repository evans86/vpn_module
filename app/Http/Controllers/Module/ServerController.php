<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Location\Location;
use App\Models\Server\Server;
use App\Services\Server\strategy\ServerStrategyFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

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
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $locations = Location::pluck('code', 'id')->mapWithKeys(function ($code, $id) {
            return [$id => $code . ' ' . Location::find($id)->emoji];
        });
        
        return view('module.server.create', compact('locations'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Server $server)
    {
        $locations = Location::pluck('code', 'id')->mapWithKeys(function ($code, $id) {
            return [$id => $code . ' ' . Location::find($id)->emoji];
        });
        
        return view('module.server.edit', compact('server', 'locations'));
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
            $provider = $request->input('provider');
            $location_id = $request->input('location_id', 1);
            $is_free = $request->input('is_free', false);

            $strategy = ServerStrategyFactory::create($provider);
            $server = $strategy->configure($location_id, $provider, $is_free);

            return response()->json([
                'status' => 'success',
                'message' => 'Server created successfully',
                'data' => $server
            ]);

        } catch (\Exception $e) {
            Log::error('Server creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create server: ' . $e->getMessage()
            ], 500);
        }
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
     * Remove the specified resource from storage.
     */
    public function destroy(Server $server): JsonResponse
    {
        try {
            // Выбираем стратегию в зависимости от провайдера
            $strategy = null;
            switch ($server->provider) {
                case Server::VDSINA:
                    $strategy = new ServerVdsinaStrategy();
                    break;
                default:
                    throw new \InvalidArgumentException('Неподдерживаемый провайдер');
            }

            $strategy->delete($server->id);
            $server->update(['server_status' => Server::SERVER_DELETED]);

            return response()->json(['message' => 'Сервер успешно удален']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Ошибка при удалении сервера: ' . $e->getMessage()
            ], 400);
        }
    }
}
