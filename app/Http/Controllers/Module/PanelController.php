<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Panel\marzban\MarzbanService;
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
    /**
     * @var DatabaseLogger
     */
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

            // По умолчанию скрываем удаленные панели, если не указан параметр show_deleted
            $showDeleted = $request->boolean('show_deleted', false);
            if (!$showDeleted) {
                $query->where('panel_status', '!=', Panel::PANEL_DELETED);
            }

            // Если передан panel_id, показываем только эту панель
            if ($request->filled('panel_id')) {
                $query->where('id', $request->panel_id);
            }

            // Фильтр по серверу
            if ($request->filled('server')) {
                $search = $request->input('server');
                $query->whereHas('server', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('ip', 'like', "%{$search}%");
                });
            }

            // Фильтр по адресу панели
            if ($request->filled('panel_adress')) {
                $query->where('panel_adress', 'like', "%{$request->input('panel_adress')}%");
            }

            // Фильтр по статусу
            if ($request->filled('status')) {
                $query->where('panel_status', $request->status);
            }

            $panels = $query->orderBy('id', 'desc')
                ->paginate(10);
            
            // Добавляем параметр show_deleted в пагинацию
            $panels->appends($request->only(['show_deleted', 'server', 'panel_adress', 'status', 'panel_id']));

            // Получаем список серверов для формы создания панели
            $servers = Server::where('server_status', Server::SERVER_CONFIGURED)
                ->whereDoesntHave('panel')  // Исправляем на panel вместо panels
                ->orderBy('name')
                ->get()
                ->mapWithKeys(function ($server) {
                    return [$server->id => $server->name . ' (' . $server->ip . ')'];
                });

            return view('module.panel.index', compact('panels', 'servers', 'showDeleted'));
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

            // Используем DB::transaction() для автоматического rollback при ошибках
            DB::transaction(function () use ($validated) {
                // Получаем сервер для определения типа панели
                $server = Server::find($validated['server_id']);
                if (!$server) {
                    throw new \RuntimeException('Server not found');
                }
                
                // Получаем тип панели из запроса или используем первый доступный тип
                $panelStrategyFactory = new \App\Services\Panel\PanelStrategyFactory();
                $availablePanelTypes = $panelStrategyFactory->getAvailablePanelTypes();
                
                if (empty($availablePanelTypes)) {
                    throw new \DomainException('No panel types available');
                }
                
                // Используем первый доступный тип панели (в будущем можно добавить выбор в форму)
                $panelType = $availablePanelTypes[0];
                
                // Создаем панель через стратегию
                $strategy = new PanelStrategy($panelType);
                $strategy->create($validated['server_id']);
            });

            $this->logger->info('Panel created successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Panel created successfully');

        } catch (Exception $e) {
            // Rollback выполняется автоматически в DB::transaction()
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
     * Update panel configuration - stable (without REALITY).
     *
     * @param Panel $panel
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function updateConfigStable(Panel $panel): RedirectResponse
    {
        try {
            $this->logger->info('Обновление конфигурации панели (стабильный)', [
                'source' => 'panel',
                'action' => 'update-config-stable',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy($panel->panel);
            $strategy->updateConfigurationStable($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Стабильная конфигурация панели успешно применена');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении конфигурации панели (стабильный)', [
                'source' => 'panel',
                'action' => 'update-config-stable',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при обновлении конфигурации: ' . $e->getMessage());
        }
    }

    /**
     * Update panel configuration - with REALITY (best bypass).
     *
     * @param Panel $panel
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function updateConfigReality(Panel $panel): RedirectResponse
    {
        try {
            $this->logger->info('Обновление конфигурации панели (REALITY)', [
                'source' => 'panel',
                'action' => 'update-config-reality',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy($panel->panel);
            $strategy->updateConfigurationReality($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Конфигурация с REALITY успешно применена');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении конфигурации панели (REALITY)', [
                'source' => 'panel',
                'action' => 'update-config-reality',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при обновлении конфигурации: ' . $e->getMessage());
        }
    }

    /**
     * Update panel configuration (legacy method).
     *
     * @param Panel $panel
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function updateConfig(Panel $panel): RedirectResponse
    {
        // По умолчанию используем REALITY конфигурацию
        return $this->updateConfigReality($panel);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Panel $panel
     * @return RedirectResponse
     */
    public function destroy(Panel $panel): RedirectResponse
    {
        try {
            $this->logger->info('Удаление панели', [
                'source' => 'panel',
                'action' => 'destroy',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $panel->delete();

            $this->logger->info('Панель успешно удалена', [
                'source' => 'panel',
                'action' => 'destroy',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Панель успешно удалена');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при удалении панели', [
                'source' => 'panel',
                'action' => 'destroy',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при удалении панели: ' . $e->getMessage());
        }
    }
}
