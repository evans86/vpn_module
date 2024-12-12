<?php

namespace App\Http\Controllers\Module;

use App\Models\PackSalesman\PackSalesman;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use RuntimeException;
use App\Services\Pack\PackSalesmanService;

class PackSalesmanController extends Controller
{
    private DatabaseLogger $logger;
    private PackSalesmanService $packSalesmanService;
    private PackSalesmanRepository $packSalesmanRepository;

    public function __construct(
        DatabaseLogger $logger,
        PackSalesmanService $packSalesmanService,
        PackSalesmanRepository $packSalesmanRepository
    ) {
        $this->logger = $logger;
        $this->packSalesmanService = $packSalesmanService;
        $this->packSalesmanRepository = $packSalesmanRepository;
    }

    /**
     * Display a listing of pack-salesman relations
     * @throws Exception
     */
    public function index(): View
    {
        try {
            $pack_salesmans = $this->packSalesmanRepository->getPaginatedWithRelations();

            $this->logger->info('Просмотр списка связей пакет-продавец', [
                'source' => 'pack_salesman',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'total_relations' => $pack_salesmans->total(),
                'page' => $pack_salesmans->currentPage()
            ]);

            return view('module.pack-salesman.index', compact('pack_salesmans'));
        } catch (Exception $e) {
            $this->logger->error('Ошибка при загрузке списка связей пакет-продавец', [
                'source' => 'pack_salesman',
                'action' => 'view_list',
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Mark pack as paid
     * @param int $id
     * @return JsonResponse
     * @throws Exception
     */
    public function markAsPaid(int $id): JsonResponse
    {
        try {
            $this->packSalesmanService->success($id);

            return response()->json([
                'success' => true,
                'message' => 'Пакет успешно отмечен как оплаченный'
            ]);
        } catch (RuntimeException $e) {
            $this->logger->error('Ошибка при изменении статуса пакета', [
                'source' => 'pack_salesman',
                'action' => 'mark_as_paid',
                'user_id' => auth()->id(),
                'pack_salesman_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
