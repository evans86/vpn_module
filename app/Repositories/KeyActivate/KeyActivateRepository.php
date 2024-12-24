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
     * Get paginated key activates with pack relations and filters
     *
     * @param array $filters
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getPaginatedWithPack(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = $this->query()
            ->with(['packSalesman.pack', 'packSalesman.salesman'])
            ->orderBy('created_at', 'desc');

        if (!empty($filters['id'])) {
            $query->where('id', 'like', '%' . $filters['id'] . '%');
        }

        if (!empty($filters['pack_id'])) {
            $query->whereHas('packSalesman.pack', function($q) use ($filters) {
                $q->where('id', $filters['pack_id']);
            });
        }

        if (!empty($filters['telegram_id'])) {
            $query->whereHas('packSalesman', function($q) use ($filters) {
                $q->whereHas('salesman', function($sq) use ($filters) {
                    $sq->where('telegram_id', $filters['telegram_id']);
                });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['user_tg_id'])) {
            $query->where('user_tg_id', $filters['user_tg_id']);
        }

        \Log::info('KeyActivate Query', [
            'filters' => $filters,
            'sql' => $query->toSql(),
            'bindings' => $query->getBindings()
        ]);

        return $query->paginate($perPage);
    }

    /**
     * Find active key by user and salesman
     * @param int $userTgId
     * @param int $salesmanId
     * @return KeyActivate|null
     */
    public function findActiveKeyByUserAndSalesman(int $userTgId, int $salesmanId, int $status = KeyActivate::PAID): ?KeyActivate
    {
        /** @var KeyActivate|null $result */
        $result = $this->query()
            ->whereHas('packSalesman', function ($query) use ($salesmanId) {
                $query->where('salesman_id', $salesmanId);
            })
            ->where('user_tg_id', $userTgId)
            ->where('status', $status)
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
     * @param string $id
     * @return KeyActivate|null
     */
    public function findById(string $id): ?KeyActivate
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
        return $key->status == KeyActivate::PAID;
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
        // Получаем период действия из связанного пакета
        $pack = $key->packSalesman->pack;

        // Рассчитываем timestamp окончания: текущее время + период в днях (в секундах)
        $finishAt = time() + ($pack->period * 24 * 60 * 60);

        $key->user_tg_id = $userTgId;
        $key->status = $status;
        $key->finish_at = $finishAt;
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
