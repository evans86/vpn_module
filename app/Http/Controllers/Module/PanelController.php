<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
use App\Logging\DatabaseLogger;
use App\Dto\Server\ServerFactory;
use phpseclib3\Net\SFTP;
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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
     * Детальная страница не используется — ведём на список с фильтром по панели.
     */
    public function show(Request $request, Panel $panel): RedirectResponse
    {
        return redirect()->route('admin.module.panel.index', ['panel_id' => $panel->id]);
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
     * Update panel configuration — stable REALITY (REALITY only, same ports, no VMess/Trojan/SS).
     *
     * @param Panel $panel
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function updateConfigRealityStable(Panel $panel): RedirectResponse
    {
        try {
            $this->logger->info('Обновление конфигурации панели (только REALITY)', [
                'source' => 'panel',
                'action' => 'update-config-reality-stable',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
            ]);

            $strategy = new PanelStrategy($panel->panel);
            $strategy->updateConfigurationRealityStable($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Конфигурация «только REALITY» успешно применена');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении конфигурации панели (только REALITY)', [
                'source' => 'panel',
                'action' => 'update-config-reality-stable',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при обновлении конфигурации: ' . $e->getMessage());
        }
    }

    /**
     * Update panel configuration — mixed: SS + Trojan + VLESS + 2 REALITY (no VMess).
     */
    public function updateConfigMixed(Panel $panel): RedirectResponse
    {
        try {
            $this->logger->info('Обновление конфигурации панели (смешанный)', [
                'source' => 'panel',
                'action' => 'update-config-mixed',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
            ]);

            $strategy = new PanelStrategy($panel->panel);
            $strategy->updateConfigurationMixed($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Конфигурация «SS + Trojan + VLESS + 2 REALITY» применена');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении конфигурации панели (смешанный)', [
                'source' => 'panel',
                'action' => 'update-config-mixed',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при обновлении конфигурации: ' . $e->getMessage());
        }
    }

    /**
     * Смешанные inbounds + полный WARP с узким списком DIRECT (как в пресете mixed_warp); включает WARP на панели.
     */
    public function updateConfigMixedWarp(Request $request, Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Пресет «смешанный + WARP» доступен только для панелей Marzban.');
        }

        try {
            $this->logger->info('Обновление конфигурации панели (смешанный + WARP)', [
                'source' => 'panel',
                'action' => 'update-config-mixed-warp',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
            ]);

            $this->persistMarzbanWarpBootstrapRouting($request, $panel);

            $panel->warp_routing_enabled = true;
            $panel->warp_routing_all = true;
            if ($panel->warp_socks_port === null) {
                $panel->warp_socks_port = (int) config('panel.warp_default_socks_port', 40000);
            }
            if (trim((string) $panel->warp_socks_host) === '') {
                $panel->warp_socks_host = (string) config('panel.warp_default_socks_host', '127.0.0.1');
            }
            $panel->save();

            $strategy = new PanelStrategy($panel->panel);
            $strategy->updateConfigurationMixedWarp($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Пресет «SS + Trojan + 3 REALITY + WARP (полный туннель)» применён. Убедитесь, что на ноде поднят WARP (SOCKS или WireGuard) по настройкам ниже.');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при обновлении конфигурации панели (смешанный + WARP)', [
                'source' => 'panel',
                'action' => 'update-config-mixed-warp',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ошибка при обновлении конфигурации: ' . $e->getMessage());
        }
    }

    /**
     * WARP на ноде: маршрут geosite:google через локальный SOCKS (Cloudflare WARP).
     * На сервере Marzban должен быть поднят SOCKS5 на указанном адресе (sing-box / и т.п.).
     */
    public function updateWarpRouting(Request $request, Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Маршрутизация WARP доступна только для панелей Marzban.');
        }

        $request->validate([
            'warp_socks_host' => ['nullable', 'string', 'max:64'],
            'warp_socks_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        $this->persistMarzbanWarpBootstrapRouting($request, $panel);

        $rawPort = $request->input('warp_socks_port');
        if ($request->has('warp_routing_enabled')) {
            $panel->warp_routing_enabled = $request->boolean('warp_routing_enabled');
        }
        if ($request->has('warp_routing_touched')) {
            $panel->warp_routing_all = ! $request->boolean('warp_selective');
        } elseif ($request->has('warp_routing_all')) {
            $panel->warp_routing_all = $request->boolean('warp_routing_all');
        }
        $defSocksHost = (string) config('panel.warp_default_socks_host', '127.0.0.1');
        $panel->warp_socks_host = trim((string) $request->input('warp_socks_host')) !== ''
            ? trim((string) $request->input('warp_socks_host'))
            : $defSocksHost;
        $panel->warp_socks_port = ($rawPort === null || $rawPort === '') ? null : (int) $rawPort;
        $panel->save();

        $this->logger->info('Обновление WARP-маршрутизации панели', [
            'source' => 'panel',
            'action' => 'update-warp-routing',
            'user_id' => auth()->id(),
            'panel_id' => $panel->id,
            'warp_routing_enabled' => $panel->warp_routing_enabled,
            'warp_routing_all' => $panel->warp_routing_all,
        ]);

        try {
            $strategy = new PanelStrategy($panel->panel);
            $strategy->reapplyCurrentConfiguration($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Настройки WARP сохранены, конфигурация панели переприменена (текущий пресет: '.($panel->config_type ?? '—').').');
        } catch (Exception $e) {
            $this->logger->error('Ошибка переприменения конфига после WARP', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Настройки сохранены, но не удалось переприменить конфиг: '.$e->getMessage());
        }
    }

    /**
     * Включение/отключение WARP (без правки хоста/порта) — панель Marzban может работать без WARP.
     */
    public function toggleWarpRouting(Request $request, Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'WARP доступен только для панелей Marzban.');
        }

        $enable = $request->boolean('warp_on');
        $panel->warp_routing_enabled = $enable;
        $panel->save();

        $this->logger->info('Переключение WARP на панели', [
            'source' => 'panel',
            'action' => 'toggle-warp-routing',
            'user_id' => auth()->id(),
            'panel_id' => $panel->id,
            'warp_routing_enabled' => $enable,
        ]);

        try {
            $strategy = new PanelStrategy($panel->panel);
            $strategy->reapplyCurrentConfiguration($panel->id);

            $msg = $enable
                ? 'WARP включён: внешний трафик Xray пойдёт в локальный SOCKS (по вашим настройкам ниже), конфиг переприменён.'
                : 'WARP отключён: панель работает без маршрутизации через WARP, конфиг переприменён.';

            return redirect()->route('admin.module.panel.index')
                ->with('success', $msg);
        } catch (Exception $e) {
            $this->logger->error('Ошибка переприменения после переключения WARP', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Флаг WARP сохранён, но не удалось переприменить конфиг: '.$e->getMessage());
        }
    }

    /**
     * С ноды (SSH) проверка SOCKS: тот же адрес, что в карточке.
     */
    public function checkWarpSocks(Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Проверка WARP только для панелей Marzban.');
        }

        try {
            $panel->refresh();
            $result = app(MarzbanService::class)->checkWarpSocksOnNode($panel);
            if (isset($result['ok']) && $result['ok'] === true) {
                $msg = $result['message'] ?? 'Проверка пройдена';
                if (empty($panel->warp_routing_enabled)) {
                    $msg .= ' Примечание: в карточке панели WARP выключен — Xray сейчас не отправляет трафик в этот SOCKS; проверка лишь подтверждает, что порт на ноде отвечает.';
                }

                return redirect()->route('admin.module.panel.index')
                    ->with('success', $msg);
            }
            $err = $result['message'] ?? 'Проверка не пройдена';
            if (! empty($result['detail'])) {
                $err .= ' (кратко: '.$result['detail'].')';
            }

            return redirect()->route('admin.module.panel.index')
                ->with('error', $err);
        } catch (Exception $e) {
            $this->logger->error('Проверка WARP (SOCKS) на ноде', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Проверка WARP: '.$e->getMessage());
        }
    }

    /**
     * SSH: с ноды читает wgcf-profile.conf, сохраняет в панель и переприменяет Xray (нативный WireGuard при PANEL_WARP_OUTBOUND_PROTOCOL=auto|wireguard).
     */
    public function importWarpWireGuardSnapshot(Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Импорт WARP (wgcf) только для панелей Marzban.');
        }
        if (! $panel->server_id) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'К панели не привязан сервер (нужен SSH).');
        }

        try {
            $panel->refresh();
            $result = app(MarzbanService::class)->importWarpWireGuardSnapshotFromNode($panel);
            if (empty($result['ok'])) {
                return redirect()->route('admin.module.panel.index')
                    ->with('error', $result['message'] ?? 'Импорт не выполнен');
            }
        } catch (Exception $e) {
            $this->logger->error('Импорт WARP (wgcf) с ноды', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Импорт WARP: '.$e->getMessage());
        }

        try {
            $strategy = new PanelStrategy($panel->panel);
            $strategy->reapplyCurrentConfiguration($panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Параметры WireGuard (wgcf) сохранены в панели, конфиг Xray переприменён. При PANEL_WARP_OUTBOUND_PROTOCOL=auto Xray использует нативный WARP, если снимок есть, иначе — SOCKS.');
        } catch (Exception $e) {
            $this->logger->error('Переприменение после импорта WARP (wgcf)', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Ключи сохранены, но не удалось переприменить конфиг: '.$e->getMessage());
        }
    }

    /**
     * SSH: sing-box + wgcf на сервере панели, затем (по флажку) маршрут WARP + переприменение.
     */
    public function installWarpSocksOnServer(Request $request, Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Автоустановка WARP только для панелей Marzban.');
        }
        if (! $panel->server_id) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'К панели не привязан сервер (нужен SSH), автоустановка недоступна.');
        }
        $request->validate([
            'warp_socks_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);
        $raw = $request->input('warp_socks_port');
        $port = ($raw === null || $raw === '')
            ? (int) ($panel->warp_socks_port ?? config('panel.warp_default_socks_port', 40000))
            : (int) $raw;

        $panelId = (int) $panel->id;
        $enableWarp = $request->boolean('enable_warp_routing', true);

        app()->terminating(function () use ($panelId, $port, $enableWarp) {
            try {
                app(MarzbanService::class)->installWarpSocksOnNodeAndReapplyConfig($panelId, $port, $enableWarp, false);
            } catch (Exception $e) {
                Log::error('WARP (фон): непредвиденная ошибка', [
                    'source' => 'panel',
                    'panel_id' => $panelId,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->logger->info('WARP: установка поставлена в фон (после HTTP-ответа)', [
            'source' => 'panel',
            'panel_id' => $panelId,
            'port' => $port,
        ]);

        return redirect()->route('admin.module.panel.index')
            ->with('success', 'Установка WARP на ноду запущена в фоне — ответ отправлен сразу, Cloudflare больше не ждёт завершения SSH. Подождите 1–3 минуты и обновите страницу. Итог смотрите в storage/logs/laravel.log; при сбое там будет причина.');
    }

    /**
     * Один шаг: SSH‑установка WARP на ноде → импорт wgcf → пресет «Смешанный + REALITY» + полный коридор WARP (mixed_warp).
     * То же самое без лишних кнопок; выполняется в фоне после ответа HTTP.
     */
    public function warpOneClickSetup(Request $request, Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Автонастройка WARP доступна только для Marzban.');
        }
        if (! $panel->server_id) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Привяжите к панели сервер с SSH (раздел Серверы), иначе установка недоступна.');
        }

        $request->validate([
            'warp_socks_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
        ]);

        $this->persistMarzbanWarpBootstrapRouting($request, $panel);
        $panel->save();

        $raw = $request->input('warp_socks_port');
        $port = ($raw === null || $raw === '')
            ? (int) ($panel->warp_socks_port ?? config('panel.warp_default_socks_port', 40000))
            : (int) $raw;

        $panelId = (int) $panel->id;

        app()->terminating(function () use ($panelId, $port) {
            try {
                app(MarzbanService::class)->installWarpSocksOnNodeAndReapplyConfig($panelId, $port, true, true);
            } catch (\Throwable $e) {
                Log::error('WARP one-click (фон): ошибка', [
                    'source' => 'panel',
                    'panel_id' => $panelId,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        $this->logger->info('WARP one-click: фон постановка', [
            'source' => 'panel',
            'panel_id' => $panelId,
            'port' => $port,
        ]);

        return redirect()->route('admin.module.panel.index')
            ->with('success',
                'Автонастройка с WARP запущена в фоне (2–5 мин). Обновите список панелей; при ошибке смотрите laravel.log.'
            );
    }

    /**
     * Повторно отправить текущий пресет на Marzban (после обновления кода панели / шаблонов маршрутизации).
     */
    public function reapplyMarzbanConfiguration(Panel $panel): RedirectResponse
    {
        if ($panel->panel !== Panel::MARZBAN) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Действие доступно только для Marzban.');
        }
        if ((int) $panel->panel_status !== Panel::PANEL_CONFIGURED) {
            return redirect()->route('admin.module.panel.index')
                ->with('error', 'Панель ещё не в статусе «Настроена» — выберите сценарий «Смешанный» или «С WARP».');
        }

        try {
            $this->logger->info('Повторная отправка конфига Marzban', [
                'source' => 'panel',
                'action' => 'reapply-marzban-config',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'config_type' => $panel->config_type,
            ]);

            $strategy = new PanelStrategy($panel->panel);
            $strategy->reapplyCurrentConfiguration((int) $panel->id);

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Текущий пресет снова отправлен в Marzban.');
        } catch (Exception $e) {
            $this->logger->error('Ошибка повторной отправки конфига Marzban', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('admin.module.panel.index')
                ->with('error', $e->getMessage());
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
     * Проверка подключения к панели (через браузер, без консоли).
     * Откройте в админке: Панели → выберите панель → в адресной строке добавьте /test-connection
     * Или откройте: /admin/module/panel/28/test-connection (подставьте id панели).
     *
     * @param Panel $panel
     * @return JsonResponse
     */
    public function testConnection(Panel $panel): JsonResponse
    {
        $host = $panel->api_address ?? ('https://' . preg_replace('#^https?://#', '', rtrim($panel->panel_adress ?? '', '/')));
        $result = ['success' => false, 'message' => '', 'details' => '', 'host' => $host];

        try {
            $api = new \App\Services\External\MarzbanAPI($host);
            $token = $api->getToken($panel->panel_login ?? '', $panel->panel_password ?? '');
            $result['success'] = true;
            $result['message'] = 'Подключение к панели успешно. Токен получен.';
            $result['details'] = 'Длина токена: ' . strlen($token ?? '') . ' символов.';
        } catch (\Throwable $e) {
            $result['message'] = $e->getMessage();
            $result['details'] = get_class($e) . ' in ' . $e->getFile() . ':' . $e->getLine();
        }

        return response()->json($result);
    }

    /**
     * Upload TLS certificates for panel.
     *
     * @param Request $request
     * @param Panel $panel
     * @return JsonResponse|RedirectResponse
     */
    public function uploadCertificates(Request $request, Panel $panel)
    {
        try {
            $this->logger->info('Начало загрузки сертификатов', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'has_certificate_file' => $request->hasFile('certificate'),
                'has_key_file' => $request->hasFile('key'),
                'has_use_tls' => $request->has('use_tls'),
                'use_tls_value' => $request->input('use_tls'),
                'panel_has_cert' => !empty($panel->tls_certificate_path),
                'panel_has_key' => !empty($panel->tls_key_path),
                'all_request_data' => $request->all()
            ]);
            
            // Если сертификаты уже загружены, можно обновить только use_tls без перезагрузки файлов
            if ($panel->tls_certificate_path && $panel->tls_key_path && !$request->hasFile('certificate') && !$request->hasFile('key')) {
                $this->logger->info('Обновление только use_tls (без файлов)', [
                    'source' => 'panel',
                    'panel_id' => $panel->id
                ]);
                $panel->use_tls = $request->has('use_tls') && $request->input('use_tls') == '1';
                $panel->save();

                $this->logger->info('Обновлен статус TLS шифрования', [
                    'source' => 'panel',
                    'action' => 'update-tls-status',
                    'user_id' => auth()->id(),
                    'panel_id' => $panel->id,
                    'use_tls' => $panel->use_tls
                ]);

                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => true,
                        'message' => $panel->use_tls 
                            ? 'TLS шифрование включено' 
                            : 'TLS шифрование выключено',
                        'use_tls' => $panel->use_tls
                    ]);
                }

                return redirect()->route('admin.module.panel.index')
                    ->with('success', $panel->use_tls 
                        ? 'TLS шифрование включено. Не забудьте обновить конфигурацию панели!' 
                        : 'TLS шифрование выключено');
            }

            $this->logger->info('Валидация файлов', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'has_certificate' => $request->hasFile('certificate'),
                'has_key' => $request->hasFile('key'),
                'certificate_file' => $request->hasFile('certificate') ? [
                    'name' => $request->file('certificate')->getClientOriginalName(),
                    'size' => $request->file('certificate')->getSize(),
                    'mime' => $request->file('certificate')->getMimeType(),
                    'extension' => $request->file('certificate')->getClientOriginalExtension()
                ] : null,
                'key_file' => $request->hasFile('key') ? [
                    'name' => $request->file('key')->getClientOriginalName(),
                    'size' => $request->file('key')->getSize(),
                    'mime' => $request->file('key')->getMimeType(),
                    'extension' => $request->file('key')->getClientOriginalExtension()
                ] : null
            ]);
            
            try {
                $validated = $request->validate([
                    'certificate' => 'required|file|max:10240', // 10MB max, убрали mimes для теста
                    'key' => 'required|file|max:10240', // 10MB max, убрали mimes для теста
                ]);
                
                $this->logger->info('Валидация прошла, результат:', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'validated' => $validated
                ]);
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->logger->error('Ошибка валидации файлов', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'errors' => $e->errors(),
                    'message' => $e->getMessage(),
                    'certificate_file' => $request->hasFile('certificate') ? [
                        'name' => $request->file('certificate')->getClientOriginalName(),
                        'size' => $request->file('certificate')->getSize(),
                        'mime' => $request->file('certificate')->getMimeType(),
                        'extension' => $request->file('certificate')->getClientOriginalExtension()
                    ] : null,
                    'key_file' => $request->hasFile('key') ? [
                        'name' => $request->file('key')->getClientOriginalName(),
                        'size' => $request->file('key')->getSize(),
                        'mime' => $request->file('key')->getMimeType(),
                        'extension' => $request->file('key')->getClientOriginalExtension()
                    ] : null
                ]);
                throw $e;
            }
            
            $this->logger->info('Валидация прошла успешно', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'certificate_file' => $request->file('certificate') ? [
                    'name' => $request->file('certificate')->getClientOriginalName(),
                    'size' => $request->file('certificate')->getSize(),
                    'mime' => $request->file('certificate')->getMimeType()
                ] : null,
                'key_file' => $request->file('key') ? [
                    'name' => $request->file('key')->getClientOriginalName(),
                    'size' => $request->file('key')->getSize(),
                    'mime' => $request->file('key')->getMimeType()
                ] : null
            ]);

            // Создаем директорию для сертификатов панели
            $certDir = storage_path('app/certificates/' . $panel->id);
            $this->logger->info('Создание директории для сертификатов', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'certDir' => $certDir,
                'dir_exists' => file_exists($certDir)
            ]);
            
            if (!file_exists($certDir)) {
                $created = mkdir($certDir, 0755, true);
                $this->logger->info('Директория создана', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'certDir' => $certDir,
                    'created' => $created
                ]);
            }

            // Сохраняем сертификат
            $this->logger->info('Начало сохранения сертификата', [
                'source' => 'panel',
                'panel_id' => $panel->id
            ]);
            
            $certPath = $request->file('certificate')->storeAs(
                'certificates/' . $panel->id,
                'cert.pem',
                'local'
            );
            
            $this->logger->info('Сертификат сохранен', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'certPath' => $certPath
            ]);

            // Сохраняем ключ
            $this->logger->info('Начало сохранения ключа', [
                'source' => 'panel',
                'panel_id' => $panel->id
            ]);
            
            $keyPath = $request->file('key')->storeAs(
                'certificates/' . $panel->id,
                'key.pem',
                'local'
            );
            
            $this->logger->info('Ключ сохранен', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'keyPath' => $keyPath
            ]);

            $this->logger->info('Файлы сохранены', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'certPath' => $certPath,
                'keyPath' => $keyPath,
                'full_cert_path' => storage_path('app/' . $certPath),
                'full_key_path' => storage_path('app/' . $keyPath),
                'cert_exists' => file_exists(storage_path('app/' . $certPath)),
                'key_exists' => file_exists(storage_path('app/' . $keyPath)),
                'use_tls_request' => $request->has('use_tls'),
                'use_tls_value' => $request->input('use_tls')
            ]);

            // Копируем сертификаты на сервер Marzban через SSH
            $localCertPath = storage_path('app/' . $certPath);
            $localKeyPath = storage_path('app/' . $keyPath);
            
            // Пути на сервере Marzban
            $remoteCertDir = '/var/lib/marzban/certificates';
            $remoteCertPath = $remoteCertDir . '/cert_' . $panel->id . '.pem';
            $remoteKeyPath = $remoteCertDir . '/key_' . $panel->id . '.pem';
            
            try {
                // Загружаем сервер для SSH подключения
                $panel->load('server');
                if ($panel->server) {
                    $serverDto = ServerFactory::fromEntity($panel->server);
                    $marzbanService = app(MarzbanService::class);
                    $ssh = $marzbanService->connectSshAdapter($serverDto);
                    
                    // Создаем директорию на сервере, если её нет
                    $ssh->exec("mkdir -p {$remoteCertDir} 2>&1");
                    
                    // Копируем файлы через SFTP (тот же порт, что и SSH)
                    $sftp = new SFTP($serverDto->ip, $serverDto->ssh_port ?? 22);
                    if ($sftp->login($serverDto->login, $serverDto->password)) {
                        $sftp->put($remoteCertPath, $localCertPath, SFTP::SOURCE_LOCAL_FILE);
                        $sftp->put($remoteKeyPath, $localKeyPath, SFTP::SOURCE_LOCAL_FILE);
                        
                        // Устанавливаем права доступа
                        $ssh->exec("chmod 600 {$remoteKeyPath} 2>&1");
                        $ssh->exec("chmod 644 {$remoteCertPath} 2>&1");
                        
                        $this->logger->info('Сертификаты скопированы на сервер Marzban', [
                            'source' => 'panel',
                            'panel_id' => $panel->id,
                            'remote_cert_path' => $remoteCertPath,
                            'remote_key_path' => $remoteKeyPath
                        ]);
                        
                        // Сохраняем пути на сервере в БД
                        $panel->tls_certificate_path = $remoteCertPath;
                        $panel->tls_key_path = $remoteKeyPath;
                    } else {
                        throw new \RuntimeException('SFTP authentication failed');
                    }
                } else {
                    // Если сервер не найден, используем локальные пути
                    $this->logger->warning('Сервер не найден для панели, используем локальные пути', [
                        'source' => 'panel',
                        'panel_id' => $panel->id
                    ]);
                    $panel->tls_certificate_path = $localCertPath;
                    $panel->tls_key_path = $localKeyPath;
                }
            } catch (\Exception $e) {
                $this->logger->error('Ошибка при копировании сертификатов на сервер', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                
                // В случае ошибки используем локальные пути
                $panel->tls_certificate_path = $localCertPath;
                $panel->tls_key_path = $localKeyPath;
            }
            
            // Обновляем настройку use_tls из формы (если чекбокс отмечен)
            if ($request->has('use_tls') && $request->input('use_tls') == '1') {
                $panel->use_tls = true;
            }
            
            $this->logger->info('Перед сохранением в БД', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'tls_certificate_path' => $panel->tls_certificate_path,
                'tls_key_path' => $panel->tls_key_path,
                'use_tls' => $panel->use_tls
            ]);
            
            // Если чекбокс не был отмечен, оставляем use_tls как есть (не меняем на false)
            $saved = $panel->save();
            
            $this->logger->info('После сохранения в БД', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'saved' => $saved,
                'tls_certificate_path' => $panel->tls_certificate_path,
                'tls_key_path' => $panel->tls_key_path,
                'use_tls' => $panel->use_tls
            ]);
            
            // Обновляем модель из БД для корректного отображения
            $panel->refresh();
            
            $this->logger->info('После refresh из БД', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'tls_certificate_path' => $panel->tls_certificate_path,
                'tls_key_path' => $panel->tls_key_path,
                'use_tls' => $panel->use_tls
            ]);

            $this->logger->info('TLS сертификаты загружены для панели', [
                'source' => 'panel',
                'action' => 'upload-certificates',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'cert_path' => $panel->tls_certificate_path,
                'key_path' => $panel->tls_key_path,
                'use_tls' => $panel->use_tls
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Сертификаты успешно загружены' . ($panel->use_tls ? '. TLS включен' : '. Не забудьте включить TLS!'),
                    'cert_path' => $panel->tls_certificate_path,
                    'key_path' => $panel->tls_key_path,
                    'use_tls' => $panel->use_tls
                ]);
            }

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'TLS сертификаты успешно загружены для панели' . ($panel->use_tls ? '. TLS включен' : '. Не забудьте включить TLS!'));
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (Exception $e) {
            $this->logger->error('Ошибка при загрузке TLS сертификатов', [
                'source' => 'panel',
                'action' => 'upload-certificates',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при загрузке сертификатов: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при загрузке сертификатов: ' . $e->getMessage());
        }
    }

    /**
     * Get Let's Encrypt certificate automatically via SSH.
     *
     * @param Request $request
     * @param Panel $panel
     * @return JsonResponse|RedirectResponse
     */
    public function getLetsEncryptCertificate(Request $request, Panel $panel)
    {
        try {
            $request->validate([
                'domain' => 'nullable|string|max:255',
                'email' => 'nullable|email|max:255',
                'use_tls' => 'nullable|boolean',
                'validation_method' => 'nullable|in:http,dns'
            ]);

            // Автоматически определяем домен из адреса панели
            $domain = trim($request->input('domain', ''));
            if (empty($domain)) {
                $domain = parse_url($panel->panel_adress, PHP_URL_HOST);
                if (empty($domain)) {
                    throw new \RuntimeException('Не удалось определить домен из адреса панели. Укажите домен вручную.');
                }
            }
            
            $email = trim($request->input('email', ''));
            
            // Если email не указан, генерируем его из домена
            if (empty($email)) {
                $email = 'admin@' . $domain;
            }
            
            // Валидация email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \RuntimeException('Некорректный email адрес: ' . $email);
            }

            $this->logger->info('Начало получения Let\'s Encrypt сертификата', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'domain' => $domain,
                'email' => $email,
                'server_ip' => $panel->server ? $panel->server->ip : 'unknown'
            ]);

            // Проверяем наличие сервера
            $panel->load('server');
            if (!$panel->server) {
                throw new \RuntimeException('Сервер не найден для панели');
            }

            $serverDto = ServerFactory::fromEntity($panel->server);
            $marzbanService = app(MarzbanService::class);
            $ssh = $marzbanService->connectSshAdapter($serverDto);

            // Проверяем наличие certbot
            $certbotCheck = $ssh->exec('which certbot 2>&1');
            if (empty(trim($certbotCheck)) || str_contains($certbotCheck, 'not found')) {
                // Устанавливаем certbot
                $this->logger->info('Установка certbot', [
                    'source' => 'panel',
                    'panel_id' => $panel->id
                ]);

                $installOutput = $ssh->exec('sudo apt-get update -qq && sudo apt-get install -y certbot 2>&1');
                $installStatus = $ssh->getExitStatus();

                if ($installStatus !== 0) {
                    throw new \RuntimeException('Не удалось установить certbot: ' . substr($installOutput, 0, 500));
                }
            }

            // Проверяем, что домен указывает на IP сервера с панелью
            $serverIp = $panel->server->ip;
            $domainIp = gethostbyname($domain);
            
            if ($domainIp === $domain) {
                // DNS не разрешился
                throw new \RuntimeException('Домен не разрешается в IP адрес. Убедитесь, что DNS запись настроена правильно.');
            }
            
            if ($domainIp !== $serverIp) {
                $this->logger->error('IP домена не совпадает с IP сервера с панелью', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'domain' => $domain,
                    'domain_ip' => $domainIp,
                    'server_ip' => $serverIp
                ]);
                
                throw new \RuntimeException(
                    "Домен '{$domain}' указывает на IP {$domainIp}, а сервер с панелью имеет IP {$serverIp}. " .
                    "Let's Encrypt не сможет проверить домен. " .
                    "Создайте поддомен (например, panel.vpn-telegram.com) и укажите его на IP сервера с панелью ({$serverIp}), " .
                    "или измените DNS запись для этого домена."
                );
            }

            // Получаем сертификат (выполняется на сервере с панелью через SSH)
            $this->logger->info('Получение сертификата Let\'s Encrypt на сервере с панелью', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'domain' => $domain,
                'email' => $email,
                'server_ip' => $serverIp,
                'domain_ip' => $domainIp
            ]);

            // Останавливаем веб-сервер на порту 80, если он запущен (для standalone режима)
            $ssh->exec('sudo systemctl stop nginx 2>&1 || sudo systemctl stop apache2 2>&1 || true');

            // Получаем сертификат на сервере с панелью (standalone режим - HTTP-валидация)
            $certbotCommand = sprintf(
                'sudo certbot certonly --standalone --non-interactive --agree-tos --email %s -d %s 2>&1',
                escapeshellarg($email),
                escapeshellarg($domain)
            );
            
            $this->logger->info('Выполнение certbot команды на сервере', [
                'source' => 'panel',
                'panel_id' => $panel->id,
                'command' => $certbotCommand
            ]);

            $certbotOutput = $ssh->exec($certbotCommand);
            $certbotStatus = $ssh->getExitStatus();

            // Запускаем веб-сервер обратно
            $ssh->exec('sudo systemctl start nginx 2>&1 || sudo systemctl start apache2 2>&1 || true');

            if ($certbotStatus !== 0) {
                $this->logger->error('Ошибка получения сертификата Let\'s Encrypt', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'domain' => $domain,
                    'output' => substr($certbotOutput, 0, 1000),
                    'exit_status' => $certbotStatus
                ]);

                throw new \RuntimeException('Не удалось получить сертификат Let\'s Encrypt. Убедитесь, что домен указывает на IP сервера и порт 80 открыт. Ошибка: ' . substr($certbotOutput, 0, 500));
            }

            // Пути к сертификатам Let's Encrypt
            $letsEncryptCertPath = "/etc/letsencrypt/live/{$domain}/fullchain.pem";
            $letsEncryptKeyPath = "/etc/letsencrypt/live/{$domain}/privkey.pem";

            // Пути на сервере Marzban
            $remoteCertDir = '/var/lib/marzban/certificates';
            $remoteCertPath = $remoteCertDir . '/cert_' . $panel->id . '.pem';
            $remoteKeyPath = $remoteCertDir . '/key_' . $panel->id . '.pem';

            // Создаем директорию на сервере, если её нет
            $ssh->exec("sudo mkdir -p {$remoteCertDir} 2>&1");
            $ssh->exec("sudo chown -R marzban:marzban {$remoteCertDir} 2>&1 || sudo chown -R \$USER:\$USER {$remoteCertDir} 2>&1 || true");

            // Копируем сертификаты
            $copyCert = $ssh->exec("sudo cp {$letsEncryptCertPath} {$remoteCertPath} 2>&1");
            $copyKey = $ssh->exec("sudo cp {$letsEncryptKeyPath} {$remoteKeyPath} 2>&1");

            // Устанавливаем права доступа
            $ssh->exec("sudo chmod 644 {$remoteCertPath} 2>&1");
            $ssh->exec("sudo chmod 600 {$remoteKeyPath} 2>&1");
            $ssh->exec("sudo chown marzban:marzban {$remoteCertPath} {$remoteKeyPath} 2>&1 || sudo chown \$USER:\$USER {$remoteCertPath} {$remoteKeyPath} 2>&1 || true");

            // Сохраняем пути в БД
            $panel->tls_certificate_path = $remoteCertPath;
            $panel->tls_key_path = $remoteKeyPath;
            
            // Обновляем настройку use_tls из формы (если чекбокс отмечен)
            if ($request->has('use_tls') && $request->input('use_tls') == '1') {
                $panel->use_tls = true;
            }
            
            $panel->save();
            $panel->refresh();

            $this->logger->info('Let\'s Encrypt сертификат успешно получен и сохранен', [
                'source' => 'panel',
                'action' => 'get-letsencrypt-certificate',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'domain' => $domain,
                'cert_path' => $remoteCertPath,
                'key_path' => $remoteKeyPath,
                'use_tls' => $panel->use_tls
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Let\'s Encrypt сертификат успешно получен и сохранен! Не забудьте обновить конфигурацию панели.',
                    'cert_path' => $remoteCertPath,
                    'key_path' => $remoteKeyPath
                ]);
            }

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'Let\'s Encrypt сертификат успешно получен и сохранен! Не забудьте обновить конфигурацию панели.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка валидации',
                    'errors' => $e->errors()
                ], 422);
            }

            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (Exception $e) {
            $this->logger->error('Ошибка при получении Let\'s Encrypt сертификата', [
                'source' => 'panel',
                'action' => 'get-letsencrypt-certificate',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при получении сертификата: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при получении сертификата: ' . $e->getMessage());
        }
    }

    /**
     * Remove TLS certificates for panel (use default).
     *
     * @param Panel $panel
     * @return JsonResponse|RedirectResponse
     */
    public function removeCertificates(Panel $panel)
    {
        try {
            // Проверяем, является ли путь локальным или удаленным
            $isLocalCertPath = $panel->tls_certificate_path && (
                str_starts_with($panel->tls_certificate_path, storage_path()) || 
                str_starts_with($panel->tls_certificate_path, base_path())
            );
            
            $isLocalKeyPath = $panel->tls_key_path && (
                str_starts_with($panel->tls_key_path, storage_path()) || 
                str_starts_with($panel->tls_key_path, base_path())
            );

            // Удаляем локальные файлы сертификатов
            if ($isLocalCertPath && $panel->tls_certificate_path && @file_exists($panel->tls_certificate_path)) {
                @unlink($panel->tls_certificate_path);
                $this->logger->info('Локальный сертификат удален', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'path' => $panel->tls_certificate_path
                ]);
            }
            
            if ($isLocalKeyPath && $panel->tls_key_path && @file_exists($panel->tls_key_path)) {
                @unlink($panel->tls_key_path);
                $this->logger->info('Локальный ключ удален', [
                    'source' => 'panel',
                    'panel_id' => $panel->id,
                    'path' => $panel->tls_key_path
                ]);
            }

            // Для удаленных путей (на сервере Marzban) пытаемся удалить через SSH
            if (!$isLocalCertPath && $panel->tls_certificate_path) {
                try {
                    $panel->load('server');
                    if ($panel->server) {
                        $serverDto = ServerFactory::fromEntity($panel->server);
                        $marzbanService = app(MarzbanService::class);
                        $ssh = $marzbanService->connectSshAdapter($serverDto);
                        
                        // Удаляем файлы на удаленном сервере
                        if ($panel->tls_certificate_path) {
                            $ssh->exec("sudo rm -f " . escapeshellarg($panel->tls_certificate_path) . " 2>&1");
                        }
                        if ($panel->tls_key_path) {
                            $ssh->exec("sudo rm -f " . escapeshellarg($panel->tls_key_path) . " 2>&1");
                        }
                        
                        $this->logger->info('Удаленные сертификаты удалены через SSH', [
                            'source' => 'panel',
                            'panel_id' => $panel->id,
                            'cert_path' => $panel->tls_certificate_path,
                            'key_path' => $panel->tls_key_path
                        ]);
                    }
                } catch (\Exception $e) {
                    // Логируем ошибку, но продолжаем - главное очистить БД
                    $this->logger->warning('Не удалось удалить файлы на удаленном сервере', [
                        'source' => 'panel',
                        'panel_id' => $panel->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Удаляем локальную директорию если пуста
            $certDir = storage_path('app/certificates/' . $panel->id);
            if (is_dir($certDir) && count(scandir($certDir)) == 2) {
                @rmdir($certDir);
            }

            // Очищаем пути в БД
            $panel->tls_certificate_path = null;
            $panel->tls_key_path = null;
            $panel->use_tls = false;
            $panel->save();

            $this->logger->info('TLS сертификаты удалены для панели', [
                'source' => 'panel',
                'action' => 'remove-certificates',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Сертификаты удалены, будут использоваться настройки по умолчанию'
                ]);
            }

            return redirect()->route('admin.module.panel.index')
                ->with('success', 'TLS сертификаты удалены, будут использоваться настройки по умолчанию');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при удалении TLS сертификатов', [
                'source' => 'panel',
                'action' => 'remove-certificates',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при удалении сертификатов: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка при удалении сертификатов: ' . $e->getMessage());
        }
    }

    /**
     * Toggle TLS encryption for panel.
     *
     * @param Panel $panel
     * @return JsonResponse|RedirectResponse
     */
    public function toggleTls(Panel $panel)
    {
        try {
            // Проверяем, что сертификаты загружены
            if (!$panel->tls_certificate_path || !$panel->tls_key_path) {
                if (request()->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Сначала загрузите TLS сертификаты'
                    ], 400);
                }

                return redirect()->back()
                    ->with('error', 'Сначала загрузите TLS сертификаты');
            }

            $panel->use_tls = !$panel->use_tls;
            $panel->save();

            $this->logger->info('Изменен статус TLS шифрования', [
                'source' => 'panel',
                'action' => 'toggle-tls',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'use_tls' => $panel->use_tls
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $panel->use_tls 
                        ? 'TLS шифрование включено' 
                        : 'TLS шифрование выключено',
                    'use_tls' => $panel->use_tls
                ]);
            }

            return redirect()->route('admin.module.panel.index')
                ->with('success', $panel->use_tls 
                    ? 'TLS шифрование включено. Не забудьте обновить конфигурацию панели!' 
                    : 'TLS шифрование выключено');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при изменении статуса TLS', [
                'source' => 'panel',
                'action' => 'toggle-tls',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Toggle rotation exclusion for panel.
     *
     * @param Panel $panel
     * @return JsonResponse|RedirectResponse
     */
    public function toggleRotationExclusion(Panel $panel)
    {
        try {
            $panel->excluded_from_rotation = !$panel->excluded_from_rotation;
            $panel->save();

            $this->logger->info('Изменен статус исключения из ротации', [
                'source' => 'panel',
                'action' => 'toggle-rotation-exclusion',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'excluded_from_rotation' => $panel->excluded_from_rotation
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $panel->excluded_from_rotation 
                        ? 'Панель исключена из ротации' 
                        : 'Панель включена в ротацию',
                    'excluded_from_rotation' => $panel->excluded_from_rotation
                ]);
            }

            return redirect()->route('admin.module.panel.index')
                ->with('success', $panel->excluded_from_rotation 
                    ? 'Панель исключена из ротации' 
                    : 'Панель включена в ротацию');
        } catch (Exception $e) {
            $this->logger->error('Ошибка при изменении статуса исключения из ротации', [
                'source' => 'panel',
                'action' => 'toggle-rotation-exclusion',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'error' => $e->getMessage()
            ]);

            if (request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка: ' . $e->getMessage()
                ], 500);
            }

            return redirect()->back()
                ->with('error', 'Ошибка: ' . $e->getMessage());
        }
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

    /**
     * Резолверы DIRECT и UDP/53 до catch-all WARP — из формы карточки панели (см. partial warp-bootstrap-routing-fields).
     */
    private function persistMarzbanWarpBootstrapRouting(Request $request, Panel $panel): void
    {
        if ($panel->panel !== Panel::MARZBAN || ! $request->boolean('warp_bootstrap_ack')) {
            return;
        }

        $request->validate([
            'warp_bootstrap_dns_ips' => ['nullable', 'string', 'max:4096'],
        ]);

        $dns = trim((string) $request->input('warp_bootstrap_dns_ips', ''));
        $panel->warp_bootstrap_dns_ips = ($dns === '') ? null : $dns;
        $panel->warp_bootstrap_udp53_direct = $request->boolean('warp_bootstrap_udp53_direct');
    }

}
