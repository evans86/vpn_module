<?php

namespace App\Services\Panel;

use App\Models\ServerUser\ServerUser;

interface PanelInterface
{
    /**
     * Создание панели
     *
     * @param int $server_id
     * @return void
     */
    public function create(int $server_id): void;

    /**
     * Обновление конфигурации панели
     *
     * @param int $panel_id
     * @return void
     */
    public function updateConfiguration(int $panel_id): void;

    /**
     * Добавление пользователя панели
     *
     * @param int $panel_id
     * @param int $userTgId
     * @param int $data_limit
     * @param int $expire
     * @param string $key_activate_id
     * @return ServerUser
     */
    public function addServerUser(int $panel_id, int $userTgId, int $data_limit, int $expire, string $key_activate_id): ServerUser;

    /**
     * Проверка использования пользователя
     *
     * @param int $panel_id
     * @param string $user_id
     * @return void
     */
    public function checkOnline(int $panel_id, string $user_id): void;

    /**
     * Удаление пользователя панели
     *
     * @param string $user_id
     * @param int $panel_id
     * @return void
     */
    public function deleteServerUser(int $panel_id, string $user_id): void;

    /**
     * @param int $panel_id
     * @param string $user_id
     * @return array
     */
    public function getSubscribeInfo(int $panel_id, string $user_id): array;
}
