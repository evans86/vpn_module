<?php

namespace App\Console\Commands;

use App\Models\KeyActivate\KeyActivate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClearModuleDataCommand extends Command
{
    protected $signature = 'module:update-data {--force : Принудительное удаление без подтверждения}';
    protected $description = 'Удаление всех серверов, панелей, ключей и пакетов продавцов';

    public function handle()
    {
        if (!$this->option('force') && !$this->confirm('Вы уверены, что хотите обновить ВСЕ данные? Это действие необратимо!')) {
            $this->info('Операция отменена.');
            return Command::SUCCESS;
        }

        $this->info('Начинаем обновление данных...');

        try {

            $keyActivates = KeyActivate::where('status', KeyActivate::PAID)
                ->where('finish_at', '!=', null)->where('user_tg_id', '=', null)
                ->get();

            $count = $keyActivates->count();
            $this->info("получено ключей: {$count}");

            foreach ($keyActivates as $keyActivate) {
                $keyActivate->finish_at = null;
                $keyActivate->save();
            }


            // Логируем успешное удаление
            Log::info('Выполнено обновление данных', [
                'source' => 'cron'
            ]);

        } catch (\Exception $e) {
            $this->error('Произошла ошибка при удалении данных: ' . $e->getMessage());

            Log::error('Ошибка при очистке данных модуля', [
                'source' => 'cron',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return 1;
        }

        return 0;
    }
}
