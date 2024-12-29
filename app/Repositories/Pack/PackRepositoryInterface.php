<?php

namespace App\Repositories\Pack;

use App\Models\Pack\Pack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface PackRepositoryInterface
{
    /**
     * Get all packs with pagination and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(array $filters = [], int $perPage = 10): LengthAwarePaginator;

    /**
     * Find pack by id
     *
     * @param int $id
     * @return Pack|null
     */
    public function findById(int $id): ?Pack;

    /**
     * Find pack by id or fail
     *
     * @param int $id
     * @return Pack
     */
    public function findByIdOrFail(int $id): Pack;

    /**
     * Create new pack
     *
     * @param array $data
     * @return Pack
     */
    public function create(array $data): Pack;

    /**
     * Update pack
     *
     * @param Model $model
     * @param array $data
     * @return bool
     */
    public function update(Model $model, array $data): bool;

    /**
     * Delete pack
     *
     * @param Model $model
     * @return bool|null
     */
    public function delete(Model $model): ?bool;
}
