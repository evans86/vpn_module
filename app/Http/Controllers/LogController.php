<?php

namespace App\Http\Controllers;

use App\Models\Log\ApplicationLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogController extends Controller
{
    /**
     * Display logs list
     */
    public function index(Request $request)
    {
        try {
            // Clean old logs (older than 30 days)
            $deletedCount = ApplicationLog::cleanOldLogs(30);
            if ($deletedCount > 0) {
                Log::info('Cleaned old logs', [
                    'source' => 'system',
                    'deleted_count' => $deletedCount
                ]);
            }

            $query = ApplicationLog::query();

            // Filter by level if specified
            if ($request->filled('level')) {
                $query->byLevel($request->get('level'));
            }

            // Filter by source if specified
            if ($request->filled('source')) {
                $query->bySource($request->get('source'));
            }

            // Filter by date range if specified
            if ($request->filled('date_from') || $request->filled('date_to')) {
                $query->byDateRange(
                    $request->get('date_from', now()->subDays(7)->toDateString()),
                    $request->get('date_to')
                );
            }

            // Search by message if specified
            if ($request->filled('search')) {
                $searchTerm = $request->get('search');
                $query->where(function($q) use ($searchTerm) {
                    $q->where('message', 'like', '%' . $searchTerm . '%')
                      ->orWhere('context', 'like', '%' . $searchTerm . '%');
                });
            }

            // Get unique sources for filter with caching
            $sources = cache()->remember('log_sources', 60, function() {
                return ApplicationLog::distinct()
                    ->orderBy('source')
                    ->pluck('source');
            });
            
            // Get logs with pagination
            $logs = $query->orderBy('created_at', 'desc')
                         ->paginate(50)
                         ->withQueryString();

            if ($request->ajax()) {
                return view('logs.partials.table', compact('logs'));
            }

            return view('logs.index', [
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
        } catch (\Exception $e) {
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

    public function show(ApplicationLog $log)
    {
        return view('logs.show', compact('log'));
    }
}
