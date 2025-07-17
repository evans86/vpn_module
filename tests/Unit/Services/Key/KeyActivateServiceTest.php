<?php

namespace Services\Key;

use App\Dto\Bot\BotModuleDto;
use App\Models\KeyActivate\KeyActivate;
use App\Repositories\KeyActivate\KeyActivateRepository;
use App\Repositories\PackSalesman\PackSalesmanRepository;
use App\Repositories\Panel\PanelRepository;
use App\Services\Key\KeyActivateService;
use App\Services\Notification\NotificationService;
use App\Logging\DatabaseLogger;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class KeyActivateServiceTest extends TestCase
{
    private KeyActivateService $service;
    private Mockery\MockInterface $keyActivateRepo;
    private Mockery\MockInterface $packSalesmanRepo;
    private Mockery\MockInterface $panelRepo;
    private Mockery\MockInterface $logger;
    private Mockery\MockInterface $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем моки для всех зависимостей
        $this->keyActivateRepo = Mockery::mock(KeyActivateRepository::class);
        $this->packSalesmanRepo = Mockery::mock(PackSalesmanRepository::class);
        $this->panelRepo = Mockery::mock(PanelRepository::class);
        $this->logger = Mockery::mock(DatabaseLogger::class);
        $this->notificationService = Mockery::mock(NotificationService::class);

        $this->service = new KeyActivateService(
            $this->keyActivateRepo,
            $this->packSalesmanRepo,
            $this->panelRepo,
            $this->logger,
            $this->notificationService
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_creates_key_without_pack_salesman()
    {
        $trafficLimit = 1000;
        $finishAt = now()->addMonth()->timestamp;

        $expectedKey = new KeyActivate([
            'id' => 'test-uuid',
            'traffic_limit' => $trafficLimit,
            'finish_at' => $finishAt,
            'status' => KeyActivate::PAID
        ]);

        // Ожидаем вызовы
        $this->keyActivateRepo->expects('createKey')
            ->with(Mockery::on(function ($data) use ($trafficLimit, $finishAt) {
                return $data['traffic_limit'] === $trafficLimit
                    && $data['finish_at'] === $finishAt
                    && $data['status'] === KeyActivate::PAID;
            }))
            ->andReturns($expectedKey);

        $this->logger->expects('info')
            ->with('Ключ успешно создан', Mockery::type('array'));

        $result = $this->service->create(
            $trafficLimit,
            null, // pack_salesman_id
            $finishAt,
            null  // deleted_at
        );

        $this->assertInstanceOf(KeyActivate::class, $result);
        $this->assertEquals('test-uuid', $result->id);
    }

    /** @test */
    public function it_creates_key_with_pack_salesman()
    {
        $trafficLimit = 2000;
        $packSalesmanId = 1;
        $finishAt = now()->addMonth()->timestamp;

        // Mock через Mockery (не через PHPUnit)
        $packSalesman = Mockery::mock(\App\Models\PackSalesman\PackSalesman::class);
        $packSalesman->allows('__get')->with('id')->andReturns($packSalesmanId);

        $expectedKey = new KeyActivate([
            'id' => 'test-uuid-2',
            'traffic_limit' => $trafficLimit,
            'pack_salesman_id' => $packSalesmanId,
            'finish_at' => $finishAt,
            'status' => KeyActivate::PAID
        ]);

        // Настройка моков в стиле Mockery
        $this->packSalesmanRepo->allows('findByIdOrFail')
            ->with($packSalesmanId)
            ->andReturns($packSalesman);

        $this->keyActivateRepo->allows('createKey')
            ->with(Mockery::on(function ($data) use ($trafficLimit, $finishAt, $packSalesmanId) {
                return $data['traffic_limit'] == $trafficLimit
                    && $data['pack_salesman_id'] == $packSalesmanId
                    && $data['finish_at'] == $finishAt
                    && $data['status'] == KeyActivate::PAID;
            }))
            ->andReturns($expectedKey);

        $this->logger->allows('info')
            ->with('Ключ успешно создан', Mockery::type('array'));

        $result = $this->service->create(
            $trafficLimit,
            $packSalesmanId,
            $finishAt,
            null
        );

        $this->assertInstanceOf(KeyActivate::class, $result);
        $this->assertEquals('test-uuid-2', $result->id);
    }

    /** @test */
    public function it_throws_exception_when_pack_salesman_not_found()
    {
        $packSalesmanId = 999;
        $exception = new RuntimeException('Pack salesman not found');

        $this->packSalesmanRepo->allows('findByIdOrFail')
            ->with($packSalesmanId)
            ->andThrow($exception);

        // Разрешаем любые вызовы error() (минимум 1 раз)
        $this->logger->expects('error')->atLeast();

        $this->expectException(RuntimeException::class);

        $this->service->create(
            1000,
            $packSalesmanId,
            now()->addMonth()->timestamp,
            null
        );
    }
}
