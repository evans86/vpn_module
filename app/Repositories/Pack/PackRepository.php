<?php

namespace App\Repositories\Pack;

use App\Models\Pack\Pack;
use Illuminate\Pagination\LengthAwarePaginator;

class PackRepository implements PackRepositoryInterface
{
    public function getAllPaginated(int $perPage = 10): LengthAwarePaginator
    {
        return Pack::orderBy('id', 'desc')->paginate($perPage);
    }

    public function findById(int $id): ?Pack
    {
        return Pack::find($id);
    }

    public function create(array $data): Pack
    {
        return Pack::create($data);
    }

    public function update(Pack $pack, array $data): Pack
    {
        $pack->update($data);
        return $pack;
    }

    public function delete(Pack $pack): bool
    {
        return $pack->delete();
    }
}
