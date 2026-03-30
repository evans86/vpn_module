<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use App\Services\Server\LogUploadService;
use App\Services\Cloudflare\CloudflareService;
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
    /**
     * @var LogUploadService
     */
    private LogUploadService $logUploadService;

    public function __construct(
        ServerRepository $serverRepository,
        DatabaseLogger   $logger,
        LogUploadService $logUploadService
    )
    {
        $this->serverRepository = $serverRepository;
        $this->logger = $logger;
        $this->logUploadService = $logUploadService;
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

            // По умолчанию скрываем удаленные серверы, если не указан параметр show_deleted
            $showDeleted = $request->boolean('show_deleted', false);
            if (!$showDeleted) {
                $query->where('server_status', '!=', Server::SERVER_DELETED);
            }

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

                if ($request->filled('provider')) {
                    $p = strtolower((string) $request->input('provider'));
                    if (in_array($p, [Server::VDSINA, Server::TIMEWEB, Server::MANUAL], true)) {
                        $query->where('provider', $p);
                    }
                }
            }

            $sort = (string) $request->input('sort', 'id_desc');
            if ($sort === 'provider_asc') {
                $query->orderBy('provider', 'asc')->orderBy('id', 'desc');
            } elseif ($sort === 'provider_desc') {
                $query->orderBy('provider', 'desc')->orderBy('id', 'desc');
            } else {
                $query->orderBy('id', 'desc');
            }

            $servers = $query->paginate(10);

            $servers->appends($request->only(['show_deleted', 'name', 'ip', 'host', 'status', 'provider', 'sort']));

            // Получаем список локаций для формы создания сервера
            $locations = \App\Models\Location\Location::all();

            return view('module.server.index', compact('servers', 'showDeleted', 'locations'));
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

            // Получаем список доступных провайдеров через фабрику
            $serverStrategyFactory = new \App\Services\Server\ServerStrategyFactory();
            $availableProviders = $serverStrategyFactory->getAvailableProviders();
            
            if (empty($availableProviders)) {
                throw new \DomainException('No server providers available');
            }
            
            // Валидация входных данных
            $validated = $request->validate([
                'location_id' => 'required|integer|exists:location,id',
                'provider' => 'required|string|in:' . implode(',', $availableProviders)
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
     * Добавить сервер вручную (провайдер без API).
     * Принимает: location_id, name, ip, host (опц.), ssh_port (опц., иначе проверка TCP — порт 22), login (опц.), password (опц.).
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeManual(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'location_id' => 'required|integer|exists:location,id',
                'name' => 'required|string|max:255',
                'ip' => 'required|string|max:45',
                'host' => 'nullable|string|max:255',
                'ssh_port' => 'nullable|integer|min:1|max:65535',
                'login' => 'nullable|string|max:255',
                'password' => 'nullable|string|max:500',
            ]);

            $server = new Server();
            $server->location_id = $validated['location_id'];
            $server->name = $validated['name'];
            $server->ip = $validated['ip'];
            $server->host = $validated['host'] ?? $validated['ip'];
            $server->ssh_port = $validated['ssh_port'] ?? null;
            $server->login = $validated['login'] ?? null;
            $server->password = $validated['password'] ?? null;
            $server->provider = Server::MANUAL;
            $server->provider_id = null;
            $server->server_status = Server::SERVER_CREATED;
            $server->is_free = false;
            $server->save();

            $this->logger->info('Manual server created', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'location_id' => $server->location_id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Сервер добавлен',
                'data' => $server,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Ошибка валидации',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            $this->logger->error('Error creating manual server', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Не удалось добавить сервер: ' . $e->getMessage(),
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
                'ssh_port' => 'sometimes|nullable|integer|min:1|max:65535',
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
     * Перезагрузка сервера (API VDSina/Timeweb или SSH для ручных серверов).
     */
    public function reboot(Server $server): JsonResponse
    {
        if ((int) $server->server_status === (int) Server::SERVER_DELETED) {
            return response()->json([
                'success' => false,
                'message' => 'Сервер удалён',
            ], 400);
        }

        $provider = strtolower((string) $server->provider);
        if (in_array($provider, [Server::VDSINA, Server::TIMEWEB], true) && empty($server->provider_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Нет provider_id — перезагрузка через API недоступна',
            ], 400);
        }

        try {
            $strategy = new ServerStrategy($server->provider);
            $strategy->reboot($server);

            $this->logger->info('Server reboot requested', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'provider' => $server->provider,
            ]);

            $msg = $provider === Server::MANUAL
                ? 'Перезагрузка запланирована примерно через 1 минуту (SSH)'
                : 'Запрос на перезагрузку отправлен провайдеру';

            return response()->json([
                'success' => true,
                'message' => $msg,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Server reboot failed', [
                'source' => 'server',
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Настроить DNS (Cloudflare) для ручного сервера. Только для provider=manual и статуса «Создан».
     *
     * @param Server $server
     * @return JsonResponse
     */
    public function setupDns(Server $server): JsonResponse
    {
        if (strtolower((string)$server->provider) !== Server::MANUAL) {
            return response()->json(['success' => false, 'message' => 'Только для серверов, добавленных вручную'], 400);
        }
        if ((int)$server->server_status !== (int)Server::SERVER_CREATED) {
            return response()->json(['success' => false, 'message' => 'DNS настраивается только для серверов со статусом «Создан»'], 400);
        }
        if (empty($server->ip)) {
            return response()->json(['success' => false, 'message' => 'Укажите IP сервера'], 400);
        }

        try {
            $baseName = preg_replace('/[^a-z0-9\-]/i', '-', strtolower(trim($server->name ?? '')));
            $baseName = trim($baseName, '-');
            if ($baseName === '') {
                $baseName = 'server' . $server->id;
            }
            $locationCode = $server->location ? strtolower($server->location->code ?? '') : '';
            if ($locationCode !== '') {
                $baseName .= '-' . $locationCode;
            }
            $cloudflare = new CloudflareService();
            $host = $cloudflare->createSubdomain($baseName, $server->ip);

            $server->host = $host->name;
            $server->dns_record_id = $host->id;
            $server->save();

            $this->logger->info('DNS configured for manual server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'host' => $server->host,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'DNS запись создана: ' . $server->host,
                'host' => $server->host,
            ]);
        } catch (Exception $e) {
            $this->logger->error('DNS setup failed for manual server', [
                'source' => 'server',
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка настройки DNS: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Пинг ручного сервера (TCP к порту SSH: ssh_port или 22). При успехе — статус «Настроен».
     * В теле запроса можно передать ssh_port — сохранится в БД перед проверкой.
     *
     * @param Server $server
     * @return JsonResponse
     */
    public function pingAndConfigure(Request $request, Server $server): JsonResponse
    {
        if (strtolower((string)$server->provider) !== Server::MANUAL) {
            return response()->json(['success' => false, 'message' => 'Только для серверов, добавленных вручную'], 400);
        }
        if ((int)$server->server_status !== (int)Server::SERVER_CREATED) {
            return response()->json(['success' => false, 'message' => 'Сервер уже настроен или удалён'], 400);
        }

        try {
            $portRule = $request->validate([
                'ssh_port' => 'sometimes|nullable|integer|min:1|max:65535',
            ]);
            if (array_key_exists('ssh_port', $portRule)) {
                $server->ssh_port = $portRule['ssh_port'];
                $server->save();
            }

            $strategy = new ServerStrategy($server->provider);
            $ok = $strategy->ping($server);
            $checkPort = $server->ssh_port ?? 22;
            if (!$ok) {
                return response()->json([
                    'success' => false,
                    'message' => 'Сервер недоступен (TCP порт SSH ' . $checkPort . '). Проверьте хост/IP, порт и фаервол.',
                ], 400);
            }
            $server->server_status = Server::SERVER_CONFIGURED;
            $server->save();

            $this->logger->info('Manual server marked as configured after ping', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Сервер доступен, статус установлен: Настроен',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Ping/configure failed for manual server', [
                'source' => 'server',
                'server_id' => $server->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Ошибка: ' . $e->getMessage(),
            ], 500);
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

    /**
     * Включить выгрузку логов на сервере
     *
     * @param Server $server
     * @return JsonResponse
     */
    public function enableLogUpload(Server $server): JsonResponse
    {
        try {
            $this->logger->info('Enabling log upload', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            $result = $this->logUploadService->enableLogUpload($server);

            if ($result['success']) {
                $this->logger->info('Log upload enabled successfully', [
                    'source' => 'server',
                    'user_id' => auth()->id(),
                    'server_id' => $server->id
                ]);
            } else {
                $this->logger->error('Failed to enable log upload', [
                    'source' => 'server',
                    'user_id' => auth()->id(),
                    'server_id' => $server->id,
                    'error' => $result['message']
                ]);
            }

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            $this->logger->error('Error enabling log upload', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при включении выгрузки логов: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Проверить статус выгрузки логов на сервере
     *
     * @param Server $server
     * @return JsonResponse
     */
    public function checkLogUploadStatus(Server $server): JsonResponse
    {
        try {
            $this->logger->info('Checking log upload status', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id
            ]);

            $result = $this->logUploadService->checkLogUploadStatus($server);

            return response()->json($result, $result['success'] ? 200 : 400);

        } catch (Exception $e) {
            $this->logger->error('Error checking log upload status', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке статуса выгрузки логов: ' . $e->getMessage(),
                'status' => [
                    'installed' => false,
                    'cron_configured' => false,
                    'enabled_in_db' => (bool)$server->logs_upload_enabled,
                    'active' => false
                ]
            ], 500);
        }
    }
}
