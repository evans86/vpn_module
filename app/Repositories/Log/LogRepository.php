<?php

namespace App\Repositories\Log;

use App\Models\Log\ApplicationLog;
use App\Repositories\BaseRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection as SupportCollection;

class LogRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return ApplicationLog::class;
    }

    /**
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithFilters(array $filters, int $perPage = 50): LengthAwarePaginator
    {
        $query = $this->query();

        if (!empty($filters['level'])) {
            $query->byLevel($filters['level']);
        }

        if (!empty($filters['source'])) {
            $query->bySource($filters['source']);
        }

        if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
            $query->byDateRange(
                $filters['date_from'] ?? now()->subDays(7)->toDateString(),
                $filters['date_to'] ?? null
            );
        }

        if (!empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('message', 'like', '%' . $searchTerm . '%')
                    ->orWhere('context', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->withQueryString();
    }

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
