<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReRunMigrationCommand extends Command
{
    protected $signature = 'migrate:rerun {migration : Имя файла миграции (например: 2025_12_01_212026_optimize_application_logs_indexes)}';

    protected $description = 'Удаляет запись о миграции из таблицы migrations и позволяет запустить её заново';

    public function handle(): int
    {
        $migrationName = $this->argument('migration');
        
        // Проверяем, существует ли миграция в таблице migrations
        $migration = DB::table('migrations')
            ->where('migration', $migrationName)
            ->first();

        if (!$migration) {
            $this->warn("⚠️ Миграция '{$migrationName}' не найдена в таблице migrations.");
            $this->info("💡 Возможно, она ещё не была выполнена. Попробуйте запустить: php artisan migrate");
            return 1;
        }

        $this->info("📋 Найдена миграция: {$migrationName}");
        $this->info("   Batch: {$migration->batch}");
        
        if (!$this->confirm('Удалить запись о миграции из таблицы migrations?', true)) {
            $this->info('❌ Операция отменена');
            return 0;
        }

        DB::table('migrations')
            ->where('migration', $migrationName)
            ->delete();

        $this->info("✅ Запись о миграции удалена из таблицы migrations");
        $this->newLine();
        $this->info("💡 Теперь вы можете запустить миграцию заново:");
        $this->line("   php artisan migrate");

        return 0;
    }
}

