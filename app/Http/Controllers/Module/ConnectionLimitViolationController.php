<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\VPN\ConnectionLimitViolation;
use App\Services\VPN\ConnectionLimitMonitorService;
use App\Services\VPN\ViolationManualService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ConnectionLimitViolationController extends Controller
{
    private ConnectionLimitMonitorService $monitorService;
    private ViolationManualService $manualService;

    public function __construct(
        ConnectionLimitMonitorService $monitorService,
        ViolationManualService $manualService
    ) {
        $this->monitorService = $monitorService;
        $this->manualService = $manualService;
    }

    /**
     * Display a listing of connection limit violations
     */
    public function index(Request $request): View
    {
        $query = ConnectionLimitViolation::with([
            'keyActivate.packSalesman.salesman',
            'keyActivate.moduleSalesman',
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

        // Фильтрация по количеству нарушений
        if ($request->filled('violation_count')) {
            $query->where('violation_count', '>=', $request->violation_count);
        }

        // Фильтрация по дате
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Поиск по ID ключа или пользователя
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('user_tg_id', 'LIKE', "%{$search}%")
                    ->orWhereHas('keyActivate', function($q) use ($search) {
                        $q->where('id', 'LIKE', "%{$search}%");
                    });
            });
        }

        $violations = $query->paginate(config('app.items_per_page', 30));

        // Статистика для виджетов
        $stats = $this->monitorService->getViolationStats();

        // Дополнительные данные для фильтров (с eager loading для оптимизации)
        $panels = \App\Models\Panel\Panel::where('panel_status', \App\Models\Panel\Panel::PANEL_CONFIGURED)
            ->with('server.location')
            ->get();

        return view('module.connection-limit-violations.index', compact('violations', 'stats', 'panels'));
    }

    /**
     * Ручная проверка нарушений
     */
    public function manualCheck(Request $request)
    {
        try {
            $threshold = $request->get('threshold', 2);
            $windowMinutes = $request->get('window', 60);

            $results = $this->manualService->manualViolationCheck($threshold, $windowMinutes);

            return redirect()->route('admin.module.connection-limit-violations.index')
                ->with('success', "Проверка завершена. Найдено нарушений: {$results['violations_found']}")
                ->with('check_results', $results);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка при проверке: ' . $e->getMessage());
        }
    }

    /**
     * Массовые действия с нарушениями
     */
    public function bulkActions(Request $request)
    {
        $action = $request->input('action');
        $violationIds = $request->input('violation_ids', []);

        if (empty($violationIds)) {
            return redirect()->back()->with('error', 'Не выбраны нарушения');
        }

        try {
            $count = 0;

            switch ($action) {
                case 'resolve':
                    $count = $this->manualService->bulkResolve($violationIds);
                    $message = "Помечено как решенные: {$count} нарушений";
                    break;

                case 'ignore':
                    $count = $this->manualService->bulkIgnore($violationIds);
                    $message = "Помечено как игнорированные: {$count} нарушений";
                    break;

                case 'notify':
                    $count = $this->manualService->bulkNotify($violationIds);
                    $message = "Отправлено уведомлений: {$count}";
                    break;

                case 'replace_key':
                    $count = $this->manualService->bulkReplaceKeys($violationIds);
                    $message = "Заменено ключей: {$count}";
                    break;

                case 'delete':
                    $count = $this->manualService->bulkDelete($violationIds);
                    $message = "Удалено нарушений: {$count}";
                    break;

                default:
                    return redirect()->back()->with('error', 'Неизвестное действие');
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ошибка: ' . $e->getMessage());
        }
    }

    /**
     * Детальное управление одним нарушением
     */
    public function manageViolation(Request $request, ConnectionLimitViolation $violation)
    {
        $action = $request->input('action');

        try {
            switch ($action) {
                case 'send_notification':
                    $this->manualService->sendUserNotification($violation);
                    $message = 'Уведомление отправлено пользователю';
                    break;

                case 'reissue_key':
                    $newKey = $this->manualService->reissueKey($violation);
                    $message = "Ключ перевыпущен. Новый ключ: {$newKey->id}";
                    break;

                case 'ignore':
                    $this->manualService->ignoreViolation($violation);
                    $message = 'Нарушение помечено как игнорированное';
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Неизвестное действие']);
            }

            return response()->json(['success' => true, 'message' => $message, 'new_key_id' => $newKey->id ?? null]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
        }
    }

    /**
     * Быстрое действие без перезагрузки страницы (AJAX)
     */
    public function quickAction(Request $request, ConnectionLimitViolation $violation)
    {
        $action = $request->input('action');

        try {
            switch ($action) {
                case 'resolve':
                    $this->monitorService->resolveViolation($violation);
                    break;

                case 'ignore':
                    $this->monitorService->ignoreViolation($violation);
                    break;

                case 'toggle_status':
                    if ($violation->status === ConnectionLimitViolation::STATUS_ACTIVE) {
                        $this->monitorService->resolveViolation($violation);
                    } else {
                        $violation->status = ConnectionLimitViolation::STATUS_ACTIVE;
                        $violation->resolved_at = null;
                        $violation->save();
                    }
                    break;

                default:
                    return response()->json(['error' => 'Unknown action'], 400);
            }

            return response()->json([
                'success' => true,
                'new_status' => $violation->fresh()->status,
                'status_color' => $violation->fresh()->status_color,
                'status_icon' => $violation->fresh()->status_icon
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
