<?php

namespace App\Repositories\KeyActivateUser;

use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\ServerUser\ServerUser;
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
     * Загрузка в два шага: сначала только строки key_activate_user (лёгкий SELECT по индексу key_activate_id),
     * затем один батч server_user + panel/server/location. Так меньше round-trip и проще план, чем цепочка
     * eager на родительской модели (при проблемах с сетью до MySQL это заметно).
     *
     * @return Collection<int, KeyActivateUser>
     */
    public function findAllByKeyActivateIdForSubscription(string $keyActivateId): Collection
    {
        /** @var Collection<int, KeyActivateUser> $kaus */
        $kaus = $this->query()
            ->where('key_activate_id', $keyActivateId)
            ->select(['id', 'server_user_id', 'key_activate_id', 'location_id', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->get();

        if ($kaus->isEmpty()) {
            return $kaus;
        }

        $serverUserIds = $kaus->pluck('server_user_id')->unique()->filter()->values()->all();
        if ($serverUserIds === []) {
            return $kaus;
        }

        /** @var \Illuminate\Support\Collection<string, ServerUser> $serverUsers */
        $serverUsers = ServerUser::query()
            ->whereIn('id', $serverUserIds)
            ->with([
                'panel:id,server_id',
                'panel.server:id,name,location_id',
                'panel.server.location:id,code,emoji',
            ])
            ->get()
            ->keyBy('id');

        foreach ($kaus as $kau) {
            $sid = $kau->server_user_id;
            if ($sid && $serverUsers->has($sid)) {
                $kau->setRelation('serverUser', $serverUsers->get($sid));
            }
        }

        return $kaus;
    }
}
