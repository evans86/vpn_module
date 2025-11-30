<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use App\Repositories\Server\ServerRepository;
use App\Logging\DatabaseLogger;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ServerController extends Controller
{
    /**
     * @var ServerRepository
     */
    private ServerRepository $serverRepository;
    /**
     * @var DatabaseLogger
     */
    private DatabaseLogger $logger;

    public function __construct(
        ServerRepository $serverRepository,
        DatabaseLogger   $logger
    )
    {
        $this->serverRepository = $serverRepository;
        $this->logger = $logger;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Application|Factory|View|RedirectResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Server::query()->with(['panel', 'location']);

            // Фильтрация по конкретному серверу
            if ($request->filled('server_id')) {
                $query->where('id', $request->input('server_id'));
            } else {
                // Остальные фильтры применяются только если не указан конкретный сервер
                if ($request->filled('id')) {
                    $query->where('id', $request->input('id'));
                }

                if ($request->filled('name')) {
                    $query->where('name', 'like', '%' . $request->input('name') . '%');
                }

                if ($request->filled('ip')) {
                    $query->where('ip', 'like', '%' . $request->input('ip') . '%');
                }

                if ($request->filled('host')) {
                    $query->where('host', 'like', '%' . $request->input('host') . '%');
                }

                if ($request->filled('status')) {
                    $query->where('server_status', $request->input('status'));
                }
            }

            $servers = $query->orderBy('id', 'desc')
                ->paginate(config('app.items_per_page', 30));

            $this->logger->info('Просмотр списка серверов', [
                'source' => 'server',
                'action' => 'index',
                'user_id' => auth()->id(),
                'filters' => $request->only(['id', 'name', 'ip', 'host', 'status', 'server_id'])
            ]);

            return view('module.server.index', compact('servers'));
        } catch (Exception $e) {
            $this->logger->error('Ошибка при просмотре списка серверов', [
                'source' => 'server',
                'action' => 'index',
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Ошибка при загрузке списка серверов');
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws GuzzleException
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $this->logger->info('Creating new server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'location_id' => $request->input('location_id'),
                'provider' => $request->input('provider')
            ]);

            // Валидация входных данных
            $validated = $request->validate([
                'location_id' => 'required|integer|exists:location,id',
                'provider' => 'required|string|in:' . Server::VDSINA
            ]);

            $strategy = new ServerStrategy($validated['provider']);
            $server = $strategy->configure($validated['location_id'], $validated['provider'], false);

            $this->logger->info('Server created successfully', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'location_id' => $server->location_id,
                'provider' => $server->provider
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Server created successfully',
                'data' => $server
            ]);

        } catch (RuntimeException $e) {
            $this->logger->error('Server creation failed', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'data' => $request->all()
            ]);

            $errorMessage = $e->getMessage();
            if (str_contains($errorMessage, 'Server limit reached')) {
                return response()->json([
                    'success' => false,
                    'message' => 'VDSina server limit reached (limit: 5 servers). ' .
                        'Please delete unused servers or add funds.',
                    'error_code' => 'SERVER_LIMIT_REACHED'
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to create server: ' . $e->getMessage()
            ], 400);

        } catch (Exception $e) {
            $this->logger->error('Unexpected error during server creation', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'An unexpected error occurred while creating the server'
            ], 500);
        }
    }

    /**
     * @param Request $request
     * @param Server $server
     * @return RedirectResponse
     */
    public function update(Request $request, Server $server): RedirectResponse
    {
        try {
            // Валидация входных данных
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'ip' => 'sometimes|ip',
                'host' => 'sometimes|string|max:255',
                'location_id' => 'sometimes|integer|exists:location,id',
            ]);

            $this->logger->info('Updating server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'data' => $validated
            ]);

            $server = $this->serverRepository->updateConfiguration($server, $validated);

            $this->logger->info('Server updated successfully', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            return redirect()->route('module.server.index')
                ->with('success', 'Server updated successfully');
        } catch (Exception $e) {
            $this->logger->error('Error updating server', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'data' => $request->all()
            ]);

            return redirect()->route('module.server.index')
                ->withErrors('Error updating server: ' . $e->getMessage());
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
            $this->logger->info('Deleting server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'provider' => $server->provider
            ]);

            // Создаем стратегию для провайдера и удаляем сервер
            $strategy = new ServerStrategy($server->provider);
            $strategy->delete($server);

            $this->logger->info('Server deleted successfully', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            return response()->json([
                'message' => 'Server deleted successfully'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Error deleting server', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            return response()->json([
                'message' => 'Error deleting server: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * @param Server $server
     * @return JsonResponse
     */
    public function getStatus(Server $server): JsonResponse
    {
        return response()->json([
            'status' => $server->server_status,
            'message' => $server->status_label
        ]);
    }
}
