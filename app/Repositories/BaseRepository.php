<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;

abstract class BaseRepository
{
    /** @var Model */
    protected Model $model;

    public function __construct()
    {
        $this->model = app($this->getModelClass());
    }

    abstract protected function getModelClass(): string;

    protected function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * @template TModel of Model
     * @param int $id
     * @return TModel|null
     */
    public function find(int $id): ?Model
    {
        return $this->query()->find($id);
    }

    /**
     * @template TModel of Model
     * @param int $id
     * @return TModel
     * @throws ModelNotFoundException
     */
    public function findOrFail(int $id): Model
    {
        return $this->query()->findOrFail($id);
    }

    /**
     * @template TModel of Model
     * @return Collection<TModel>
     */
    public function all(): Collection
    {
        return $this->query()->get();
    }

    /**
     * @template TModel of Model
     * @param array $data
     * @return TModel
     */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /**
     * @param Model $model
     * @param array $data
     * @return bool
     */
    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    /**
     * @param Model $model
     * @return bool
     */
    public function delete(Model $model): bool
    {
        return $model->delete();
    }
}
