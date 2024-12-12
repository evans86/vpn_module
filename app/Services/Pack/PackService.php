<?php

namespace App\Services\Pack;

use App\Dto\Pack\PackDto;
use App\Dto\Pack\PackFactory;
use App\Repositories\Pack\PackRepositoryInterface;
use App\Logging\DatabaseLogger;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;
use RuntimeException;
use Illuminate\Support\Collection;

class PackService
{
    private PackRepositoryInterface $packRepository;
    private DatabaseLogger $logger;

    public function __construct(
        PackRepositoryInterface $packRepository,
        DatabaseLogger        $logger
    ) {
        $this->packRepository = $packRepository;
        $this->logger = $logger;
    }

    /**
     * Get all packs with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        try {
            $this->logger->info('Getting paginated packs', [
                'source' => 'pack',
                'action' => 'get_paginated',
                'per_page' => $perPage
            ]);

            return $this->packRepository->getAllPaginated($perPage);
        } catch (Exception $e) {
            $this->logger->error('Failed to get paginated packs', [
                'source' => 'pack',
                'action' => 'get_paginated',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Failed to get packs: {$e->getMessage()}");
        }
    }

    /**
     * Get all active packs
     *
     * @return Collection
     */
    public function getAllActive(): Collection
    {
        try {
            $this->logger->info('Getting all active packs', [
                'source' => 'pack',
                'action' => 'get_active'
            ]);

            return $this->packRepository->getAllActive();
        } catch (Exception $e) {
            $this->logger->error('Failed to get active packs', [
                'source' => 'pack',
                'action' => 'get_active',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Failed to get active packs: {$e->getMessage()}");
        }
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
            $this->logger->info('Creating new pack', [
                'source' => 'pack',
                'action' => 'create',
                'data' => $data
            ]);

            $pack = $this->packRepository->create($data);
            return PackFactory::fromEntity($pack);
        } catch (Exception $e) {
            $this->logger->error('Failed to create pack', [
                'source' => 'pack',
                'action' => 'create',
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

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
            $this->logger->info('Updating pack', [
                'source' => 'pack',
                'action' => 'update',
                'pack_id' => $id,
                'data' => $data
            ]);

            $pack = $this->packRepository->findByIdOrFail($id);
            $pack = $this->packRepository->updatePack($pack, $data);

            $this->logger->info('Pack updated successfully', [
                'source' => 'pack',
                'action' => 'update',
                'pack_id' => $pack->id
            ]);

            return PackFactory::fromEntity($pack);
        } catch (Exception $e) {
            $this->logger->error('Failed to update pack', [
                'source' => 'pack',
                'action' => 'update',
                'pack_id' => $id,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Failed to update pack: {$e->getMessage()}");
        }
    }

    /**
     * Toggle pack status
     *
     * @param int $id
     * @return PackDto
     * @throws Exception
     */
    public function toggleStatus(int $id): PackDto
    {
        try {
            $this->logger->info('Toggling pack status', [
                'source' => 'pack',
                'action' => 'toggle_status',
                'pack_id' => $id
            ]);

            $pack = $this->packRepository->findByIdOrFail($id);
            $pack = $this->packRepository->updateStatus($pack, !$pack->status);

            $this->logger->info('Pack status toggled successfully', [
                'source' => 'pack',
                'action' => 'toggle_status',
                'pack_id' => $pack->id,
                'new_status' => $pack->status
            ]);

            return PackFactory::fromEntity($pack);
        } catch (Exception $e) {
            $this->logger->error('Failed to toggle pack status', [
                'source' => 'pack',
                'action' => 'toggle_status',
                'pack_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Failed to toggle pack status: {$e->getMessage()}");
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
            $this->logger->info('Deleting pack', [
                'source' => 'pack',
                'action' => 'delete',
                'pack_id' => $id
            ]);

            $pack = $this->packRepository->findByIdOrFail($id);
            $result = $this->packRepository->delete($pack);

            if ($result) {
                $this->logger->info('Pack deleted successfully', [
                    'source' => 'pack',
                    'action' => 'delete',
                    'pack_id' => $id
                ]);
            } else {
                $this->logger->warning('Failed to delete pack', [
                    'source' => 'pack',
                    'action' => 'delete',
                    'pack_id' => $id
                ]);
            }

            return $result;
        } catch (Exception $e) {
            $this->logger->error('Error deleting pack', [
                'source' => 'pack',
                'action' => 'delete',
                'pack_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw new RuntimeException("Failed to delete pack: {$e->getMessage()}");
        }
    }
}
