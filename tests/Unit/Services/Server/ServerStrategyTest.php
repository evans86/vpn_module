<?php

namespace Services\Server;

use App\Models\Server\Server;
use App\Services\Server\ServerStrategy;
use App\Services\Server\strategy\ServerVdsinaStrategy;
use DomainException;
use Mockery;
use Tests\TestCase;

class ServerStrategyTest extends TestCase
{
    /** @test */
    public function it_creates_vdsina_strategy_when_provider_is_vdsina()
    {
        $serverStrategy = new ServerStrategy(Server::VDSINA);

        $this->assertInstanceOf(ServerVdsinaStrategy::class, $serverStrategy->strategy);
    }

    /** @test */
    public function it_throws_exception_for_unknown_provider()
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Server strategy not found');

        new ServerStrategy('unknown_provider');
    }

    /** @test */
    public function it_delegates_configure_method_to_strategy()
    {
        $mockStrategy = Mockery::mock(ServerVdsinaStrategy::class);
        $mockServer = Mockery::mock(Server::class);

        $mockStrategy
            ->expects('configure')
            ->with(1, Server::VDSINA, true)
            ->andReturns($mockServer);

        $serverStrategy = new ServerStrategy(Server::VDSINA);
        $serverStrategy->strategy = $mockStrategy;

        $result = $serverStrategy->configure(1, Server::VDSINA, true);

        $this->assertSame($mockServer, $result);
    }

    /** @test */
    public function it_delegates_check_status_method_to_strategy()
    {
        $mockStrategy = Mockery::mock(ServerVdsinaStrategy::class);

        $mockStrategy
            ->expects('checkStatus')
            ->andReturnNull();

        $serverStrategy = new ServerStrategy(Server::VDSINA);
        $serverStrategy->strategy = $mockStrategy;

        // Вызываем метод
        $result = $serverStrategy->checkStatus();

        // Явная проверка, что метод ничего не возвращает
        $this->assertNull($result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
