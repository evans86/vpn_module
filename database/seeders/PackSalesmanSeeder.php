<?php

namespace Database\Seeders;

use App\Models\Pack\Pack;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Salesman\Salesman;
use Illuminate\Database\Seeder;

class PackSalesmanSeeder extends Seeder
{
    public function run()
    {
        // Получаем существующие ID пакетов и продавцов
        $packIds = Pack::pluck('id')->toArray();
        $salesmanIds = Salesman::pluck('id')->toArray();

        if (empty($packIds) || empty($salesmanIds)) {
            $this->command->error('Нет пакетов или продавцов в базе данных. Сначала запустите PackSeeder и SalesmanSeeder.');
            return;
        }

        // Создаем тестовые записи
        for ($i = 0; $i < 50; $i++) {
            $pack_id = $packIds[array_rand($packIds)];
            $salesman_id = $salesmanIds[array_rand($salesmanIds)];
            
            // Генерируем случайный статус с разным распределением вероятностей
            $status = random_int(1, 10) <= 7 ? PackSalesman::PAID : 
                     (random_int(1, 10) <= 7 ? PackSalesman::NOT_PAID : PackSalesman::EXPIRED);

            PackSalesman::create([
                'pack_id' => $pack_id,
                'salesman_id' => $salesman_id,
                'status' => $status,
                'created_at' => now()->subDays(random_int(0, 60)), // случайная дата за последние 60 дней
                'updated_at' => now()->subDays(random_int(0, 60))
            ]);
        }
    }
}
