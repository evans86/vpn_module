<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\VPN\ConnectionLimitViolation;
use App\Services\VPN\ConnectionLimitMonitorService;
use App\Services\VPN\ViolationManualService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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
        try {
            // Временное увеличение лимита памяти для диагностики
            if (app()->environment('local')) {
                ini_set('memory_limit', '256M');
            }

            $query = ConnectionLimitViolation::query();

            // ОПТИМИЗАЦИЯ: Выбираем только необходимые поля и загружаем отношения с выбором полей
            $query->select([
                'id',
                'key_activate_id',
                'panel_id',
                'server_user_id',
                'user_tg_id',
                'violation_count',
                'first_detected_at',
                'last_detected_at',
                'status',
                'resolved_at',
                'created_at',
                'updated_at'
            ]);

            // ОПТИМИЗАЦИЯ: Загружаем отношения с выбором только нужных полей
            $query->with([
                'keyActivate:id,pack_salesman_id,module_salesman_id,user_tg_id,status' => [
                    'packSalesman:id,salesman_id' => [
                        'salesman:id,name,telegram_id'
                    ],
                    'moduleSalesman:id,name'
                ],
                'serverUser:id,username,panel_id',
                'panel:id,name,server_id' => [
                    'server:id,name,location_id'
                ]
            ]);

            // Сортировка с использованием индексов
            $query->orderBy('created_at', 'desc');

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
                            $q->select('id')->where('id', 'LIKE', "%{$search}%");
                        });
                });
            }

            // ОПТИМИЗАЦИЯ: Ограничиваем количество записей и используем простую пагинацию
            $violations = $query->paginate(config('app.items_per_page', 20)) // Уменьшено с 30 до 20
            ->onEachSide(1); // Уменьшаем количество страниц для пагинации

            // Статистика для виджетов - с ограничением записей
            $stats = $this->getOptimizedViolationStats();

            // ОПТИМИЗАЦИЯ: Загружаем панели с ограничением
            $panels = \App\Models\Panel\Panel::where('panel_status', \App\Models\Panel\Panel::PANEL_CONFIGURED)
                ->select(['id', 'name', 'server_id', 'panel_status'])
                ->with(['server:id,name'])
                ->limit(50) // Ограничиваем количество панелей
                ->get();

            // Логируем использование памяти для отладки
            if (app()->environment('local')) {
                Log::debug('ConnectionLimitViolationController memory usage: ' .
                    round(memory_get_usage() / 1024 / 1024, 2) . ' MB');
                Log::debug('Violations count: ' . $violations->total());
            }

            return view('module.connection-limit-violations.index', compact('violations', 'stats', 'panels'));

        } catch (\Exception $e) {
            Log::error('Error in ConnectionLimitViolationController@index', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'memory_usage' => round(memory_get_usage() / 1024 / 1024, 2) . ' MB'
            ]);

            // Возвращаем страницу с минимальными данными при ошибке
            return view('module.connection-limit-violations.index', [
                'violations' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 20),
                'stats' => [
                    'total' => 0,
                    'active' => 0,
                    'resolved' => 0,
                    'ignored' => 0
                ],
                'panels' => [],
                'error' => 'Произошла ошибка при загрузке данных. Попробуйте обновить страницу или уточнить фильтры.'
            ]);
        }
    }

    /**
     * Оптимизированная статистика
     */
    private function getOptimizedViolationStats(): array
    {
        return Cache::remember('violation_stats', 300, function () { // Кэшируем на 5 минут
            return [
                'total' => DB::table('connection_limit_violations')
                    ->selectRaw('COUNT(*) as count')
                    ->value('count'),
                'active' => DB::table('connection_limit_violations')
                    ->where('status', ConnectionLimitViolation::STATUS_ACTIVE)
                    ->selectRaw('COUNT(*) as count')
                    ->value('count'),
                'resolved' => DB::table('connection_limit_violations')
                    ->where('status', ConnectionLimitViolation::STATUS_RESOLVED)
                    ->selectRaw('COUNT(*) as count')
                    ->value('count'),
                'ignored' => DB::table('connection_limit_violations')
                    ->where('status', ConnectionLimitViolation::STATUS_IGNORED)
                    ->selectRaw('COUNT(*) as count')
                    ->value('count'),
                'today' => DB::table('connection_limit_violations')
                    ->whereDate('created_at', today())
                    ->selectRaw('COUNT(*) as count')
                    ->value('count')
            ];
        });
    }

    /**
     * Ручная проверка нарушений
     */
    public function manualCheck(Request $request)
    {
        try {
            $threshold = $request->get('threshold', 3);
            $windowMinutes = $request->get('window', 15);

            $results = $this->manualService->manualViolationCheck($threshold, $windowMinutes);

            return redirect()->route('admin.module.connection-limit-violations.index')
                ->with('success', "Проверка завершена. Найдено нарушений: {$results['violations_found']}")
                ->with('check_results', $results);

        } catch (\Exception $e) {
            Log::error('Error in manualCheck', ['error' => $e->getMessage()]);
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

        $isAjax = $request->expectsJson() || $request->ajax();

        if (empty($violationIds)) {
            if ($isAjax) {
                return response()->json(['success' => false, 'message' => 'Не выбраны нарушения']);
            }
            return redirect()->back()->with('error', 'Не выбраны нарушения');
        }

        try {
            // ОПТИМИЗАЦИЯ: Ограничиваем количество обрабатываемых записей
            $violationIds = array_slice($violationIds, 0, 100); // Максимум 100 записей

            $count = 0;
            $message = '';

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

                case 'reissue_keys':
                    $count = $this->manualService->bulkReplaceKeys($violationIds);
                    $message = "Перевыпущено ключей: {$count}";
                    break;

                case 'delete':
                    $count = $this->manualService->bulkDelete($violationIds);
                    $message = "Удалено нарушений: {$count}";
                    break;

                default:
                    if ($isAjax) {
                        return response()->json(['success' => false, 'message' => 'Неизвестное действие']);
                    }
                    return redirect()->back()->with('error', 'Неизвестное действие');
            }

            // Очищаем кэш статистики
            Cache::forget('violation_stats');

            if ($isAjax) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'count' => $count
                ]);
            }

            return redirect()->back()->with('success', $message);

        } catch (\Exception $e) {
            Log::error('Error in bulkActions', ['error' => $e->getMessage()]);

            if ($isAjax) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка: ' . $e->getMessage()
                ]);
            }
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
            $result = null;

            switch ($action) {
                case 'send_notification':
                    $this->manualService->sendUserNotification($violation);
                    $message = 'Уведомление отправлено пользователю';
                    break;

                case 'reissue_key':
                    $newKey = $this->manualService->reissueKey($violation);
                    $message = "Ключ перевыпущен. Новый ключ: {$newKey->id}";
                    $result = ['new_key_id' => $newKey->id];
                    break;

                case 'ignore':
                    $this->manualService->ignoreViolation($violation);
                    $message = 'Нарушение помечено как игнорированное';
                    break;

                default:
                    return response()->json(['success' => false, 'message' => 'Неизвестное действие']);
            }

            // Очищаем кэш статистики
            Cache::forget('violation_stats');

            return response()->json(array_merge(
                ['success' => true, 'message' => $message],
                $result ?? []
            ));

        } catch (\Exception $e) {
            Log::error('Error in manageViolation', ['violation_id' => $violation->id, 'error' => $e->getMessage()]);
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

            // Очищаем кэш статистики
            Cache::forget('violation_stats');

            return response()->json([
                'success' => true,
                'new_status' => $violation->fresh()->status,
                'status_color' => $violation->fresh()->status_color,
                'status_icon' => $violation->fresh()->status_icon
            ]);

        } catch (\Exception $e) {
            Log::error('Error in quickAction', ['violation_id' => $violation->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Показать детальную информацию о нарушении
     */
    public function show(ConnectionLimitViolation $violation): View
    {
        // ОПТИМИЗАЦИЯ: Загружаем только необходимые поля
        $violation->load([
            'keyActivate:id,pack_salesman_id,module_salesman_id,user_tg_id,status,key' => [
                'packSalesman:id,salesman_id' => [
                    'salesman:id,name,telegram_id'
                ],
                'moduleSalesman:id,name'
            ],
            'serverUser:id,username,panel_id,key_activate_id',
            'panel:id,name,server_id' => [
                'server:id,name,location_id'
            ]
        ]);

        return view('module.connection-limit-violations.show', compact('violation'));
    }
}
