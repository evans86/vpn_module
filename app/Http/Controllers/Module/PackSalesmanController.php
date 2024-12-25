<?php

namespace App\Http\Controllers\Module;

use App\Models\PackSalesman\PackSalesman;
use App\Logging\DatabaseLogger;
use App\Http\Controllers\Controller;
use App\Models\Salesman\Salesman;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\Request;
use RuntimeException;
use App\Services\Pack\PackSalesmanService;
use Carbon\Carbon;

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
    public function index(Request $request): View
    {
        try {
            $query = PackSalesman::with(['pack', 'salesman']);

            // Фильтр по id
            if ($request->has('id')) {
                $query->where('id', $request->id);
            }

            // Фильтр по продавцу (поиск по telegram_id или username)
            if ($request->filled('salesman_search')) {
                $search = $request->salesman_search;
                $query->whereHas('salesman', function($q) use ($search) {
                    $q->where('telegram_id', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
                });
            }

            // Фильтр по статусу
            if ($request->filled('status')) {
                if ($request->status === 'paid') {
                    $query->where('status', PackSalesman::PAID);
                } elseif ($request->status === 'pending') {
                    $query->where('status', PackSalesman::NOT_PAID);
                }
            }

            // Фильтр по дате создания
            if ($request->filled('created_at')) {
                $date = Carbon::parse($request->created_at);
                $query->whereDate('created_at', $date);
            }

            $pack_salesmans = $query->latest()->paginate(10);

            $this->logger->info('Просмотр списка связей пакет-продавец', [
                'source' => 'pack_salesman',
                'action' => 'view',
                'user_id' => auth()->id(),
                'page' => $pack_salesmans->currentPage()
            ]);

            return view('module.pack-salesman.index', compact('pack_salesmans'));
        } catch (Exception $e) {
            $this->logger->error('Ошибка при загрузке списка связей пакет-продавец', [
                'source' => 'pack_salesman',
                'action' => 'view',
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
