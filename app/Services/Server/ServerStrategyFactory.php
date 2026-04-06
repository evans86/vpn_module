<?php

namespace App\Services\Server;

use App\Models\Server\Server;
use App\Services\Server\strategy\ServerManualStrategy;
use App\Services\Server\strategy\ServerTimewebStrategy;
use App\Services\Server\strategy\ServerVdsinaStrategy;
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
     * Провайдеры с собственной стратегией (API). Всё остальное — ServerManualStrategy (произвольный slug).
     *
     * @var array<string, string>
     */
    private static array $apiStrategyMap = [
        Server::VDSINA => ServerVdsinaStrategy::class,
        Server::TIMEWEB => ServerTimewebStrategy::class,
    ];

    /**
     * Создать стратегию для указанного провайдера
     *
     * @param string $provider Тип провайдера (например, Server::VDSINA или slug ручного сервера)
     * @return ServerInterface Стратегия для работы с сервером
     * @throws DomainException Если провайдер не найден
     */
    public function create(string $provider): ServerInterface
    {
        $p = strtolower(trim($provider));
        if ($p === '') {
            throw new DomainException('Пустой код провайдера.');
        }
        if (isset(self::$apiStrategyMap[$p])) {
            $strategyClass = self::$apiStrategyMap[$p];
            $strategy = app($strategyClass);
            if (!$strategy instanceof ServerInterface) {
                throw new DomainException(
                    "Strategy class {$strategyClass} must implement ServerInterface"
                );
            }

            return $strategy;
        }

        $strategy = app(ServerManualStrategy::class);
        if (!$strategy instanceof ServerInterface) {
            throw new DomainException('ServerManualStrategy must implement ServerInterface');
        }

        return $strategy;
    }

    /**
     * Список провайдеров, для которых есть создание сервера через API (форма «Добавить сервер (API)»).
     *
     * @return array<string>
     */
    public function getAvailableProviders(): array
    {
        return array_keys(self::$apiStrategyMap);
    }

    /**
     * Поддерживается ли строка провайдера (есть стратегия, включая произвольный slug → manual).
     */
    public function isProviderSupported(string $provider): bool
    {
        return trim($provider) !== '';
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

        self::$apiStrategyMap[$provider] = $strategyClass;

        Log::info('Server strategy registered', [
            'provider' => $provider,
            'strategy_class' => $strategyClass,
        ]);
    }
}
