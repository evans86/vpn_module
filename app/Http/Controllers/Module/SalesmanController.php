<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Salesman\Salesman;
use App\Repositories\Salesman\SalesmanRepository;
use App\Services\Salesman\SalesmanService;
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

    public function __construct(
        SalesmanService $salesmanService,
        SalesmanRepository $salesmanRepository
    ) {
        $this->salesmanService = $salesmanService;
        $this->salesmanRepository = $salesmanRepository;
    }

    /**
     * Display a listing of salesmen
     * @return View
     * @throws Exception
     */
    public function index(): View
    {
        try {
            Log::info('Accessing salesman list', [
                'source' => 'salesman',
                'user_id' => auth()->id()
            ]);

            $salesmen = $this->salesmanRepository->getPaginated();

            return view('module.salesman.index', compact('salesmen'));
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
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, int $id): JsonResponse
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
}
