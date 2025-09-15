<?php

namespace App\Http\Controllers;

use App\Models\Log\ApplicationLog;
use App\Repositories\Log\LogRepository;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogController extends Controller
{
    /**
     * @var LogRepository
     */
    private LogRepository $logRepository;

    public function __construct(LogRepository $logRepository)
    {
        $this->logRepository = $logRepository;
    }

    /**
     * Display logs list
     * @param Request $request
     * @return Response
     */
    public function index(Request $request): Response
    {
        try {
            // Clean old logs (older than 30 days)
            $deletedCount = $this->logRepository->cleanOldLogs(15);
            if ($deletedCount > 0) {
                Log::info('Cleaned old logs', [
                    'source' => 'system',
                    'deleted_count' => $deletedCount
                ]);
            }

            // Get logs with filters and pagination
            $logs = $this->logRepository->getPaginatedWithFilters([
                'level' => $request->get('level'),
                'source' => $request->get('source'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'search' => $request->get('search'),
            ]);

            // Get unique sources for filter with caching
            $sources = cache()->remember('log_sources', 60, function () {
                return $this->logRepository->getUniqueSources();
            });

            if ($request->ajax()) {
                return response()->view('logs.partials.table', compact('logs'));
            }

            return response()->view('logs.index', [
                'logs' => $logs,
                'sources' => $sources,
                'filters' => [
                    'level' => $request->get('level'),
                    'source' => $request->get('source'),
                    'date_from' => $request->get('date_from'),
                    'date_to' => $request->get('date_to'),
                    'search' => $request->get('search'),
                ]
            ]);
        } catch (Exception $e) {
            Log::error('Error displaying logs', [
                'source' => 'system',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            if ($request->ajax()) {
                return response()->json(['error' => 'Error loading logs: ' . $e->getMessage()], 500);
            }

            return back()->with('error', 'Error displaying logs: ' . $e->getMessage());
        }
    }

    /**
     * @param ApplicationLog $log
     * @return Response
     */
    public function show(ApplicationLog $log): Response
    {
        return response()->view('logs.show', compact('log'));
    }
}
