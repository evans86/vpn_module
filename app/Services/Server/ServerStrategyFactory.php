<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use App\Services\Server\ServerInterface;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Фабрика для создания стратегий работы с серверами
 * 
 * Позволяет легко добавлять новые типы провайдеров без изменения существующего кода
 * 
 * @example
 * $factory = new ServerStrategyFactory();
 * $strategy = $factory->create(Server::VDSINA);
 * $server = $strategy->configure($locationId, $provider, $isFree);
 */
class ServerStrategyFactory
{
    /**
     * Маппинг провайдеров на классы стратегий
     * 
     * @var array<string, string>
     */
    private static array $strategyMap = [
        Server::VDSINA => \App\Services\Server\strategy\ServerVdsinaStrategy::class,
        Server::TIMEWEB => \App\Services\Server\strategy\ServerTimewebStrategy::class,
        // Здесь можно добавить новые провайдеры:
        // Server::NEW_PROVIDER => \App\Services\Server\strategy\ServerNewProviderStrategy::class,
    ];

    /**
     * Создать стратегию для указанного провайдера
     *
     * @param string $provider Тип провайдера (например, Server::VDSINA)
     * @return ServerInterface Стратегия для работы с сервером
     * @throws DomainException Если провайдер не найден
     */
    public function create(string $provider): ServerInterface
    {
        if (!isset(self::$strategyMap[$provider])) {
            Log::error('Server strategy not found', [
                'provider' => $provider,
                'available_providers' => array_keys(self::$strategyMap)
            ]);
            
            throw new DomainException(
                "Server strategy not found for provider: {$provider}. " .
                "Available providers: " . implode(', ', array_keys(self::$strategyMap))
            );
        }

        $strategyClass = self::$strategyMap[$provider];
        
        // Используем DI контейнер для создания стратегии с зависимостями
        $strategy = app($strategyClass);
        
        if (!$strategy instanceof ServerInterface) {
            throw new DomainException(
                "Strategy class {$strategyClass} must implement ServerInterface"
            );
        }

        return $strategy;
    }

    /**
     * Получить список доступных провайдеров
     *
     * @return array<string> Массив доступных провайдеров
     */
    public function getAvailableProviders(): array
    {
        return array_keys(self::$strategyMap);
    }

    /**
     * Проверить, поддерживается ли провайдер
     *
     * @param string $provider Тип провайдера
     * @return bool true если провайдер поддерживается
     */
    public function isProviderSupported(string $provider): bool
    {
        return isset(self::$strategyMap[$provider]);
    }

    /**
     * Зарегистрировать новую стратегию (для расширяемости)
     * 
     * ВНИМАНИЕ: Используйте только в Service Provider или конфигурации!
     *
     * @param string $provider Тип провайдера
     * @param string $strategyClass Класс стратегии
     * @return void
     * @throws DomainException Если класс стратегии не существует или не реализует интерфейс
     */
    public static function registerStrategy(string $provider, string $strategyClass): void
    {
        if (!class_exists($strategyClass)) {
            throw new DomainException("Strategy class {$strategyClass} does not exist");
        }

        if (!is_subclass_of($strategyClass, ServerInterface::class)) {
            throw new DomainException(
                "Strategy class {$strategyClass} must implement ServerInterface"
            );
        }

        self::$strategyMap[$provider] = $strategyClass;
        
        Log::info('Server strategy registered', [
            'provider' => $provider,
            'strategy_class' => $strategyClass
        ]);
    }
}
