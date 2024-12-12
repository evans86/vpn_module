<?php

namespace Tests\Unit\Services\Pack;

use App\Dto\Pack\PackDto;
use App\Logging\DatabaseLogger;
use App\Models\Pack\Pack;
use App\Repositories\Pack\PackRepository;
use App\Services\Pack\PackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;
use Mockery\MockInterface;
use Exception;

class PackServiceTest extends TestCase
{
    use RefreshDatabase;

    private MockInterface $packRepository;
    private MockInterface $logger;
    private PackService $packService;

    protected function setUp(): void
    {
        parent::setUp();

        // Создаем моки с использованием
        $this->packRepository = $this->mock(PackRepository::class);
        $this->logger = $this->mock(DatabaseLogger::class);

        // Настраиваем логгер для всех тестов - разрешаем любое количество вызовов
        $this->logger->shouldIgnoreMissing();

        $this->packService = new PackService($this->packRepository, $this->logger);
    }

    /** @test */
    public function it_can_get_all_packs_paginated(): void
    {
        // Arrange
        $perPage = 10;
        $expectedPaginator = new LengthAwarePaginator([], 0, $perPage);

        $this->packRepository
            ->expects('getAllPaginated')
            ->with($perPage)
            ->once()
            ->andReturn($expectedPaginator);

        // Act
        $result = $this->packService->getAllPaginated($perPage);

        // Assert
        $this->assertSame($expectedPaginator, $result);
    }

    /** @test */
    public function it_throws_exception_when_getting_paginated_packs_fails(): void
    {
        // Arrange
        $perPage = 10;
        $errorMessage = 'Database error';

        $this->packRepository
            ->expects('getAllPaginated')
            ->with($perPage)
            ->once()
            ->andThrow(new Exception($errorMessage));

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to get packs: {$errorMessage}");

        $this->packService->getAllPaginated($perPage);
    }

    /** @test
     * @throws Exception
     */
    public function it_can_create_a_pack(): void
    {
        // Arrange
        $data = [
            'price' => 100,
            'period' => 30,
            'traffic_limit' => 1000,
            'count' => 1,
            'activate_time' => 24,
            'status' => true
        ];

        $pack = new Pack($data);
        $pack->id = 1;

        $this->packRepository
            ->expects('create')
            ->with($data)
            ->once()
            ->andReturn($pack);

        // Act
        $result = $this->packService->create($data);

        // Assert
        $this->assertInstanceOf(PackDto::class, $result);
        $this->assertEquals($data['price'], $result->price);
        $this->assertEquals($data['period'], $result->period);
        $this->assertEquals($data['traffic_limit'], $result->traffic_limit);
        $this->assertEquals($data['count'], $result->count);
        $this->assertEquals($data['activate_time'], $result->activate_time);
        $this->assertEquals($data['status'], $result->status);
    }

    /** @test */
    public function it_throws_exception_when_creating_pack_fails(): void
    {
        // Arrange
        $data = [
            'price' => 100,
            'period' => 30
        ];

        $errorMessage = 'Validation error';

        $this->packRepository
            ->expects('create')
            ->with($data)
            ->once()
            ->andThrow(new Exception($errorMessage));

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to create pack: {$errorMessage}");

        $this->packService->create($data);
    }

    /** @test
     * @throws Exception
     */
    public function it_can_update_a_pack(): void
    {
        // Arrange
        $id = 1;
        $data = [
            'price' => 200,
            'period' => 60,
            'traffic_limit' => 2000,
            'count' => 2,
            'activate_time' => 48,
            'status' => 1
        ];

        $pack = new Pack($data);
        $pack->id = $id;

        $this->packRepository
            ->expects('findByIdOrFail')
            ->with($id)
            ->once()
            ->andReturn($pack);

        $this->packRepository
            ->expects('updatePack')
            ->with($pack, $data)
            ->once()
            ->andReturn($pack);

        // Act
        $result = $this->packService->update($id, $data);

        // Assert
        $this->assertInstanceOf(PackDto::class, $result);
        $this->assertEquals($data['price'], $result->price);
        $this->assertEquals($data['period'], $result->period);
        $this->assertEquals($data['traffic_limit'], $result->traffic_limit);
        $this->assertEquals($data['count'], $result->count);
        $this->assertEquals($data['activate_time'], $result->activate_time);
        $this->assertEquals($data['status'], $result->status);
    }

    /** @test */
    public function it_throws_exception_when_updating_pack_fails(): void
    {
        // Arrange
        $id = 1;
        $data = ['price' => 200];
        $errorMessage = 'Pack not found';

        $this->packRepository
            ->expects('findByIdOrFail')
            ->with($id)
            ->once()
            ->andThrow(new Exception($errorMessage));

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to update pack: {$errorMessage}");

        $this->packService->update($id, $data);
    }

    /** @test
     * @throws Exception
     */
    public function it_can_toggle_pack_status(): void
    {
        // Arrange
        $id = 1;
        $pack = new Pack();
        $pack->id = $id;
        $pack->price = 1000;
        $pack->period = 60;
        $pack->traffic_limit = 2000;
        $pack->count = 5;
        $pack->activate_time = 48;
        $pack->status = true;

        $updatedPack = clone $pack;
        $updatedPack->status = false;

        $this->packRepository
            ->expects('findByIdOrFail')
            ->with($id)
            ->once()
            ->andReturn($pack);

        $this->packRepository
            ->expects('updateStatus')
            ->withArgs(function ($packArg, $statusArg) use ($pack) {
                return $packArg->id === $pack->id && $statusArg === false;
            })
            ->once()
            ->andReturn($updatedPack);

        // Act
        $result = $this->packService->toggleStatus($id);

        // Assert
        $this->assertInstanceOf(PackDto::class, $result);
        $this->assertFalse($result->status);
    }

    /** @test */
    public function it_throws_exception_when_toggling_pack_status_fails(): void
    {
        // Arrange
        $id = 1;
        $errorMessage = 'Pack not found';

        $this->packRepository
            ->expects('findByIdOrFail')
            ->with($id)
            ->once()
            ->andThrow(new Exception($errorMessage));

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to toggle pack status: {$errorMessage}");

        $this->packService->toggleStatus($id);
    }

    /** @test
     * @throws Exception
     */
    public function it_can_delete_a_pack(): void
    {
        // Arrange
        $id = 1;
        $pack = new Pack();
        $pack->id = $id;

        $this->packRepository
            ->expects('findByIdOrFail')
            ->with($id)
            ->once()
            ->andReturn($pack);

        $this->packRepository
            ->expects('delete')
            ->with($pack)
            ->once()
            ->andReturn(true);

        // Act
        $result = $this->packService->delete($id);

        // Assert
        $this->assertTrue($result);
    }

    /** @test */
    public function it_throws_exception_when_deleting_pack_fails(): void
    {
        // Arrange
        $id = 1;
        $errorMessage = 'Pack not found';

        $this->packRepository
            ->expects('findByIdOrFail')
            ->with($id)
            ->once()
            ->andThrow(new Exception($errorMessage));

        // Assert & Act
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Failed to delete pack: {$errorMessage}");

        $this->packService->delete($id);
    }
}
