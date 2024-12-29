<?php
namespace Database\Factories\Location;

use App\Models\Location\Location;
use Illuminate\Database\Eloquent\Factories\Factory;

class LocationFactory extends Factory
{
    protected $model = Location::class;

    public function definition(): array
    {
        return [
            'code' => 'NL',
            'emoji' => ':nl:'
        ];
    }
}
