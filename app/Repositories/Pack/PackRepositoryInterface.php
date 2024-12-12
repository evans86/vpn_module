<?php

namespace App\Repositories\Pack;

use App\Models\Pack\Pack;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;

interface PackRepositoryInterface
{
    /**
     * Get all packs with pagination
     * 
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator;

    /**
     * Find pack by ID
     * 
     * @param int $id
     * @return Pack|null
     */
    public function findById(int $id): ?Pack;

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
     * @return bool
     */
    public function delete(Model $model): bool;
}
