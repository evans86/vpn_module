<?php

namespace App\Repositories\PackSalesman;

use App\Models\PackSalesman\PackSalesman;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class PackSalesmanRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return PackSalesman::class;
    }

    /**
     * Get paginated pack-salesman relations with relations
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithRelations(int $perPage = 15): LengthAwarePaginator
    {
        return $this->query()
            ->with(['pack', 'salesman'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * @param int $id
     * @return PackSalesman
     */
    public function findByIdOrFail(int $id): PackSalesman
    {
        /** @var PackSalesman */
        return $this->query()
            ->where('id', $id)
            ->firstOrFail();
    }

    /**
     * Find paid pack by salesman ID
     * @param int $salesmanId
     * @return PackSalesman|null
     */
    public function findPaidBySalesmanId(int $salesmanId): ?PackSalesman
    {
        /** @var PackSalesman|null $result */
        $result = $this->query()
            ->where('salesman_id', $salesmanId)
            ->where('paid', true)
            ->first();

        return $result;
    }

    /**
     * Find pack by salesman ID
     * @param int $salesmanId
     * @return PackSalesman|null
     */
    public function findBySalesmanId(int $salesmanId): ?PackSalesman
    {
        /** @var PackSalesman|null $result */
        $result = $this->query()
            ->where('salesman_id', $salesmanId)
            ->first();

        return $result;
    }

    /**
     * Find pack by salesman ID and pack ID
     * @param int $salesmanId
     * @param int $packId
     * @return PackSalesman|null
     */
    public function findBySalesmanIdAndPackId(int $salesmanId, int $packId): ?PackSalesman
    {
        /** @var PackSalesman|null $result */
        $result = $this->query()
            ->where('salesman_id', $salesmanId)
            ->where('pack_id', $packId)
            ->first();

        return $result;
    }

    /**
     * Increment count of sold keys
     * @param int $id
     * @return PackSalesman
     */
    public function incrementSoldKeys(int $id): PackSalesman
    {
        $packSalesman = $this->findByIdOrFail($id);
        $packSalesman->sold_keys++;
        $packSalesman->save();
        return $packSalesman;
    }

    /**
     * Find pack by ID with relations
     * @param int $id
     * @return PackSalesman|null
     */
    public function findByIdWithRelations(int $id): ?PackSalesman
    {
        /** @var PackSalesman|null $result */
        $result = $this->query()
            ->with(['pack', 'salesman'])
            ->find($id);
        return $result;
    }

    /**
     * Find pack by ID with relations or fail
     * @param int $id
     * @return PackSalesman
     * @throws ModelNotFoundException
     */
    public function findByIdWithRelationsOrFail(int $id): PackSalesman
    {
        /** @var PackSalesman $result */
        $result = $this->query()
            ->with(['pack', 'salesman'])
            ->findOrFail($id);
        return $result;
    }

    /**
     * Update pack status
     * @param PackSalesman $packSalesman
     * @param string $status
     * @return PackSalesman
     */
    public function updateStatus(PackSalesman $packSalesman, string $status): PackSalesman
    {
        $packSalesman->status = $status;
        $packSalesman->save();
        return $packSalesman;
    }
}
