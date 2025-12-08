<?php

namespace App\Repositories\KeyActivateUser;

use App\Models\KeyActivateUser\KeyActivateUser;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class KeyActivateUserRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return KeyActivateUser::class;
    }

    /**
     * Find KeyActivateUser by key_activate_id with relations
     * @param string $keyActivateId
     * @return KeyActivateUser|null
     */
    public function findByKeyActivateIdWithRelations(string $keyActivateId): ?KeyActivateUser
    {
        /** @var KeyActivateUser|null */
        return $this->query()
            ->where('key_activate_id', $keyActivateId)
            ->with(['serverUser', 'keyActivate'])
            ->first();
    }
}
