<?php

namespace App\Repositories\Log;

use App\Models\Log\ApplicationLog;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class LogRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return ApplicationLog::class;
    }

    public function getPaginatedWithFilters(array $filters, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->query()
            ->select([
                'id',
                'created_at',
                'level',
                'source',
                DB::raw('CASE WHEN LENGTH(message) > 100 THEN CONCAT(SUBSTRING(message, 1, 100), "...") ELSE message END as message_short'),
                'user_id'
            ]);

        // Фильтр по уровню
        if (!empty($filters['level'])) {
            $query->byLevel($filters['level']);
        }

        // Фильтр по источнику
        if (!empty($filters['source'])) {
            $query->bySource($filters['source']);
        }

        // Фильтр по дате (обязательно ограничиваем для производительности)
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $start = $filters['date_from'] ?? now()->subDays(7)->toDateString();
            $end = $filters['date_to'] ?? now()->toDateString();

                $query->whereBetween('created_at', [
                $this->createDateTime($start, '00:00:00'),
                $this->createDateTime($end, '23:59:59')
                ]);
            } else {
            // По умолчанию ограничиваем последними 7 днями для производительности
            $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
        }

        // Поиск (оптимизированный - только если больше 2 символов)
        if (!empty($filters['search']) && strlen(trim($filters['search'])) > 2) {
            $searchTerm = trim($filters['search']);
            // Используем индекс на created_at для оптимизации
            $query->where(function($q) use ($searchTerm) {
                $q->where('message', 'like', '%' . $searchTerm . '%')
                  ->orWhere('source', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc') // Дополнительная сортировка для стабильности
            ->paginate($perPage)
            ->appends($filters);
    }

    /**
     * Получить статистику по уровням логов
     *
     * @param array $filters
     * @return array
     */
    public function getLevelStats(array $filters = []): array
    {
        $query = $this->query();

        // Применяем те же фильтры что и для основного запроса
        if (!empty($filters['source'])) {
            $query->bySource($filters['source']);
        }

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $start = $filters['date_from'] ?? now()->subDays(7)->toDateString();
            $end = $filters['date_to'] ?? now()->toDateString();
            $query->whereBetween('created_at', [
                $this->createDateTime($start, '00:00:00'),
                $this->createDateTime($end, '23:59:59')
            ]);
        } else {
            $query->where('created_at', '>=', now()->subDays(7)->startOfDay());
        }

        $stats = $query->select('level', DB::raw('COUNT(*) as count'))
            ->groupBy('level')
            ->pluck('count', 'level')
            ->toArray();

        return [
            'error' => ($stats['error'] ?? 0) + ($stats['critical'] ?? 0) + ($stats['emergency'] ?? 0),
            'warning' => $stats['warning'] ?? 0,
            'info' => $stats['info'] ?? 0,
            'debug' => $stats['debug'] ?? 0,
            'total' => array_sum($stats)
        ];
    }

    protected function createDateTime(string $date, ?string $time): string
    {
        if ($time) {
            return "{$date} {$time}";
        }
        return $date;
    }

    /**
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
//    public function getPaginatedWithFilters(array $filters, int $perPage = 50): LengthAwarePaginator
//    {
//        $query = $this->query();
//
//        if (!empty($filters['level'])) {
//            $query->byLevel($filters['level']);
//        }
//
//        if (!empty($filters['source'])) {
//            $query->bySource($filters['source']);
//        }
//
//        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
//            $query->byDateRange(
//                $filters['date_from'] ?? now()->subDays(7)->toDateString(),
//                $filters['date_to'] ?? null
//            );
//        }
//
//        if (!empty($filters['time_from']) || !empty($filters['time_to'])) {
//            $query->where(function (Builder $q) use ($filters) {
//                if (!empty($filters['time_from'])) {
//                    $q->whereTime('created_at', '>=', $filters['time_from']);
//                }
//                if (!empty($filters['time_to'])) {
//                    $q->whereTime('created_at', '<=', $filters['time_to']);
//                }
//            });
//        }
//
//        if (!empty($filters['search'])) {
//            $searchTerm = $filters['search'];
//            $query->where(function (Builder $q) use ($searchTerm) {
//                $q->where('message', 'like', '%' . $searchTerm . '%')
//                    ->orWhere('context', 'like', '%' . $searchTerm . '%');
//            });
//        }
//
//        return $query->orderBy('created_at', 'desc')
//            ->paginate($perPage)
//            ->withQueryString();
//    }

    /**
     * @return SupportCollection
     */
    public function getUniqueSources(): SupportCollection
    {
        return $this->query()
            ->distinct()
            ->orderBy('source')
            ->pluck('source');
    }

    /**
     * Clean logs older than specified days
     * @param int $days
     * @return int Number of deleted records
     */
    public function cleanOldLogs(int $days): int
    {
        return $this->query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
