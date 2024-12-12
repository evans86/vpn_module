<?php

namespace App\Repositories\Location;

use App\Models\Location\Location;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class LocationRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return Location::class;
    }

    /**
     * @param string $code
     * @return Location|null
     */
    public function findByCode(string $code): ?Location
    {
        /** @var Location|null */
        return $this->query()
            ->where('code', $code)
            ->first();
    }

    /**
     * @param int $id
     * @return Location
     */
    public function findByIdOrFail(int $id)
    {
        /** @var Location */
        return $this->query()
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * @param string $code
     * @return Location
     * @throws ModelNotFoundException
     */
    public function findByCodeOrFail(string $code): Location
    {
        /** @var Location */
        return $this->query()
            ->where('code', $code)
            ->firstOrFail();
    }
}
