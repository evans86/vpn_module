<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use App\Models\PackSalesman\PackSalesman;
use App\Models\Server\Server;
use App\Models\Panel\Panel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClearModuleDataCommand extends Command
{
    protected $signature = 'module:clear-data {--force : Принудительное удаление без подтверждения}';
    protected $description = 'Удаление всех серверов, панелей, ключей и пакетов продавцов';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Вы уверены, что хотите удалить ВСЕ данные? Это действие необратимо!')) {
            $this->info('Операция отменена.');
            return;
        }

        $this->info('Начинаем удаление данных...');

        try {
            // Удаляем серверы
            $serverCount = Server::count();
            Server::query()->delete();
            $this->info("Удалено серверов: {$serverCount}");

            // Удаляем панели
            $panelCount = Panel::count();
            Panel::query()->delete();
            $this->info("Удалено панелей: {$panelCount}");

//            // Удаляем ключи
//            $keyCount = KeyActivate::count();
//            KeyActivate::query()->delete();
//            $this->info("Удалено ключей: {$keyCount}");
//
//            // Удаляем пакеты продавцов
//            $packageCount = PackSalesman::count();
//            PackSalesman::query()->delete();
//            $this->info("Удалено пакетов: {$packageCount}");

            $this->info('Все данные успешно удалены!');

            // Логируем успешное удаление
            Log::info('Выполнена очистка данных модуля', [
                'servers' => $serverCount,
                'panels' => $panelCount,
//                'keys' => $keyCount,
//                'packages' => $packageCount
            ]);

        } catch (\Exception $e) {
            $this->error('Произошла ошибка при удалении данных: ' . $e->getMessage());

            Log::error('Ошибка при очистке данных модуля', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }

        return 0;
    }
}
