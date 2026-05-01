<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Constants\TariffTier;
use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use App\Services\Server\LogUploadService;
use App\Services\Server\ServerDecoyStubService;
use App\Services\Server\ServerSpeedtestCliInstaller;
use App\Services\Cloudflare\CloudflareService;
use App\Repositories\Panel\PanelRepository;
use App\Repositories\Server\ServerRepository;
use App\Logging\DatabaseLogger;
use App\Support\ServerProviderCode;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
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

    /**
     * @var PanelRepository
     */
    private PanelRepository $panelRepository;

    /**
     * @var ServerDecoyStubService
     */
    private ServerDecoyStubService $decoyStubService;

    /**
     * @var ServerSpeedtestCliInstaller
     */
    private ServerSpeedtestCliInstaller $speedtestCliInstaller;

    public function __construct(
        ServerRepository $serverRepository,
        DatabaseLogger   $logger,
        LogUploadService $logUploadService,
        PanelRepository $panelRepository,
        ServerDecoyStubService $decoyStubService,
        ServerSpeedtestCliInstaller $speedtestCliInstaller
    )
    {
        $this->serverRepository = $serverRepository;
        $this->logger = $logger;
        $this->logUploadService = $logUploadService;
        $this->panelRepository = $panelRepository;
        $this->decoyStubService = $decoyStubService;
        $this->speedtestCliInstaller = $speedtestCliInstaller;
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
                    $p = (string) $request->input('provider');
                    if (strlen($p) <= 64) {
                        $query->where('provider', $p);
                    }
                }
            }

            $sort = (string) $request->input('sort', 'id_desc');
            if ($sort === 'provider_asc') {
                $query->orderBy('provider', 'asc')->orderBy('id', 'desc');
            } elseif ($sort === 'provider_desc') {
                $query->orderBy('provider', 'desc')->orderBy('id', 'desc');
            } elseif ($sort === 'tariff_asc') {
                $query->orderBy('tariff_tier', 'asc')->orderBy('id', 'desc');
            } elseif ($sort === 'tariff_desc') {
                $query->orderBy('tariff_tier', 'desc')->orderBy('id', 'desc');
            } else {
                $query->orderBy('id', 'desc');
            }

            $servers = $query->paginate(10);

            $servers->appends($request->only(['show_deleted', 'name', 'ip', 'host', 'status', 'provider', 'sort']));

            // Получаем список локаций для формы создания сервера
            $locations = \App\Models\Location\Location::all();

            $providerFilterOptions = $this->buildProviderFilterOptions();

            $manualProviderSuggestions = Server::query()
                ->where('server_status', '!=', Server::SERVER_DELETED)
                ->whereNotNull('provider')
                ->whereNotIn('provider', [Server::VDSINA, Server::TIMEWEB])
                ->distinct()
                ->orderBy('provider')
                ->pluck('provider')
                ->values()
                ->all();

            return view('module.server.index', compact(
                'servers',
                'showDeleted',
                'locations',
                'providerFilterOptions',
                'manualProviderSuggestions'
            ));
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
     * Подписи для фильтра по провайдеру (все уникальные коды из БД + известные алиасы).
     *
     * @return array<string, string> код => подпись
     */
    private function buildProviderFilterOptions(): array
    {
        $codes = Server::query()
            ->where('server_status', '!=', Server::SERVER_DELETED)
            ->whereNotNull('provider')
            ->where('provider', '!=', '')
            ->distinct()
            ->orderBy('provider')
            ->pluck('provider');

        $out = [];
        foreach ($codes as $code) {
            $s = new Server(['provider' => $code]);

            $out[(string) $code] = $s->getProviderLabel();
        }

        return $out;
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
                'provider' => 'required|string|in:' . implode(',', $availableProviders),
                'tariff_tier' => 'nullable|string|in:' . implode(',', TariffTier::all()),
            ]);

            $strategy = new ServerStrategy($validated['provider']);
            $server = $strategy->configure($validated['location_id'], $validated['provider'], false);

            $server->tariff_tier = $validated['tariff_tier'] ?? TariffTier::FULL;
            $server->save();

            $this->panelRepository->forgetRotationSelectionCache($server->provider);

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
                'provider_name' => 'required|string|max:80',
                'name' => 'required|string|max:255',
                'ip' => 'required|string|max:45',
                'host' => 'nullable|string|max:255',
                'ssh_port' => 'nullable|integer|min:1|max:65535',
                'login' => 'nullable|string|max:255',
                'password' => 'nullable|string|max:500',
                'tariff_tier' => 'nullable|string|in:' . implode(',', TariffTier::all()),
            ]);

            $providerCode = ServerProviderCode::fromLabel($validated['provider_name']);

            $server = new Server();
            $server->location_id = $validated['location_id'];
            $server->name = $validated['name'];
            $server->ip = $validated['ip'];
            $server->host = $validated['host'] ?? $validated['ip'];
            $server->ssh_port = $validated['ssh_port'] ?? null;
            $server->login = $validated['login'] ?? null;
            $server->password = $validated['password'] ?? null;
            $server->provider = $providerCode;
            $server->provider_id = null;
            $server->server_status = Server::SERVER_CREATED;
            $server->is_free = false;
            $server->tariff_tier = $validated['tariff_tier'] ?? TariffTier::FULL;
            $server->save();

            $this->logger->info('Manual server created', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'location_id' => $server->location_id,
                'provider' => $server->provider,
            ]);

            $this->panelRepository->forgetRotationSelectionCache($providerCode);

            return response()->json([
                'success' => true,
                'message' => 'Сервер добавлен',
                'data' => $server,
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
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
     * @return RedirectResponse|JsonResponse
     */
    public function update(Request $request, Server $server)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'ip' => 'sometimes|ip',
                'host' => 'sometimes|string|max:255',
                'ssh_port' => 'sometimes|nullable|integer|min:1|max:65535',
                'location_id' => 'sometimes|integer|exists:location,id',
                'provider_name' => 'sometimes|nullable|string|max:80',
                'tariff_tier' => 'sometimes|string|in:' . implode(',', TariffTier::all()),
            ]);

            $oldTariffTier = $server->tariff_tier;

            if (array_key_exists('provider_name', $validated)) {
                if (!$server->usesManualStrategy()) {
                    throw new InvalidArgumentException('Код провайдера можно менять только для серверов без API.');
                }
                $raw = trim((string) ($validated['provider_name'] ?? ''));
                if ($raw === '') {
                    throw new InvalidArgumentException('Укажите название провайдера.');
                }
                $newCode = ServerProviderCode::fromLabel($raw);
                $oldCode = (string) $server->provider;
                if ($newCode !== $oldCode) {
                    $server->provider = $newCode;
                    $this->panelRepository->forgetRotationSelectionCache($oldCode);
                    $this->panelRepository->forgetRotationSelectionCache($newCode);
                }
                unset($validated['provider_name']);
            }

            $this->logger->info('Updating server', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'data' => $validated,
            ]);

            $server = $this->serverRepository->updateConfiguration($server, $validated);

            if (array_key_exists('tariff_tier', $validated) && (string) $oldTariffTier !== (string) $validated['tariff_tier']) {
                $this->panelRepository->forgetRotationSelectionCache(null);
            }

            $this->logger->info('Server updated successfully', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Сохранено',
                    'data' => $server->fresh(),
                ]);
            }

            return redirect()->route('admin.module.server.index')
                ->with('success', 'Server updated successfully');
        } catch (InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 422);
            }

            return redirect()->route('admin.module.server.index')
                ->with('error', $e->getMessage());
        } catch (Exception $e) {
            $this->logger->error('Error updating server', [
                'source' => 'server',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'data' => $request->all(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error updating server: ' . $e->getMessage(),
                ], 500);
            }

            return redirect()->route('admin.module.server.index')
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
        if (Server::isApiProvider($provider) && empty($server->provider_id)) {
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

            $msg = $server->usesManualStrategy()
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
        if (!$server->usesManualStrategy()) {
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
            $server->cloudflare_zone_id = $host->cloudflare_zone_id ?? null;
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
        if (!$server->usesManualStrategy()) {
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

    /**
     * Nginx-заглушка (default_server 80/443) на сервере по SSH.
     */
    public function applyDecoyStub(Request $request, Server $server): JsonResponse
    {
        $include = $request->boolean('include_123_rar');

        try {
            $this->logger->info('Apply decoy stub', [
                'source' => 'server',
                'user_id' => auth()->id(),
                'server_id' => $server->id,
                'include_123_rar' => $include,
            ]);

            $result = $this->decoyStubService->apply($server, $include);

            if (! $result['success']) {
                $this->logger->error('Decoy stub apply failed', [
                    'source' => 'server',
                    'user_id' => auth()->id(),
                    'server_id' => $server->id,
                    'message' => $result['message'],
                ]);
            }

            return response()->json($result, $result['success'] ? 200 : 400);
        } catch (Exception $e) {
            $this->logger->error('Decoy stub exception', [
                'source' => 'server',
                'user_id' => auth()->id(),
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
     * Массовая установка speedtest-cli по SSH (для опционального блока в /test-speed на заглушке).
     */
    public function bulkInstallSpeedtestCli(Request $request): JsonResponse
    {
        if (function_exists('set_time_limit')) {
            /** @see ServerSpeedtestCliInstaller таймаут SSH до 900 с на каждый узел */
            set_time_limit(0);
        }

        $onlyConfigured = $request->boolean('only_configured', true);

        $q = Server::query()->where('server_status', '!=', Server::SERVER_DELETED);
        if ($onlyConfigured) {
            $q->where('server_status', Server::SERVER_CONFIGURED);
        }
        $servers = $q->whereNotNull('ip')
            ->where('ip', '!=', '')
            ->orderBy('id')
            ->get();

        $results = [];
        $ok = 0;
        $fail = 0;
        $skipped = 0;
        $attempted = 0;

        foreach ($servers as $server) {
            if (empty($server->login) || $server->password === null || $server->password === '') {
                $skipped++;
                $results[] = [
                    'id' => $server->id,
                    'name' => $server->name,
                    'success' => false,
                    'skipped' => true,
                    'message' => 'Пропуск: нет SSH логина/пароля.',
                    'output' => '',
                ];

                continue;
            }

            $attempted++;
            $r = $this->speedtestCliInstaller->install($server);
            if ($r['success']) {
                $ok++;
            } else {
                $fail++;
            }
            $results[] = [
                'id' => $server->id,
                'name' => $server->name,
                'success' => $r['success'],
                'skipped' => false,
                'message' => $r['message'],
                'output' => $r['output'] ?? '',
            ];
        }

        $allSucceeded = ($attempted === 0 ? true : ($fail === 0));
        $message = sprintf(
            'Обработано серверов: %d · попытка установки: %d · успех: %d · ошибок: %d · пропуск (нет SSH): %d.',
            $servers->count(),
            $attempted,
            $ok,
            $fail,
            $skipped
        );

        $this->logger->info('Bulk speedtest-cli install', [
            'source' => 'server',
            'user_id' => auth()->id(),
            'only_configured' => $onlyConfigured,
            'summary' => compact('ok', 'fail', 'skipped', 'attempted'),
        ]);

        return response()->json([
            'success' => $allSucceeded,
            'message' => $message,
            'summary' => [
                'total' => $servers->count(),
                'attempted' => $attempted,
                'ok' => $ok,
                'fail' => $fail,
                'skipped' => $skipped,
            ],
            'results' => $results,
        ]);
    }
}
