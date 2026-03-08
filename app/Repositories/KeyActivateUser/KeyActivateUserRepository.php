<?php

namespace App\Repositories\KeyActivateUser;

use App\Models\KeyActivateUser\KeyActivateUser;
use App\Repositories\BaseRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class KeyActivateUserRepository extends BaseRepository
{
    protected function getModelClass(): string
    {
        return KeyActivateUser::class;
    }

    /**
     * Find KeyActivateUser by key_activate_id with relations
     * @param string $keyActivateId
     * @return KeyActivateUser|null
     */
    public function findByKeyActivateIdWithRelations(string $keyActivateId): ?KeyActivateUser
    {
        /** @var KeyActivateUser|null */
        return $this->query()
            ->where('key_activate_id', $keyActivateId)
            ->with([
                'serverUser',
                'keyActivate',
                'keyActivate.packSalesman',
                'keyActivate.packSalesman.salesman'
            ])
            ->first();
    }

    /**
     * Все слоты ключа (для мульти-провайдерной подписки).
     *
     * @return Collection<int, KeyActivateUser>
     */
    public function findAllByKeyActivateId(string $keyActivateId): Collection
    {
        return $this->query()
            ->where('key_activate_id', $keyActivateId)
            ->with([
                'serverUser',
                'serverUser.panel:id,server_id,panel,panel_adress,auth_token,panel_login,panel_password,token_died_time',
                'serverUser.panel.server:id,name,location_id,provider',
                'serverUser.panel.server.location:id,code,emoji',
                'keyActivate',
                'keyActivate.packSalesman',
                'keyActivate.packSalesman.salesman'
            ])
            ->orderBy('id')
            ->get();
    }

    /**
     * Слоты ключа только для отдачи подписки (plain text): минимум связей, без auth_token и ключа/продавца.
     * Быстрее чем findAllByKeyActivateId при запросе из VPN-приложения.
     *
     * @return Collection<int, KeyActivateUser>
     */
    public function findAllByKeyActivateIdForSubscription(string $keyActivateId): Collection
    {
        return $this->query()
            ->where('key_activate_id', $keyActivateId)
            ->with([
                'serverUser:id,keys,panel_id,updated_at',
                'serverUser.panel:id,server_id',
                'serverUser.panel.server:id,name,location_id',
                'serverUser.panel.server.location:id,code,emoji',
            ])
            ->orderBy('id')
            ->get();
    }
}
