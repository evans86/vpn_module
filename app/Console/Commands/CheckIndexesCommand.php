<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CheckIndexesCommand extends Command
{
    protected $signature = 'indexes:check {table? : Имя таблицы для проверки}';

    protected $description = 'Проверка существования индексов в таблице';

    public function handle(): int
    {
        $table = $this->argument('table') ?? 'application_logs';
        
        $this->info("🔍 Проверка индексов для таблицы: {$table}");
        $this->newLine();

        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        // Получаем все индексы для таблицы
        $indexes = $connection->select(
            "SELECT 
                INDEX_NAME as name,
                GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) as columns,
                NON_UNIQUE as non_unique
             FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ?
             GROUP BY INDEX_NAME, NON_UNIQUE
             ORDER BY INDEX_NAME",
            [$databaseName, $table]
        );

        if (empty($indexes)) {
            $this->warn("⚠️ Индексы не найдены для таблицы: {$table}");
            return 1;
        }

        $this->info("Найдено индексов: " . count($indexes));
        $this->newLine();

        $headers = ['Имя индекса', 'Колонки', 'Тип'];
        $rows = [];

        foreach ($indexes as $index) {
            $name = is_object($index) ? $index->name : $index['name'];
            $columns = is_object($index) ? $index->columns : $index['columns'];
            $nonUnique = is_object($index) ? $index->non_unique : $index['non_unique'];
            $type = $nonUnique == 0 ? 'UNIQUE' : 'INDEX';
            
            $rows[] = [$name, $columns, $type];
        }

        $this->table($headers, $rows);

        // Проверяем конкретные индексы для application_logs
        if ($table === 'application_logs') {
            $this->newLine();
            $this->info("Проверка целевых индексов:");
            
            $targetIndexes = [
                'idx_logs_level_created',
                'idx_logs_source_created',
                'idx_logs_created_at',
                'idx_logs_cleanup'
            ];

            foreach ($targetIndexes as $targetIndex) {
                $exists = $this->indexExists($table, $targetIndex);
                $status = $exists ? '✅ Существует' : '❌ Отсутствует';
                $this->line("  {$targetIndex}: {$status}");
            }
        }

        return 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $connection = Schema::getConnection();
            $databaseName = $connection->getDatabaseName();

            $result = $connection->select(
                "SELECT COUNT(*) as `count` FROM information_schema.statistics
                 WHERE table_schema = ? AND table_name = ? AND index_name = ?",
                [$databaseName, $table, $index]
            );

            if (empty($result) || !isset($result[0])) {
                return false;
            }

            $count = is_object($result[0]) ? $result[0]->count : $result[0]['count'];
            return (int)$count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}

