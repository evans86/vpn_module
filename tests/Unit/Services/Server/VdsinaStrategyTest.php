<?php

namespace Services\Server;

use App\Models\Location\Location;
use App\Models\Server\Server;
use App\Services\Cloudflare\CloudflareService;
use App\Services\External\VdsinaAPI;
use App\Services\Server\vdsina\VdsinaService;
use Mockery;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

class VdsinaStrategyTest extends TestCase
{
    use RefreshDatabase;

    private $vdsinaService;
    private $location;
    private $mockLogger;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем полноценный мок логгера
        $this->mockLogger = Mockery::mock(LoggerInterface::class);
        $this->mockLogger
            ->allows('error')
            ->zeroOrMoreTimes()
            ->andReturnNull();
        $this->mockLogger
            ->allows('info')
            ->zeroOrMoreTimes()
            ->andReturnNull();
        $this->mockLogger
            ->allows('warning')
            ->zeroOrMoreTimes()
            ->andReturnNull();

        // Подменяем фасад Log
        Log::swap($this->mockLogger);

        // Создаем локацию для теста
        $this->location = Location::factory()->create([
            'id' => rand(1000, 9999),
            'code' => 'AMS',
            'emoji' => ':nl:'
        ]);

        // Создаем экземпляр сервиса
        $this->vdsinaService = new VdsinaService();
    }

    /** @test */
    public function it_can_configure_server()
    {
        // Полностью мокаем VdsinaAPI
        $mockApi = Mockery::mock(VdsinaAPI::class, [config('services.api_keys.vdsina_key')]);

        // Настройка ожиданий API
        $mockApi->expects('getDatacenter')
            ->andReturns([
                'status' => 'ok',
                'data' => [
                    ['id' => 1, 'name' => 'Amsterdam']
                ]
            ]);

        $mockApi->expects('getTemplate')
            ->andReturns([
                'status' => 'ok',
                'data' => [
                    ['id' => 23, 'name' => 'Ubuntu 24.04']
                ]
            ]);

        $mockApi->expects('getServerPlan')
            ->andReturns([
                'status' => 'ok',
                'data' => [
                    ['id' => 1, 'name' => 'Basic Plan']
                ]
            ]);

        $mockApi->expects('createServer')
            ->with(
                Mockery::type('string'),  // server_name
                1,                        // server_plan
                0,                        // autoprolong
                1,                        // datacenter
                23                        // template
            )
            ->andReturns([
                'status' => 'ok',
                'data' => [
                    'id' => '123456',
                    'ip' => '1.2.3.4',
                    'login' => 'root',
                    'password' => 'test_password'
                ]
            ]);

        // Мокаем Cloudflare для финальной настройки
        $mockCloudflare = Mockery::mock(CloudflareService::class);
        $mockCloudflare->expects('createSubdomain')
            ->with(
                Mockery::type('string'),  // serverName
                '1.2.3.4'                 // serverIp
            )
            ->andReturns((object)[
                'id' => '789',
                'name' => 'test-server',
                'ip' => '1.2.3.4'
            ]);

        // Мокаем getServerById для финальной настройки
        $mockApi->expects('getServerById')
            ->with('123456')
            ->andReturns([
                'data' => [
                    'name' => 'test-server',
                    'ip' => [
                        ['ip' => '1.2.3.4']
                    ]
                ]
            ]);

        // Мокаем updatePassword
        $mockApi->expects('updatePassword')
            ->andReturns([
                'status' => 'ok'
            ]);

        // Используем рефлексию для внедрения мока API
        $apiReflection = new ReflectionClass($this->vdsinaService);
        $apiProperty = $apiReflection->getProperty('vdsinaApi');
        $apiProperty->setAccessible(true);
        $apiProperty->setValue($this->vdsinaService, $mockApi);

        // Используем рефлексию для внедрения мока Cloudflare
        $cloudflareReflection = new ReflectionClass($this->vdsinaService);
        try {
            $cloudflareProperty = $cloudflareReflection->getProperty('cloudflareService');
            $cloudflareProperty->setAccessible(true);
            $cloudflareProperty->setValue($this->vdsinaService, $mockCloudflare);
        } catch (\ReflectionException $e) {
            // Если свойство не найдено, можно пропустить
            $this->markTestSkipped('Cloudflare service property not found');
        }

        // Выполняем тестируемый метод
        $server = $this->vdsinaService->configure(
            $this->location->id,
            Server::VDSINA,
            true
        );

        // Финальная настройка сервера
        $this->vdsinaService->finishConfigure($server->id);

        // Перезагружаем сервер из базы
        $updatedServer = Server::find($server->id);

        // Проверяем результат
        $this->assertInstanceOf(Server::class, $updatedServer);
        $this->assertEquals($this->location->id, $updatedServer->location_id);
        $this->assertEquals(Server::VDSINA, $updatedServer->provider);
        $this->assertEquals('1.2.3.4', $updatedServer->ip);
        $this->assertEquals('root', $updatedServer->login);
    }

    /** @test */
    public function it_can_delete_server()
    {
        // Создаем тестовый сервер
        $server = Server::factory()->create([
            'location_id' => $this->location->id,
            'provider' => Server::VDSINA,
            'provider_id' => '123456'
        ]);

        // Полностью мокаем VdsinaAPI
        $mockApi = Mockery::mock(VdsinaAPI::class, [config('services.api_keys.vdsina_key')]);

        // Настройка ожидания удаления
        $mockApi->expects('deleteServer')
            ->with('123456')
            ->andReturns([
                'status' => 'ok',
                'data' => null
            ]);

        // Используем рефлексию для внедрения мока
        $reflection = new ReflectionClass($this->vdsinaService);
        $property = $reflection->getProperty('vdsinaApi');
        $property->setAccessible(true);
        $property->setValue($this->vdsinaService, $mockApi);

        // Вызываем метод удаления
        $this->vdsinaService->delete($server);
        $server = Server::find($server->id);

        // Проверяем, что сервер удален из базы
        $this->assertEquals(Server::SERVER_DELETED, $server->server_status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
