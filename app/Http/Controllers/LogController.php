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
            // Получаем фильтры
            $filters = [
                'level' => $request->get('level'),
                'source' => $request->get('source'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
                'search' => $request->get('search'),
            ];

            // Если не указана дата, ограничиваем последними 7 днями по умолчанию
            if (empty($filters['date_from']) && empty($filters['date_to'])) {
                $filters['date_from'] = now()->subDays(7)->format('Y-m-d');
            }

            // Get logs with filters and pagination
            $logs = $this->logRepository->getPaginatedWithFilters($filters, 30);

            // Get unique sources for filter with caching (увеличено до 5 минут)
            $sources = cache()->remember('log_sources', 300, function () {
                return $this->logRepository->getUniqueSources();
            });

            // Получаем статистику по уровням (кэшируем на 60 секунд)
            $stats = cache()->remember('log_stats_' . md5(json_encode($filters)), 60, function () use ($filters) {
                return $this->logRepository->getLevelStats($filters);
            });

            if ($request->ajax()) {
                return response()->json([
                    'html' => view('logs.partials.table', compact('logs'))->render(),
                    'stats' => $stats,
                    'pagination' => view('logs.partials.pagination', compact('logs'))->render(),
                    'total' => $logs->total(),
                    'count' => $logs->count()
                ]);
            }

            return response()->view('logs.index', [
                'logs' => $logs,
                'sources' => $sources,
                'stats' => $stats,
                'filters' => $filters
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
