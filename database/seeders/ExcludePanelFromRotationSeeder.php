<?php

namespace Database\Seeders;

use App\Models\Panel\Panel;
use Illuminate\Database\Seeder;

class ExcludePanelFromRotationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Исключаем панель ID 30 из ротации для тестирования
        $panel = Panel::find(30);
        
        if ($panel) {
            $panel->excluded_from_rotation = true;
            $panel->save();
            
            $this->command->info("Панель ID 30 исключена из ротации для тестирования");
        } else {
            $this->command->warn("Панель ID 30 не найдена");
        }
    }
}

