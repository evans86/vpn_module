<?php

namespace App\Services\Key;

use App\Dto\KeyActivate\KeyActivateDto;
use App\Dto\KeyActivate\KeyActivateFactory;
use App\Models\KeyActivate\KeyActivate;
use App\Models\Location\Location;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Panel\Panel;
use App\Services\Panel\PanelStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Str;
use Exception;
use RuntimeException;

class KeyActivateService
{
    /**
     * @param int $traffic_limit лимит трафика на пользователя (сколько осталось)
     * @param int $pack_salesman_id продавец пакета
     * @param int $finish_at дата окончания
     * @param int|null $user_tg_id кто активировал ключ
     * @param int $deleted_at срок, до которого надо активировать ключ
     * @param bool $status активный или законченный
     * @return KeyActivateDto
     * @throws Exception
     */
    public function create(
        int  $traffic_limit,
        int  $pack_salesman_id,
        int  $finish_at,
        ?int $user_tg_id,
        int  $deleted_at,
        bool $status = KeyActivate::ACTIVE
    ): KeyActivateDto
    {
        try {
            /**
             * @var PackSalesman $pack_salesman
             */
            $pack_salesman = PackSalesman::query()->where('id', $pack_salesman_id)->firstOrFail();


            $key_activate = new KeyActivate();
            $key_activate->id = Str::uuid();
            $key_activate->traffic_limit = $traffic_limit;
            $key_activate->pack_salesman_id = $pack_salesman->id;
            $key_activate->finish_at = $finish_at;
            $key_activate->user_tg_id = $user_tg_id;
            $key_activate->deleted_at = $deleted_at;
            $key_activate->status = $status;

            if (!$key_activate->save())
                throw new \RuntimeException('Key Activate dont create');

            return KeyActivateFactory::fromEntity($key_activate);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * @param string $key_activate_id
     * @param int $user_tg_id
     * @return void
     * @throws GuzzleException
     * @throws Exception
     */
    public function activation(string $key_activate_id, int $user_tg_id)
    {
        try {
            /**
             * @var KeyActivate $key_activate
             */
            $key_activate = KeyActivate::query()->where('id', $key_activate_id)->firstOrFail();
            $key_activate->user_tg_id = $user_tg_id;
            if (!$key_activate->save())
                throw new \RuntimeException('Key Activate dont activation');

            $key_activate_user_service = new KeyActivateUserService();

            //надо как-то передать panel_id
            $strategy = new PanelStrategy(Panel::MARZBAN);
            $server_user = $strategy->addServerUser(16, $key_activate->traffic_limit, $key_activate->finish_at);

            $key_activate_user_service->create($server_user->id, $key_activate->id, Location::NL);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }

    public function checkDuration()
    {
        /**
         * @var KeyActivate[] $key_activates
         */
        $key_activates = KeyActivate::query()
            ->where('status', KeyActivate::ACTIVE)->get();

        foreach ($key_activates as $key_activate) {
            $key_activate->status = KeyActivate::EXPIRED;
            if (!$key_activate->save())
                throw new \RuntimeException('Key Activate dont change status');
        }
    }

    public function updateTraffic()
    {
        //обновление трафика ключа
    }

    public function changeServer()
    {
        //перенос ключа на другой сервер
    }
}
