<?php

namespace App\Repositories\Pack;

use App\Models\Pack\Pack;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PackRepository extends BaseRepository implements PackRepositoryInterface
{
    protected function getModelClass(): string
    {
        return Pack::class;
    }

    /**
     * Get all packs with pagination and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->query();

        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get all active packs
     * @return Collection
     */
    public function getAllActive(): Collection
    {
        return $this->query()
            ->where('status', Pack::ACTIVE)
            ->orderBy('id', 'desc')
            ->get();
    }

    /**
     * Find pack by ID
     * @param int $id
     * @return Pack|null
     */
    public function findById(int $id): ?Pack
    {
        /** @var Pack|null $result */
        $result = $this->query()->find($id);
        return $result;
    }

    /**
     * Find pack by ID or fail
     * @param int $id
     * @return Pack
     * @throws ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Pack
    {
        /** @var Pack $result */
        $result = $this->query()->findOrFail($id);
        return $result;
    }

    /**
     * Find active pack by ID
     * @param int $id
     * @return Pack|null
     */
    public function findActiveById(int $id): ?Pack
    {
        /** @var Pack|null $result */
        $result = $this->query()
            ->where('id', $id)
            ->where('status', Pack::ACTIVE)
            ->first();
        return $result;
    }

    /**
     * Create new pack
     * @param array $data
     * @return Pack
     */
    public function create(array $data): Pack
    {
        /** @var Pack $result */
        $result = parent::create($data);
        return $result;
    }

    /**
     * Update pack
     * @param Pack $pack
     * @param array $data
     * @return Pack
     */
    public function updatePack(Pack $pack, array $data): Pack
    {
        $pack->fill($data);
        $pack->save();
        return $pack;
    }

    /**
     * Update pack status
     * @param Pack $pack
     * @param bool $status
     * @return Pack
     */
    public function updateStatus(Pack $pack, bool $status): Pack
    {
        $pack->status = $status;
        $pack->save();
        return $pack;
    }

    /**
     * Delete pack
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool
    {
        return parent::delete($model);
    }

    /**
     * Check if pack exists
     * @param int $id
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->query()->where('id', $id)->exists();
    }

    /**
     * Check if pack is active
     * @param Pack $pack
     * @return bool
     */
    public function isActive(Pack $pack): bool
    {
        return $pack->status === Pack::ACTIVE;
    }
}
