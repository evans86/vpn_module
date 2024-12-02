<?php

namespace App\Http\Controllers\Module;

use App\Models\PackSalesman\PackSalesman;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;
use App\Services\Pack\PackSalesmanService;

class PackSalesmanController extends Controller
{
    /**
     * @var DatabaseLogger
     */
    private $logger;
    private PackSalesmanService $packSalesmanService;

    public function __construct(LoggerInterface $logger, PackSalesmanService $packSalesmanService)
    {
        $this->logger = $logger;
        $this->packSalesmanService = $packSalesmanService;
    }

    /**
     * @throws Exception
     */
    public function index()
    {
        try {
            $pack_salesmans = PackSalesman::with(['pack', 'salesman'])
                ->orderBy('created_at', 'desc')
                ->paginate(15);

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
     * Отметить пакет как оплаченный
     */
    public function markAsPaid($id): JsonResponse
    {
        try {
            $this->packSalesmanService->success($id);

            return response()->json([
                'success' => true,
                'message' => 'Пакет успешно отмечен как оплаченный'
            ]);
        } catch (Exception $e) {
            $this->logger->error('Ошибка при изменении статуса пакета', [
                'source' => 'pack_salesman',
                'action' => 'mark_as_paid',
                'user_id' => auth()->id(),
                'pack_salesman_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при изменении статуса: ' . $e->getMessage()
            ], 500);
        }
    }
}
