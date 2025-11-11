<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\VPN\ConnectionLimitViolation;
use App\Services\VPN\ConnectionLimitMonitorService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionLimitViolationController extends Controller
{
    private ConnectionLimitMonitorService $monitorService;

    public function __construct(ConnectionLimitMonitorService $monitorService)
    {
        $this->monitorService = $monitorService;
    }

    /**
     * Display a listing of connection limit violations
     */
    public function index(Request $request): View
    {
        $query = ConnectionLimitViolation::with([
            'keyActivate',
            'serverUser',
            'panel.server'
        ])->latest();

        // Фильтрация по статусу
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Фильтрация по панели
        if ($request->filled('panel_id')) {
            $query->where('panel_id', $request->panel_id);
        }

        // Фильтрация по дате
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $violations = $query->paginate(config('app.items_per_page', 30));

        // Статистика для виджетов
        $stats = $this->monitorService->getViolationStats();

        return view('module.connection-limit-violations.index', compact('violations', 'stats'));
    }

    /**
     * Show violation details
     */
    public function show(ConnectionLimitViolation $violation): View
    {
        $violation->load([
            'keyActivate.packSalesman.pack',
            'serverUser',
            'panel.server'
        ]);

        return view('module.connection-limit-violations.show', compact('violation'));
    }

    /**
     * Mark violation as resolved
     */
    public function resolve(ConnectionLimitViolation $violation)
    {
        try {
            $this->monitorService->resolveViolation($violation);

            return redirect()->back()->with('success', 'Нарушение помечено как решенное');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Mark violation as ignored
     */
    public function ignore(ConnectionLimitViolation $violation)
    {
        try {
            $this->monitorService->ignoreViolation($violation);

            return redirect()->back()->with('success', 'Нарушение помечено как проигнорированное');

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Get violation statistics
     */
    public function stats()
    {
        $stats = $this->monitorService->getViolationStats();

        return response()->json($stats);
    }
}
