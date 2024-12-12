<?php

namespace App\Repositories\KeyActivate;

use App\Models\KeyActivate\KeyActivate;
use App\Repositories\BaseRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class KeyActivateRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return KeyActivate::class;
    }

    /**
     * Get paginated key activates with pack relations
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithPack(int $perPage = 10): LengthAwarePaginator
    {
        return $this->query()
            ->with(['packSalesman.pack'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find active key by user and salesman
     * @param int $userTgId
     * @param int $salesmanId
     * @return KeyActivate|null
     */
    public function findActiveKeyByUserAndSalesman(int $userTgId, int $salesmanId): ?KeyActivate
    {
        /** @var KeyActivate|null $result */
        $result = $this->query()
            ->whereHas('packSalesman', function ($query) use ($salesmanId) {
                $query->where('salesman_id', $salesmanId);
            })
            ->where('user_tg_id', $userTgId)
            ->where('status', KeyActivate::ACTIVE)
            ->where('finish_at', '>', Carbon::now()->timestamp)
            ->orderBy('finish_at', 'desc')
            ->first();
        return $result;
    }

    /**
     * Find available key for activation
     * @param int $keyId
     * @param int $salesmanId
     * @return KeyActivate|null
     */
    public function findAvailableKeyForActivation(int $keyId, int $salesmanId): ?KeyActivate
    {
        /** @var KeyActivate|null $result */
        $result = $this->query()
            ->whereHas('packSalesman', function ($query) use ($salesmanId) {
                $query->where('salesman_id', $salesmanId);
            })
            ->where('id', $keyId)
            ->where('status', KeyActivate::PAID)
            ->where(function ($query) {
                $query->whereNull('user_tg_id')
                    ->orWhere('user_tg_id', 0);
            })
            ->first();
        return $result;
    }

    /**
     * Find key by ID
     * @param int $id
     * @return KeyActivate|null
     */
    public function findById(int $id): ?KeyActivate
    {
        /** @var KeyActivate|null $result */
        $result = $this->query()->find($id);
        return $result;
    }

    /**
     * Find key by ID with relations
     * @param int $id
     * @return KeyActivate|null
     */
    public function findByIdWithRelations(int $id): ?KeyActivate
    {
        /** @var KeyActivate|null $result */
        $result = $this->query()
            ->with(['packSalesman.pack'])
            ->find($id);
        return $result;
    }

    /**
     * Find key by ID with relations or fail
     * @param int $id
     * @return KeyActivate
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findByIdWithRelationsOrFail(int $id): KeyActivate
    {
        /** @var KeyActivate $result */
        $result = $this->query()
            ->with(['packSalesman.pack'])
            ->findOrFail($id);
        return $result;
    }

    /**
     * Delete key activate
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    /**
     * Check if key has correct status for activation
     * @param KeyActivate $key
     * @return bool
     */
    public function hasCorrectStatusForActivation(KeyActivate $key): bool
    {
        return $key->status === KeyActivate::PAID;
    }

    /**
     * Update key status
     * @param KeyActivate $key
     * @param string $status
     * @return KeyActivate
     */
    public function updateStatus(KeyActivate $key, string $status): KeyActivate
    {
        $key->status = $status;
        $key->save();
        return $key;
    }

    /**
     * Update key activation data
     * @param KeyActivate $key
     * @param int $userTgId
     * @param string $status
     * @return KeyActivate
     */
    public function updateActivationData(KeyActivate $key, int $userTgId, string $status): KeyActivate
    {
        $key->user_tg_id = $userTgId;
        $key->status = $status;
        $key->activated_at = Carbon::now();
        $key->save();
        return $key;
    }

    /**
     * Create new key
     * @param array $data
     * @return KeyActivate
     */
    public function createKey(array $data): KeyActivate
    {
        /** @var KeyActivate */
        return $this->create($data);
    }

    /**
     * Check if key is expired by time
     * @param KeyActivate $key
     * @return bool
     */
    public function isExpiredByTime(KeyActivate $key): bool
    {
        $currentTime = Carbon::now()->timestamp;
        return $currentTime > $key->finish_at;
    }

    /**
     * Check if key activation period is expired
     * @param KeyActivate $key
     * @return bool
     */
    public function isActivationPeriodExpired(KeyActivate $key): bool
    {
        if (!$key->deleted_at) {
            return false;
        }
        $currentTime = Carbon::now()->timestamp;
        return $currentTime > $key->deleted_at;
    }

    /**
     * Check if key is used by another user
     * @param KeyActivate $key
     * @param int $userTgId
     * @return bool
     */
    public function isUsedByAnotherUser(KeyActivate $key, int $userTgId): bool
    {
        return $key->user_tg_id && $key->user_tg_id !== $userTgId;
    }
}
