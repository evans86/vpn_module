<?php

namespace Database\Seeders;

use App\Models\Panel\Panel;
use App\Models\Server\Server;
use Illuminate\Database\Seeder;

class PanelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Получаем все настроенные сервера
        $servers = Server::where('server_status', Server::SERVER_CONFIGURED)
                        ->where('is_free', true)
                        ->get();

        foreach ($servers as $server) {
            // Создаем панель для каждого настроенного сервера
            Panel::create([
                'panel' => Panel::MARZBAN,
                'panel_adress' => 'https://' . $server->host . ':8080',
                'panel_login' => 'admin',
                'panel_password' => bcrypt('admin'),
                'server_id' => $server->id,
                'panel_status' => Panel::PANEL_CREATED
            ]);

            // Помечаем сервер как занятый
            $server->update(['is_free' => false]);
        }
    }
}
