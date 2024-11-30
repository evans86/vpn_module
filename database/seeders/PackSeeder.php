<?php

namespace Database\Seeders;

use App\Models\Pack\Pack;
use Illuminate\Database\Seeder;

class PackSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $packs = [
            [
                'price' => 1000, // 1000 рублей
                'period' => 30, // 30 дней
                'traffic_limit' => 10 * 1024 * 1024 * 1024, // 10 GB в байтах
                'count' => 5, // 5 ключей
                'activate_time' => 24 * 60 * 60, // 24 часа на активацию
                'status' => true
            ],
            [
                'price' => 2500,
                'period' => 30,
                'traffic_limit' => 25 * 1024 * 1024 * 1024, // 25 GB
                'count' => 5,
                'activate_time' => 24 * 60 * 60,
                'status' => true
            ],
            [
                'price' => 4500,
                'period' => 30,
                'traffic_limit' => 50 * 1024 * 1024 * 1024, // 50 GB
                'count' => 5,
                'activate_time' => 24 * 60 * 60,
                'status' => true
            ]
        ];

        foreach ($packs as $pack) {
            Pack::create($pack);
        }
    }
}
