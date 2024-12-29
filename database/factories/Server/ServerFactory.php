<?php

namespace Database\Factories\Server;

use App\Models\Location\Location;
use App\Models\Server\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    protected $model = Server::class;

    public function definition(): array
    {
        return [
            'location_id' => Location::factory(), // Автоматическое создание связанной локации
            'provider' => Server::VDSINA,
            'is_free' => $this->faker->boolean,
            'server_status' => Server::SERVER_CREATED,
            'ip' => $this->faker->ipv4,
            'login' => 'admin',
            'password' => 'password',
            'name' => 'Test Server',
            'provider_id' => $this->faker->numberBetween(100000, 999999),
            'dns_record_id' => $this->faker->numberBetween(100000, 999999),
            'host' => $this->faker->domainName
        ];
    }
}
