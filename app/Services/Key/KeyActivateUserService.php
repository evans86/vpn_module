<?php

namespace App\Services\Key;

use App\Dto\KeyActivateUser\KeyActivateUserDto;
use App\Dto\KeyActivateUser\KeyActivateUserFactory;
use App\Models\KeyActivateUser\KeyActivateUser;
use App\Models\Location\Location;
use Exception;
use RuntimeException;

class KeyActivateUserService
{

    /**
     * @param string $server_user_id
     * @param string $key_activate_id
     * @param int $location_id локация ключа
     * @return KeyActivateUserDto
     * @throws Exception
     */
    public function create(string $server_user_id, string $key_activate_id, int $location_id): KeyActivateUserDto
    {
        try {
            /**
             * @var Location $location
             */
            $location = Location::query()->where('id', $location_id)->firstOrFail();

            $key_activate_user = new  KeyActivateUser();

            $key_activate_user->server_user_id = $server_user_id;
            $key_activate_user->key_activate_id = $key_activate_id;
            $key_activate_user->location_id = $location->id;

            if (!$key_activate_user->save())
                throw new \RuntimeException('Key Activate User dont create');

            return KeyActivateUserFactory::fromEntity($key_activate_user);
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
