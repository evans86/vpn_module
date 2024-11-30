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
     */
    public function index()
    {
        try {
            Log::info('Accessing panels list', [
                'source' => 'panel',
                'user_id' => auth()->id()
            ]);

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
        } catch (\Exception $e) {
            Log::error('Error accessing panels list', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return back()->with('error', 'Error loading panels list: ' . $e->getMessage());
        }
    }

    /**
     * Store a newly created panel.
     */
    public function store(Request $request)
    {
        try {
            Log::info('Creating new panel', [
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
            $panel = $strategy->create($validated['server_id']);

            DB::commit();

            Log::info('Panel created successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'server_id' => $panel->server_id
            ]);

            return redirect()->route('module.panel.index')
                ->with('success', 'Panel created successfully');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating panel', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'data' => $request->all()
            ]);

            return redirect()->route('module.panel.index')
                ->with('error', 'Error creating panel: ' . $e->getMessage());
        }
    }

    /**
     * Configure the specified panel.
     */
    public function configure(Panel $panel)
    {
        try {
            Log::info('Configuring panel', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy(Panel::MARZBAN);
            $strategy->updateConfiguration($panel->id);

            Log::info('Panel configured successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->with('success', 'Panel configured successfully');

        } catch (GuzzleException $e) {
            Log::error('Network error configuring panel', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->with('error', 'Network connection error: ' . $e->getMessage());

        } catch (\Exception $e) {
            Log::error('Error configuring panel', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->with('error', 'Error configuring panel: ' . $e->getMessage());
        }
    }

    /**
     * Update panel configuration.
     */
    public function updateConfig(Panel $panel)
    {
        try {
            Log::info('Updating panel configuration', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy(Panel::MARZBAN);
            $strategy->updateConfiguration($panel->id);

            Log::info('Panel configuration updated successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->with('success', 'Configuration updated successfully');

        } catch (\Exception $e) {
            Log::error('Error updating panel configuration', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->with('error', 'Error updating configuration: ' . $e->getMessage());
        }
    }

    /**
     * Check the status of the specified panel.
     */
    public function checkStatus(Panel $panel)
    {
        try {
            Log::info('Checking panel status', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $marzbanApi = new MarzbanAPI($panel->panel_api_address);
            $isOnline = $marzbanApi->checkOnline($panel->id);

            Log::info('Panel status checked', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id,
                'status' => $isOnline ? 'online' : 'offline'
            ]);

            return response()->json([
                'status' => $isOnline ? 'online' : 'offline'
            ]);

        } catch (\Exception $e) {
            Log::error('Error checking panel status', [
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
