<?php

namespace App\Repositories\User;

use App\Models\User;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class UserRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return User::class;
    }

    /**
     * @param string $email
     * @return User|null
     */
    public function findByEmail(string $email): ?User
    {
        /** @var User|null */
        return $this->query()
            ->where('email', $email)
            ->first();
    }

    /**
     * @param string $email
     * @return User
     * @throws ModelNotFoundException
     */
    public function findByEmailOrFail(string $email): User
    {
        /** @var User */
        return $this->query()
            ->where('email', $email)
            ->firstOrFail();
    }
}
