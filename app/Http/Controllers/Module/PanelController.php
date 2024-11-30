<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Panel\marzban\MarzbanAPI;
use App\Services\Panel\PanelStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PanelController extends Controller
{
    /**
     * Display a listing of panels.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $panels = Panel::with(['server.location'])
                      ->orderBy('id', 'desc')
                      ->paginate(10);

        // Получаем только настроенные серверы без панелей
        $servers = Server::where('server_status', Server::SERVER_CONFIGURED)
                        ->whereDoesntHave('panels')
                        ->with('location')
                        ->get()
                        ->mapWithKeys(function ($server) {
                            $locationName = $server->location ? " ({$server->location->name})" : '';
                            return [$server->id => "{$server->name}{$locationName}"];
                        });

        return view('module.panel.index', compact('panels', 'servers'));
    }

    /**
     * Store a newly created panel.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        try {
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

            return redirect()->route('module.panel.index')
                ->with('success', 'Панель успешно создана');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Panel creation failed: ' . $e->getMessage(), [
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('module.panel.index')
                ->with('error', 'Ошибка при создании панели: ' . $e->getMessage());
        }
    }

    /**
     * Check the status of the specified panel.
     *
     * @param Panel $panel
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkStatus(Panel $panel)
    {
        try {
            $marzbanApi = new MarzbanAPI($panel->panel_api_address);
            $isOnline = $marzbanApi->checkOnline($panel->id);

            return response()->json([
                'status' => $isOnline ? 'online' : 'offline'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Configure the specified panel.
     *
     * @param Panel $panel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function configure(Panel $panel)
    {
        try {
            $strategy = new PanelStrategy(Panel::MARZBAN);
            $strategy->updateConfiguration($panel->id);

            return redirect()
                ->route('module.panel.index')
                ->with('success', 'Панель успешно настроена');
        } catch (GuzzleException $e) {
            return redirect()
                ->route('module.panel.index')
                ->with('error', 'Ошибка сетевого подключения: ' . $e->getMessage());
        } catch (\Exception $e) {
            return redirect()
                ->route('module.panel.index')
                ->with('error', 'Ошибка настройки панели: ' . $e->getMessage());
        }
    }

    /**
     * Update panel configuration.
     *
     * @param Panel $panel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateConfig(Panel $panel): \Illuminate\Http\RedirectResponse
    {
        try {
            $strategy = new PanelStrategy(Panel::MARZBAN);
            $strategy->updateConfiguration($panel->id);

            return redirect()
                ->route('module.panel.index')
                ->with('success', 'Конфигурация успешно обновлена');
        } catch (\Exception $e) {
            return redirect()
                ->route('module.panel.index')
                ->with('error', 'Ошибка обновления конфигурации: ' . $e->getMessage());
        }
    }
}
