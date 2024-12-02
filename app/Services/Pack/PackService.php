<?php

namespace App\Services\Pack;

use App\Dto\Pack\PackDto;
use App\Dto\Pack\PackFactory;
use App\Repositories\Pack\PackRepositoryInterface;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;

class PackService
{
    /**
     * @var PackRepositoryInterface
     */
    private PackRepositoryInterface $packRepository;

    /**
     * @param PackRepositoryInterface $packRepository
     */
    public function __construct(PackRepositoryInterface $packRepository)
    {
        $this->packRepository = $packRepository;
    }

    /**
     * Get all packs with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return $this->packRepository->getAllPaginated($perPage);
    }

    /**
     * Create a new pack
     *
     * @param array $data
     * @return PackDto
     * @throws Exception
     */
    public function create(array $data): PackDto
    {
        try {
            $pack = $this->packRepository->create($data);
            return PackFactory::fromEntity($pack);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to create pack: {$e->getMessage()}");
        }
    }

    /**
     * Update pack
     *
     * @param int $id
     * @param array $data
     * @return PackDto
     * @throws Exception
     */
    public function update(int $id, array $data): PackDto
    {
        try {
            $pack = $this->packRepository->findById($id);
            if (!$pack) {
                throw new RuntimeException("Pack not found");
            }

            $pack = $this->packRepository->update($pack, $data);
            return PackFactory::fromEntity($pack);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to update pack: {$e->getMessage()}");
        }
    }

    /**
     * Delete pack
     *
     * @param int $id
     * @return bool
     * @throws Exception
     */
    public function delete(int $id): bool
    {
        try {
            $pack = $this->packRepository->findById($id);
            if (!$pack) {
                throw new RuntimeException("Pack not found");
            }

            return $this->packRepository->delete($pack);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to delete pack: {$e->getMessage()}");
        }
    }
}
