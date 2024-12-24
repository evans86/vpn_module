<?php

namespace App\Repositories\Server;

use App\Models\Server\Server;
use App\Models\Location\Location;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;

class ServerRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return Server::class;
    }

    /**
     * Get paginated servers with relations
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithRelations(int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()
            ->with('location')
            ->orderBy('id', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get locations for dropdown
     * @return SupportCollection
     */
    public function getLocationsForDropdown(): SupportCollection
    {
        return Location::pluck('code', 'id')->mapWithKeys(function ($code, $id) {
            /** @var Location|null $location */
            $location = Location::find($id);
            return [$id => $code . ' ' . ($location ? $location->emoji : '')];
        });
    }

    /**
     * Find server by IP
     * @param string $ip
     * @return Server|null
     */
    public function findByIp(string $ip): ?Server
    {
        /** @var Server|null */
        return $this->query()
            ->where('ip', $ip)
            ->first();
    }

    /**
     * Update server configuration
     * @param Server $server
     * @param array $data
     * @return Server
     */
    public function updateConfiguration(Server $server, array $data): Server
    {
        $server->fill($data);
        $server->save();
        return $server;
    }

    /**
     * Get filtered servers
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getFilteredServers(array $filters = [], int $perPage = 10)
    {
        $query = $this->query()->with('location');

        // Фильтр по названию (минимум 3 символа)
        if (!empty($filters['name']) && strlen($filters['name']) >= 3) {
            $query->where('name', 'like', "%{$filters['name']}%");
        }

        // Фильтр по IP (минимум 3 символа)
        if (!empty($filters['ip']) && strlen($filters['ip']) >= 3) {
            $query->where('ip', 'like', "%{$filters['ip']}%");
        }

        // Фильтр по хосту (минимум 3 символа)
        if (!empty($filters['host']) && strlen($filters['host']) >= 3) {
            $query->where('host', 'like', "%{$filters['host']}%");
        }

        // Фильтр по статусу
        if (!empty($filters['status'])) {
            $query->where('server_status', $filters['status']);
        }

        return $query->latest()->paginate($perPage);
    }
}
