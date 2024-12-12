<?php

namespace App\Repositories\ServerUser;

use App\Models\ServerUser\ServerUser;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class ServerUserRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return ServerUser::class;
    }

    /**
     * @param int $userId
     * @param int $serverId
     * @return ServerUser|null
     */
    public function findByUserAndServer(int $userId, int $serverId): ?ServerUser
    {
        /** @var ServerUser|null */
        return $this->query()
            ->where('user_id', $userId)
            ->where('server_id', $serverId)
            ->first();
    }

    /**
     * @param int $userId
     * @param int $serverId
     * @return ServerUser
     * @throws ModelNotFoundException
     */
    public function findByUserAndServerOrFail(int $userId, int $serverId): ServerUser
    {
        /** @var ServerUser */
        return $this->query()
            ->where('user_id', $userId)
            ->where('server_id', $serverId)
            ->firstOrFail();
    }
}
