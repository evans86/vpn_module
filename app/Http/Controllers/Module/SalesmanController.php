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
    /**
     * @var SalesmanService
     */
    private SalesmanService $salesmanService;
    /**
     * @var SalesmanRepository
     */
    private SalesmanRepository $salesmanRepository;
    /**
     * @var PackSalesmanService
     */
    private PackSalesmanService $packSalesmanService;
    /**
     * @var PackRepository
     */
    private PackRepository $packRepository;
    /**
     * @var PanelRepository
     */
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

            $filters = array_filter($request->only(['id', 'telegram_id', 'username', 'bot_link']));
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
     * Display salesman dashboard
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        try {
            $salesman = $this->salesmanRepository->findById($id);
            $packs = $this->packRepository->getAllActive();
            $panels = $this->panelRepository->getAllConfiguredPanels();

            // Статистика продавца (нужно реализовать соответствующие методы в репозиториях)
//            $stats = [
//                'total_clients' => $this->salesmanRepository->getTotalClientsCount($id),
//                'active_clients' => $this->salesmanRepository->getActiveClientsCount($id),
//                'total_income' => $this->salesmanRepository->getTotalIncome($id),
//            ];

            // Активности продавца (логи действий)
//            $activities = $this->salesmanRepository->getActivities($id)->paginate(10);

            return view('module.salesman.show', compact(
                'salesman',
                'packs',
                'panels',
//                'stats',
//                'activities'
            ));
        } catch (Exception $e) {
            Log::error('Error accessing salesman dashboard', [
                'source' => 'salesman',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'salesman_id' => $id,
                'user_id' => auth()->id()
            ]);

            abort(404, 'Продавец не найден');
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

    /**
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
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
     * Reset panel for salesman
     *
     * @param int $id
     * @return JsonResponse
     */
    public function resetPanel(int $id): JsonResponse
    {
        try {
            Log::info('Resetting panel for salesman', [
                'source' => 'salesman',
                'salesman_id' => $id,
                'user_id' => auth()->id()
            ]);

            $this->salesmanService->resetPanel($id);

            return response()->json([
                'success' => true,
                'message' => 'Панель успешно отвязана',
            ]);
        } catch (Exception $e) {
            Log::error('Error resetting panel for salesman', [
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

    /**
     * Обновить токен бота продавца
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateBotToken(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string'
            ]);

            Log::info('Updating bot token for salesman', [
                'source' => 'salesman',
                'salesman_id' => $id,
                'user_id' => auth()->id()
            ]);

            $this->salesmanService->updateBotToken($id, $request->input('token'));

            return response()->json([
                'success' => true,
                'message' => 'Токен бота успешно обновлен',
            ]);
        } catch (Exception $e) {
            Log::error('Error updating bot token', [
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

    /**
     * Ручная установка/смена модуля VPN (ID модуля) для продавца
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateModule(Request $request, int $id): JsonResponse
    {
        try {
            $request->validate([
                'module_bot_id' => 'nullable|integer|min:0',
            ]);

            $salesman = $this->salesmanRepository->findById($id);
            $salesman->module_bot_id = $request->input('module_bot_id') ?: null;
            $salesman->save();

            return response()->json([
                'success' => true,
                'message' => 'Модуль VPN успешно обновлён',
                'module_bot_id' => $salesman->module_bot_id,
            ]);
        } catch (Exception $e) {
            Log::error('Error updating salesman module', [
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
}
