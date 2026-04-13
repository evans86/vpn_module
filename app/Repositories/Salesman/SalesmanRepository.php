<?php

namespace App\Repositories\Salesman;

use App\Models\Salesman\Salesman;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\Builder;
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
     *
     * @param  array<string, mixed>  $filters  ключ «q»: поиск по никнейму, email, bot_link, public_key связанного bot_module, при строке из цифр — id и telegram_id
     */
    public function getPaginated(int $perPage = 20, array $filters = []): LengthAwarePaginator
    {
        $query = $this->query();

        if (! empty($filters['q']) && is_string($filters['q'])) {
            $this->applyQuickSearch($query, $filters['q']);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Экранирование % и _ для SQL LIKE.
     */
    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }

    /**
     * Общий поиск: никнейм, email, ссылка на бота; чисто числовая строка — ещё id и telegram_id.
     */
    private function applyQuickSearch(Builder $query, string $raw): void
    {
        $term = trim($raw);
        if ($term === '') {
            return;
        }

        $escaped = $this->escapeLike($term);
        $like = '%'.$escaped.'%';
        $termNoAt = ltrim($term, '@');
        $escapedNoAt = $this->escapeLike($termNoAt);
        $likeNoAt = '%'.$escapedNoAt.'%';

        $query->where(function (Builder $q) use ($like, $likeNoAt, $term) {
            $q->where('username', 'like', $like);
            if ($like !== $likeNoAt) {
                $q->orWhere('username', 'like', $likeNoAt);
            }
            $q->orWhere('email', 'like', $like)
                ->orWhere('bot_link', 'like', $like);

            if (preg_match('/^\d{1,20}$/', $term)) {
                $q->orWhere('id', (int) $term)
                    ->orWhere('telegram_id', $term);
            }

            $q->orWhereHas('botModule', function (Builder $bm) use ($like, $term) {
                $bm->where('public_key', 'like', $like)
                    ->orWhere('public_key', $term);
            });
        });
    }

    /**
     * Find salesman by token (учитывает старые записи с зашифрованным токеном)
     * @param string $token
     * @return Salesman|null
     */
    public function findByToken(string $token): ?Salesman
    {
        return Salesman::findByToken($token);
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
}
