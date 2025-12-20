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
     * Обновление конфигурации панели - стабильный вариант (без REALITY)
     *
     * @param int $panel_id
     * @return void
     */
    public function updateConfigurationStable(int $panel_id): void;

    /**
     * Обновление конфигурации панели - с REALITY (лучший обход блокировок)
     *
     * @param int $panel_id
     * @return void
     */
    public function updateConfigurationReality(int $panel_id): void;

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
    public function addServerUser(int $panel_id, int $userTgId, int $data_limit, int $expire, string $key_activate_id, array $options): ServerUser;

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

    /**
     * @return void
     */
    public function getServerStats(): void;

    /**
     * Обновление токена авторизации панели
     * 
     * @param int $panel_id ID панели
     * @return \App\Models\Panel\Panel Обновленная панель
     * @throws \Exception
     */
    public function updateToken(int $panel_id): \App\Models\Panel\Panel;

    /**
     * Перенос пользователя с одной панели на другую
     * 
     * @param int $sourcePanel_id ID исходной панели
     * @param int $targetPanel_id ID целевой панели
     * @param string $serverUser_id ID пользователя сервера (key_activate_id)
     * @return \App\Models\ServerUser\ServerUser Обновленный пользователь сервера
     * @throws \Exception
     */
    public function transferUser(int $sourcePanel_id, int $targetPanel_id, string $serverUser_id): \App\Models\ServerUser\ServerUser;
}
