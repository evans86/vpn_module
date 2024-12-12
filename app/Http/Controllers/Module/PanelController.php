<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Panel\Panel;
use App\Models\Server\Server;
use App\Services\Panel\PanelStrategy;
use App\Repositories\Panel\PanelRepository;
use App\Logging\DatabaseLogger;
use Exception;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;

class PanelController extends Controller
{
    private PanelRepository $panelRepository;
    private DatabaseLogger $logger;

    public function __construct(
        PanelRepository $panelRepository,
        DatabaseLogger $logger
    ) {
        $this->panelRepository = $panelRepository;
        $this->logger = $logger;
    }

    /**
     * Display a listing of panels.
     * @return View|RedirectResponse
     */
    public function index()
    {
        try {
            $this->logger->info('Accessing panels list', [
                'source' => 'panel',
                'user_id' => auth()->id()
            ]);

            $panels = $this->panelRepository->getPaginatedWithRelations();
            $servers = $this->panelRepository->getConfiguredServersWithoutPanels();

            return view('module.panel.index', compact('panels', 'servers'));
        } catch (Exception $e) {
            $this->logger->error('Error accessing panels list', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return back()->withErrors(['msg' => 'Error loading panels list: ' . $e->getMessage()]);
        }
    }

    /**
     * Store a newly created panel.
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request)
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
            $panel = $strategy->create($validated['server_id']);

            DB::commit();

            $this->logger->info('Panel created successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()->route('module.panel.index')
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

            return redirect()->route('module.panel.index')
                ->withErrors(['msg' => 'Error creating panel: ' . $e->getMessage()]);
        }
    }

    /**
     * Configure the specified panel.
     * @param Panel $panel
     * @return RedirectResponse
     */
    public function configure(Panel $panel)
    {
        try {
            $this->logger->info('Configuring panel', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy(Panel::MARZBAN);
            $strategy->updateConfiguration($panel->id);

            $this->logger->info('Panel configured successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->with('success', 'Panel configured successfully');

        } catch (GuzzleException $e) {
            $this->logger->error('Network error configuring panel', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->withErrors(['msg' => 'Network connection error: ' . $e->getMessage()]);

        } catch (Exception $e) {
            $this->logger->error('Error configuring panel', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->withErrors(['msg' => 'Error configuring panel: ' . $e->getMessage()]);
        }
    }

    /**
     * Update panel configuration.
     * @param Panel $panel
     * @return RedirectResponse
     * @throws GuzzleException
     */
    public function updateConfig(Panel $panel)
    {
        try {
            $this->logger->info('Updating panel configuration', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            $strategy = new PanelStrategy(Panel::MARZBAN);
            $strategy->updateConfiguration($panel->id);

            $this->logger->info('Panel configuration updated successfully', [
                'source' => 'panel',
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->with('success', 'Configuration updated successfully');

        } catch (Exception $e) {
            $this->logger->error('Error updating panel configuration', [
                'source' => 'panel',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
                'panel_id' => $panel->id
            ]);

            return redirect()
                ->route('module.panel.index')
                ->withErrors(['msg' => 'Error updating configuration: ' . $e->getMessage()]);
        }
    }

    /**
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
