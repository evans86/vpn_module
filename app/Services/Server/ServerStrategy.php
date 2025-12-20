<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use Exception;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Strategy pattern для работы с серверами различных провайдеров
 * 
 * Использует паттерн Strategy для инкапсуляции алгоритмов работы с разными провайдерами серверов
 * 
 * @deprecated Используйте ServerStrategyFactory напрямую для создания стратегий
 * Этот класс сохранен для обратной совместимости
 */
class ServerStrategy
{
    public ServerInterface $strategy;
    private ServerStrategyFactory $factory;

    /**
     * Создает стратегию для работы с указанным провайдером
     *
     * @param string $provider Провайдер сервера (например, Server::VDSINA)
     * @throws \DomainException Если провайдер не найден
     */
    public function __construct(string $provider)
    {
        $this->factory = new ServerStrategyFactory();
        $this->strategy = $this->factory->create($provider);
    }

    /**
     * Проверка доступности сервера
     *
     * @param Server $server Сервер для проверки
     * @return bool true если сервер доступен, false в противном случае
     */
    public function ping(Server $server): bool
    {
        return $this->strategy->ping($server);
    }

    /**
     * Создание сервера в первоначальном виде
     *
     * @param int $location_id
     * @param string $provider
     * @param bool $isFree
     * @return Server
     * @throws GuzzleException
     */
    public function configure(int $location_id, string $provider, bool $isFree): Server
    {
        return $this->strategy->configure($location_id, $provider, $isFree);
    }

    /**
     * Получение пароля сервера от провайдера
     *
     * @param int $server_id ID сервера
     * @return string|null Пароль сервера или null если не удалось получить
     */
    public function getServerPassword(int $server_id): ?string
    {
        return $this->strategy->getServerPassword($server_id);
    }

    /**
     * Проверка статуса сервера с окончательной настройкой сервера
     *
     * @return void
     * @throws GuzzleException
     */
    public function checkStatus(): void
    {
        $this->strategy->checkStatus();
    }

    /**
     * Создание панели и привязка к текущему серверу
     *
     * @param int $server_id
     * @param string $panel
     * @return void
     * @throws Exception
     */
    public function setPanel(int $server_id, string $panel): void
    {
        $this->strategy->setPanel($server_id, $panel);
    }

    /**
     * Удаление сервера
     *
     * @param Server $server
     * @return void
     * @throws Exception
     */
    public function delete(Server $server): void
    {
        $this->strategy->delete($server);
    }
}
