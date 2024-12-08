<?php

namespace App\Services\Key;

use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Panel\Panel;
use App\Services\Panel\PanelStrategy;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Exception;

class KeyActivateService
{
    private $panelStrategy;

    /**
     * Создание ключа
     *
     * @param int|null $traffic_limit
     * @param int $pack_salesman_id
     * @param int $finish_at
     * @param int $deleted_at
     * @return KeyActivate
     * @throws Exception
     */
    public function create(?int $traffic_limit, int $pack_salesman_id, int $finish_at, int $deleted_at): KeyActivate
    {
        try {
            /**
             * @var PackSalesman $packSalesman
             */
            $packSalesman = PackSalesman::query()->where('id', $pack_salesman_id)->firstOrFail();

            $keyActivate = new KeyActivate();

            $keyActivate->id = Str::uuid()->toString();
            $keyActivate->traffic_limit = $traffic_limit;
            $keyActivate->pack_salesman_id = $packSalesman->id;
            $keyActivate->finish_at = $finish_at;
            $keyActivate->deleted_at = $deleted_at;
            $keyActivate->status = KeyActivate::PAID;

            if (!$keyActivate->save())
                throw new RuntimeException('Key Activate dont create');

            Log::info('Ключ успешно создан', [
                'source' => 'key_activate',
                'action' => 'activate',
                'key_id' => $keyActivate->id,
                'pack_salesman_id' => $packSalesman->id
            ]);

            return $keyActivate;
        } catch (RuntimeException $r) {
            throw new RuntimeException($r->getMessage());
        } catch (Exception $e) {
            Log::error('Ошибка при активации ключа', [
                'source' => 'key_activate',
                'action' => 'activate',
                'pack_salesman_id' => $packSalesman->id,
                'error' => $e->getMessage()
            ]);
            throw new Exception($e->getMessage());
        }
    }

    /**
     * Активация ключа
     *
     * @param KeyActivate $key
     * @param int $userTgId
     * @return KeyActivate
     * @throws RuntimeException|GuzzleException
     */
    public function activate(KeyActivate $key, int $userTgId): KeyActivate
    {
        try {
            // Проверяем текущий статус
            if ($key->status != KeyActivate::PAID) {
                throw new RuntimeException('Ключ не может быть активирован (неверный статус)');
            }

            // Проверяем, не истек ли срок для активации
            if ($key->deleted_at && time() > $key->deleted_at) {
                throw new RuntimeException('Срок активации ключа истек');
            }

            // Проверяем, не занят ли уже ключ другим пользователем
            if ($key->user_tg_id && $key->user_tg_id !== $userTgId) {
                throw new RuntimeException('Ключ уже используется другим пользователем');
            }

            //проверка пользователей на панели и выбираю с наименьшим количеством пользователей
            //Получаем активную панель Marzban
            $panel = Panel::where('panel_status', Panel::PANEL_CONFIGURED)
                ->where('panel', Panel::MARZBAN)
                ->first();

            if (!$panel) {
                throw new RuntimeException('Активная панель Marzban не найдена');
            }

            // Создаем стратегию для работы с панелью
            $this->panelStrategy = new PanelStrategy(Panel::MARZBAN);

            // Добавляем пользователя на сервер
            $serverUser = $this->panelStrategy->addServerUser(
                $panel->id,
                $key->traffic_limit,
                $key->finish_at,
                $key->id
            );

            // Устанавливаем данные активации
            $key->user_tg_id = $userTgId;
            $key->status = KeyActivate::ACTIVE;
            $key->deleted_at = null;

            if (!$key->save()) {
                throw new RuntimeException('Ошибка при сохранении ключа');
            }

            Log::info('Ключ успешно активирован', [
                'source' => 'key_activate',
                'action' => 'activate',
                'key_id' => $key->id,
                'user_tg_id' => $userTgId,
                'server_user_id' => $serverUser->id
            ]);

            return $key;
        } catch (Exception $e) {
            Log::error('Ошибка при активации ключа', [
                'source' => 'key_activate',
                'action' => 'activate',
                'key_id' => $key->id,
                'user_tg_id' => $userTgId,
                'error' => $e->getMessage()
            ]);

            throw new RuntimeException($e->getMessage());
        }
    }

    /**
     * Проверка и обновление статуса ключа
     *
     * @param KeyActivate $key
     * @return KeyActivate
     */
    public function checkAndUpdateStatus(KeyActivate $key): KeyActivate
    {
        $currentTime = time();

        // Проверяем срок действия для активных ключей
        if ($key->status === KeyActivate::ACTIVE && $currentTime > $key->finish_at) {
            $key->status = KeyActivate::EXPIRED;
            $key->save();

            Log::info('Статус ключа обновлен на EXPIRED (истек срок действия)', [
                'source' => 'key_activate',
                'action' => 'update_status',
                'key_id' => $key->id
            ]);
        }

        // Проверяем срок активации для оплаченных ключей
        if ($key->status === KeyActivate::PAID && $key->deleted_at && $currentTime > $key->deleted_at) {
            $key->status = KeyActivate::EXPIRED;
            $key->save();

            Log::info('Статус ключа обновлен на EXPIRED (истек срок активации)', [
                'source' => 'key_activate',
                'action' => 'update_status',
                'key_id' => $key->id
            ]);
        }

        return $key;
    }
}
