<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Repositories\Panel\PanelRepository;
use App\Repositories\Salesman\SalesmanRepository;
use App\Services\Salesman\SalesmanService;
use App\Services\Pack\PackSalesmanService;
use App\Repositories\Pack\PackRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use RuntimeException;

class SalesmanController extends Controller
{
    private SalesmanService $salesmanService;
    private SalesmanRepository $salesmanRepository;
    private PackSalesmanService $packSalesmanService;
    private PackRepository $packRepository;
    private PanelRepository $panelRepository;

    public function __construct(
        SalesmanService     $salesmanService,
        SalesmanRepository  $salesmanRepository,
        PackSalesmanService $packSalesmanService,
        PackRepository      $packRepository,
        PanelRepository     $panelRepository
    )
    {
        $this->salesmanService = $salesmanService;
        $this->salesmanRepository = $salesmanRepository;
        $this->packSalesmanService = $packSalesmanService;
        $this->packRepository = $packRepository;
        $this->panelRepository = $panelRepository;
    }

    /**
     * Display a listing of salesmen
     *
     * @param Request $request
     * @return View
     * @throws Exception
     */
    public function index(Request $request): View
    {
        try {
            Log::info('Accessing salesman list', [
                'source' => 'salesman',
                'user_id' => auth()->id()
            ]);

            $filters = array_filter($request->only(['id', 'telegram_id']));
            $salesmen = $this->salesmanRepository->getPaginated(20, $filters);
            $packs = $this->packRepository->getAllActive();
            $panels = $this->panelRepository->getAllConfiguredPanels();

            return view('module.salesman.index', compact('salesmen', 'filters', 'packs', 'panels'));
        } catch (Exception $e) {
            Log::error('Error accessing salesman list', [
                'source' => 'salesman',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            throw $e;
        }
    }

    /**
     * Toggle salesman status
     *
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            Log::info('Toggling salesman status', [
                'source' => 'salesman',
                'salesman_id' => $id,
                'user_id' => auth()->id()
            ]);

            $salesman = $this->salesmanService->updateStatus($id);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'status' => $salesman->status
            ]);
        } catch (RuntimeException $e) {
            Log::error('Error toggling salesman status', [
                'source' => 'salesman',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'salesman_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function assignPanel(Request $request, int $id): JsonResponse
    {
        try {
            Log::info('Assigning panel to salesman', [
                'source' => 'salesman',
                'salesman_id' => $id,
                'panel_id' => $request->input('panel_id'),
                'user_id' => auth()->id()
            ]);

            $this->salesmanService->assignPanel($id, $request->input('panel_id'));

            return response()->json([
                'success' => true,
                'message' => 'Panel assigned successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Error assigning panel to salesman', [
                'source' => 'salesman',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'salesman_id' => $id,
                'panel_id' => $request->input('panel_id'),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign pack to salesman
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function assignPack(Request $request, int $id): JsonResponse
    {
        try {
            Log::info('Assigning pack to salesman', [
                'source' => 'salesman',
                'salesman_id' => $id,
                'pack_id' => $request->input('pack_id'),
                'user_id' => auth()->id()
            ]);

            $packSalesman = $this->packSalesmanService->create(
                $request->input('pack_id'),
                $id
            );

            return response()->json([
                'success' => true,
                'message' => 'Pack assigned successfully',
                'data' => $packSalesman->getArray()
            ]);
        } catch (Exception $e) {
            Log::error('Error assigning pack to salesman', [
                'source' => 'salesman',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'salesman_id' => $id,
                'pack_id' => $request->input('pack_id'),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
