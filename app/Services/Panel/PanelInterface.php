<?php

namespace App\Services\Panel;

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
     * @return void
     */
    public function addServerUser(int $panel_id): void;

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
}
