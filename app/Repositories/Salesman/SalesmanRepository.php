<?php

namespace App\Repositories\Salesman;

use App\Models\Salesman\Salesman;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;

class SalesmanRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return Salesman::class;
    }

    /**
     * Get all salesmen
     * @return Collection
     */
    public function getAll(): Collection
    {
        return $this->query()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get all active salesmen
     * @return Collection
     */
    public function getAllActive(): Collection
    {
        return $this->query()
            ->where('status', true)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get paginated salesmen list with filters
     * @param int $perPage
     * @param array $filters
     * @return LengthAwarePaginator
     */
    public function getPaginated(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = $this->query();

        if (isset($filters['id'])) {
            $query->where('id', $filters['id']);
        }

        if (isset($filters['telegram_id'])) {
            $query->where('telegram_id', $filters['telegram_id']);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Find salesman by token
     * @param string $token
     * @return Salesman|null
     */
    public function findByToken(string $token): ?Salesman
    {
        /** @var Salesman|null $result */
        $result = $this->query()
            ->where('token', $token)
            ->first();
        return $result;
    }

    /**
     * Find salesman by telegram ID
     * @param int $telegramId
     * @return Salesman|null
     */
    public function findByTelegramId(int $telegramId): ?Salesman
    {
        /** @var Salesman|null $result */
        $result = $this->query()
            ->where('telegram_id', $telegramId)
            ->first();
        return $result;
    }

    /**
     * Find salesman by ID or fail
     * @param int $id
     * @return Salesman
     * @throws ModelNotFoundException
     */
    public function findByIdOrFail(int $id): Salesman
    {
        /** @var Salesman $result */
        $result = $this->query()->findOrFail($id);
        return $result;
    }

    /**
     * Find salesman by ID
     * @param int $id
     * @return Salesman|null
     */
    public function findById(int $id): ?Salesman
    {
        /** @var Salesman|null $result */
        $result = $this->query()->find($id);
        return $result;
    }

    /**
     * Create new salesman
     * @param array $data
     * @return Salesman
     */
    public function create(array $data): Salesman
    {
        /** @var Salesman $result */
        $result = parent::create($data);
        return $result;
    }

//    /**
//     * Update salesman
//     * @param Salesman $salesman
//     * @param array $data
//     * @return bool
//     */
//    public function update(Salesman $salesman, array $data): bool
//    {
//        $salesman->fill($data);
//        $salesman->save();
//        return $salesman;
//    }

    /**
     * Update salesman status
     * @param Salesman $salesman
     * @param bool $status
     * @return Salesman
     */
    public function updateStatus(Salesman $salesman, bool $status): Salesman
    {
        $salesman->status = $status;
        $salesman->save();
        return $salesman;
    }

    /**
     * Update salesman token
     * @param Salesman $salesman
     * @param string $token
     * @return Salesman
     */
    public function updateToken(Salesman $salesman, string $token): Salesman
    {
        $salesman->token = $token;
        $salesman->save();
        return $salesman;
    }

//    /**
//     * Delete salesman
//     * @param Salesman $salesman
//     * @return bool
//     */
//    public function delete(Salesman $salesman): bool
//    {
//        return parent::delete($salesman);
//    }
}
