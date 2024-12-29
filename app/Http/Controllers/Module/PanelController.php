<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Panel\PanelStrategy;
use App\Logging\DatabaseLogger;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class PanelController extends Controller
{
    private DatabaseLogger $logger;

    public function __construct(
        DatabaseLogger $logger
    )
    {
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
            $query = Panel::query()->with(['server', 'server.location']);

            // Фильтр по серверу
            if ($request->filled('server')) {
                $search = $request->server;
                $query->whereHas('server', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%");
                });
            }

            // Фильтр по адресу панели
            if ($request->filled('panel_adress')) {
                $query->where('panel_adress', 'like', "%{$request->panel_adress}%");
            }

            // Фильтр по статусу
            if ($request->filled('status')) {
                $query->where('panel_status', $request->status);
            }

            $panels = $query->orderBy('id', 'desc')
                ->paginate(config('app.items_per_page', 30));

            // Получаем список серверов для формы создания панели
            $servers = Server::where('server_status', Server::SERVER_CONFIGURED)
                ->whereDoesntHave('panels')  // Добавляем условие отсутствия связанной панели
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($server) {
                    return [$server->id => $server->name . ' (' . $server->ip . ')'];
                });

            $this->logger->info('Просмотр списка панелей', [
                'source' => 'panel',
                'action' => 'index',
                'user_id' => auth()->id(),
                'filters' => $request->only(['server', 'panel_adress', 'status'])
            ]);

            return view('module.panel.index', compact('panels', 'servers'));
        } catch (Exception $e) {
            $this->logger->error('Ошибка при просмотре списка панелей', [
                'source' => 'panel',
                'action' => 'index',
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);

            return redirect()->back()->with('error', 'Ошибка при загрузке списка панелей');
        }
    }

    /**
     * Store a newly created panel.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $this->logger->info('Creating new panel', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'server_id' => $request->input('server_id')
            ]);

            $validated = $request->validate([
                'server_id' => [
                    'required',
                    'exists:server,id',
                    Rule::unique('panel', 'server_id'),
                    Rule::exists('server', 'id')->where(function ($query) {
                        $query->where('server_status', Server::SERVER_CONFIGURED);
                    }),
                ]
            ]);

            DB::beginTransaction();

            // Создаем панель через стратегию
            $strategy = new PanelStrategy(Panel::MARZBAN);
            $strategy->create($validated['server_id']);

            DB::commit();

            $this->logger->info('Panel created successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Panel created successfully');

        } catch (Exception $e) {
            DB::rollBack();
            $this->logger->error('Error creating panel', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'data' => $request->all()
            ]);

            return redirect()->route('admin.module.panel.index')
                ->withErrors(['msg' => 'Error creating panel: ' . $e->getMessage()]);
        }
    }

    /**
     * Configure panel.
     *
     * @param Panel $panel
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function configure(Panel $panel): RedirectResponse
    {
        try {
            $this->logger->info('Настройка панели', [
                'source' => 'panel',
                'action' => 'configure',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy($panel->panel);
            $strategy->updateConfiguration($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Панель успешно настроена');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при настройке панели', [
                'source' => 'panel',
                'action' => 'configure',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при настройке панели: ' . $e->getMessage());
        }
    }

    /**
     * Update panel configuration.
     *
     * @param Panel $panel
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function updateConfig(Panel $panel): RedirectResponse
    {
        try {
            $this->logger->info('Обновление конфигурации панели', [
                'source' => 'panel',
                'action' => 'update-config',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy($panel->panel);
            $strategy->updateConfiguration($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Конфигурация панели успешно обновлена');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении конфигурации панели', [
                'source' => 'panel',
                'action' => 'update-config',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при обновлении конфигурации панели: ' . $e->getMessage());
        }
    }

    /**
     * @TODO
     *
     * Check panel status
     * @param Panel $panel
     * @return JsonResponse
     */
    public function checkStatus(Panel $panel)
    {
        try {
            $this->logger->info('Checking panel status', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $marzbanApi = new MarzbanAPI($panel->panel_api_address);
            $isOnline = $marzbanApi->checkOnline($panel->id);

            $this->logger->info('Panel status checked', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'status' => $isOnline ? 'online' : 'offline'
            ]);

            return response()->json([
                'status' => $isOnline ? 'online' : 'offline'
            ]);

        } catch (Exception $e) {
            $this->logger->error('Error checking panel status', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
