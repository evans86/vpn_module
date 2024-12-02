<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Models\Log\ApplicationLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    /**
     * Показать список логов с фильтрацией
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        // Получаем параметры фильтрации из запроса
        $level = $request->input('level');
        $source = $request->input('source');
        $search = $request->input('search');
        $userId = $request->input('user_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');
        $perPage = $request->input('per_page', 15);

        // Строим запрос с фильтрами
        $query = ApplicationLog::query()
            ->when($level, function ($query) use ($level) {
                return $query->byLevel($level);
            })
            ->when($source, function ($query) use ($source) {
                return $query->bySource($source);
            })
            ->when($search, function ($query) use ($search) {
                return $query->searchMessage($search);
            })
            ->when($userId, function ($query) use ($userId) {
                return $query->byUser($userId);
            })
            ->when($startDate, function ($query) use ($startDate, $endDate) {
                return $query->byDateRange($startDate, $endDate);
            });

        // Получаем отфильтрованные логи с пагинацией
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Получаем списки для фильтров
        $sources = ApplicationLog::getSourcesList();
        $levels = ApplicationLog::getLevelsList();

        // Очищаем старые логи при просмотре страницы
        ApplicationLog::cleanOldLogs();

        return view('module.log.index', compact(
            'logs',
            'sources',
            'levels',
            'level',
            'source',
            'search',
            'userId',
            'startDate',
            'endDate'
        ));
    }

    /**
     * Очистить все логи
     *
     * @return RedirectResponse
     */
    public function clear(): RedirectResponse
    {
        try {
            ApplicationLog::truncate();
            return redirect()->route('module.log.index')->with('success', 'Все логи успешно удалены');
        } catch (\Exception $e) {
            return redirect()->route('module.log.index')->with('error', 'Ошибка при удалении логов: ' . $e->getMessage());
        }
    }

    /**
     * Получить детали лога
     *
     * @param int $id
     * @return View
     */
    public function show(int $id): View
    {
        $log = ApplicationLog::findOrFail($id);
        return view('module.log.show', compact('log'));
    }

    /**
     * Экспорт логов в CSV
     *
     * @param Request $request
     * @return StreamedResponse
     */
    public function export(Request $request): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="logs.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0'
        ];

        // Получаем параметры фильтрации из запроса
        $level = $request->input('level');
        $source = $request->input('source');
        $search = $request->input('search');
        $userId = $request->input('user_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        // Строим запрос с теми же фильтрами
        $query = ApplicationLog::query()
            ->when($level, function ($query) use ($level) {
                return $query->byLevel($level);
            })
            ->when($source, function ($query) use ($source) {
                return $query->bySource($source);
            })
            ->when($search, function ($query) use ($search) {
                return $query->searchMessage($search);
            })
            ->when($userId, function ($query) use ($userId) {
                return $query->byUser($userId);
            })
            ->when($startDate, function ($query) use ($startDate, $endDate) {
                return $query->byDateRange($startDate, $endDate);
            })
            ->orderBy('created_at', 'desc');

        $callback = function () use ($query) {
            $file = fopen('php://output', 'w');

            // Заголовки CSV
            fputcsv($file, [
                'ID',
                'Уровень',
                'Источник',
                'Сообщение',
                'Контекст',
                'ID пользователя',
                'IP адрес',
                'User Agent',
                'Создан',
                'Обновлен'
            ]);

            // Записываем данные
            $query->chunk(1000, function ($logs) use ($file) {
                foreach ($logs as $log) {
                    fputcsv($file, [
                        $log->id,
                        $log->level,
                        $log->source,
                        $log->message,
                        json_encode($log->context, JSON_UNESCAPED_UNICODE),
                        $log->user_id,
                        $log->ip_address,
                        $log->user_agent,
                        $log->created_at,
                        $log->updated_at
                    ]);
                }
            });

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
