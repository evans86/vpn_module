<?php

namespace Database\Factories;

use App\Models\Server;
use Illuminate\Database\Eloquent\Factories\Factory;

class ServerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Server::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'name' => $this->faker->word . ' Server',
            'ip' => $this->faker->ipv4,
            'port' => $this->faker->numberBetween(1000, 65535),
            'status' => $this->faker->boolean(80), // 80% шанс быть активным
        ];
    }
}
