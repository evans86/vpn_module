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
}
