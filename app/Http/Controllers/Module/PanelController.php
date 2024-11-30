<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Panel\marzban\MarzbanService;
use App\Services\Panel\PanelStrategy;
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

        $servers = Server::where('server_status', Server::SERVER_CONFIGURED)
                        ->where('is_free', true)
                        ->with('location')
                        ->get()
                        ->map(function ($server) {
                            return [
                                'id' => $server->id,
                                'name' => $server->name . ' (' . $server->location->name . ')'
                            ];
                        })
                        ->pluck('name', 'id');

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
                'panel_adress' => ['required', 'url', 'unique:panel,panel_adress'],
                'panel_login' => ['required', 'string', 'max:255'],
                'panel_password' => ['required', 'string', 'min:6'],
                'server_id' => [
                    'required',
                    'exists:server,id',
                    Rule::unique('panel', 'server_id')->whereNull('deleted_at')
                ]
            ]);

            DB::beginTransaction();

            $panel = Panel::create($validated + [
                'panel' => Panel::MARZBAN,
                'panel_status' => Panel::PANEL_CREATED
            ]);

            // Помечаем сервер как занятый
            Server::where('id', $validated['server_id'])->update(['is_free' => false]);

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
     * Update the specified panel.
     *
     * @param Request $request
     * @param Panel $panel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, Panel $panel)
    {
        try {
            $validated = $request->validate([
                'panel_adress' => [
                    'required',
                    'url',
                    Rule::unique('panel', 'panel_adress')->ignore($panel->id)
                ],
                'panel_login' => ['required', 'string', 'max:255'],
                'panel_password' => ['nullable', 'string', 'min:6'],
                'server_id' => [
                    'required',
                    'exists:server,id',
                    Rule::unique('panel', 'server_id')
                        ->whereNull('deleted_at')
                        ->ignore($panel->id)
                ]
            ]);

            DB::beginTransaction();

            // Если меняется сервер
            if ($panel->server_id !== (int)$validated['server_id']) {
                // Освобождаем старый сервер
                if ($panel->server_id) {
                    Server::where('id', $panel->server_id)->update(['is_free' => true]);
                }
                // Занимаем новый сервер
                Server::where('id', $validated['server_id'])->update(['is_free' => false]);
            }

            // Если пароль не указан, удаляем его из массива
            if (empty($validated['panel_password'])) {
                unset($validated['panel_password']);
            }

            $panel->update($validated);

            DB::commit();

            return redirect()->route('module.panel.index')
                ->with('success', 'Панель успешно обновлена');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Panel update failed: ' . $e->getMessage(), [
                'panel_id' => $panel->id,
                'data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('module.panel.index')
                ->with('error', 'Ошибка при обновлении панели: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified panel.
     *
     * @param Panel $panel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Panel $panel)
    {
        try {
            DB::beginTransaction();

            // Освобождаем сервер
            if ($panel->server_id) {
                Server::where('id', $panel->server_id)->update(['is_free' => true]);
            }

            $panel->delete();

            DB::commit();

            return redirect()->route('module.panel.index')
                ->with('success', 'Панель успешно удалена');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Panel deletion failed: ' . $e->getMessage(), [
                'panel_id' => $panel->id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('module.panel.index')
                ->with('error', 'Ошибка при удалении панели: ' . $e->getMessage());
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
            $strategy = new PanelStrategy(Panel::MARZBAN);
            $isOnline = $strategy->checkOnline($panel->id);

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
     * Configure the panel
     *
     * @param Panel $panel
     * @return \Illuminate\Http\RedirectResponse
     */
    public function configure(Panel $panel)
    {
        try {
            // Создаем новый экземпляр MarzbanService
            $marzbanService = new MarzbanService();
            
            // Обновляем конфигурацию панели
            $marzbanService->updateConfiguration($panel->id);
            
            // Обновляем статус панели
            $panel->update(['panel_status' => Panel::PANEL_CONFIGURED]);
            
            return redirect()->route('module.panel.index')
                ->with('success', 'Панель успешно настроена');
                
        } catch (\Exception $e) {
            Log::error('Panel configuration failed: ' . $e->getMessage(), [
                'panel_id' => $panel->id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('module.panel.index')
                ->with('error', 'Ошибка при настройке панели: ' . $e->getMessage());
        }
    }
}
