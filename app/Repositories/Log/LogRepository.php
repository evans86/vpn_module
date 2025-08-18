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

    public function getPaginatedWithFilters(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->query()
            ->select([
                'id',
                'created_at',
                'level',
                'source',
                DB::raw('CASE WHEN LENGTH(message) > 60 THEN CONCAT(SUBSTRING(message, 1, 60), "...") ELSE message END as message_short'),
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

        // Фильтр по дате
        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $start = $filters['date_from'] ?? now()->subDays(7)->toDateString();
            $end = $filters['date_to'] ?? null;

            if ($end) {
                $query->whereBetween('created_at', [
                    $this->createDateTime($start, $filters['time_from'] ?? null),
                    $this->createDateTime($end, $filters['time_to'] ?? '23:59:59')
                ]);
            } else {
                $query->whereDate('created_at', $start);
            }
        }

        // Фильтр по времени (если не указана дата)
        if ((!empty($filters['time_from']) || !empty($filters['time_to'])) && empty($filters['date_from']) && empty($filters['date_to'])) {
            if (!empty($filters['time_from'])) {
                $query->whereTime('created_at', '>=', $filters['time_from']);
            }
            if (!empty($filters['time_to'])) {
                $query->whereTime('created_at', '<=', $filters['time_to']);
            }
        }

        // Поиск
        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            if (strlen($searchTerm) > 3) {
                $query->searchMessage($searchTerm);
            } else {
                $query->where('message', 'like', '%' . $searchTerm . '%');
            }
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->appends($filters);
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
