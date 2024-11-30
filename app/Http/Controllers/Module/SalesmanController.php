<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Salesman\Salesman;
use App\Services\Salesman\SalesmanService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SalesmanController extends Controller
{
    private SalesmanService $salesmanService;

    public function __construct(SalesmanService $salesmanService)
    {
        $this->salesmanService = $salesmanService;
    }

    public function index()
    {
        try {
            Log::info('Accessing salesman list', [
                'source' => 'salesman',
                'user_id' => auth()->id()
            ]);

            $salesmen = Salesman::query()
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return view('module.salesman.index', compact('salesmen'));
        } catch (\Exception $e) {
            Log::error('Error accessing salesman list', [
                'source' => 'salesman',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return back()->with('error', 'Error loading salesman list: ' . $e->getMessage());
        }
    }

    /**
     * Toggle salesman status
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function toggleStatus(Request $request, int $id)
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
        } catch (\Exception $e) {
            Log::error('Error toggling salesman status', [
                'source' => 'salesman',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'salesman_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating status: ' . $e->getMessage()
            ], 500);
        }
    }
}
