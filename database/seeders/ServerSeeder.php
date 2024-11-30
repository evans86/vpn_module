<?php

namespace Database\Seeders;

use App\Models\Server\Server;
use App\Models\Location\Location;
use Illuminate\Database\Seeder;

class ServerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Создаем тестовые сервера для разных локаций
        $locations = Location::all();
        
        if ($locations->isEmpty()) {
            // Если локации не созданы, создаем базовые
            Location::create(['code' => 'NL', 'emoji' => '&#127475;&#127473;']);  // NL flag
            Location::create(['code' => 'RU', 'emoji' => '&#127479;&#127482;']);  // RU flag
            $locations = Location::all();
        }

        foreach ($locations as $location) {
            // Создаем по 2 сервера для каждой локации
            for ($i = 1; $i <= 2; $i++) {
                Server::create([
                    'name' => $location->code . ' Server ' . $i,
                    'ip' => '192.168.' . $location->id . '.' . $i,
                    'login' => 'admin',
                    'password' => bcrypt('password'),
                    'host' => strtolower($location->code) . $i . '.example.com',
                    'provider' => Server::VDSINA,
                    'location_id' => $location->id,
                    'server_status' => $i === 1 ? Server::SERVER_CONFIGURED : Server::SERVER_CREATED,
                    'is_free' => $i === 1,
                ]);
            }
        }
    }
}
