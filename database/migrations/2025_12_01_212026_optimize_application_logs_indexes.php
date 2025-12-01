<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class OptimizeApplicationLogsIndexes extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Проверяем существование индексов ДО создания Blueprint
        $idxLevelCreated = $this->indexExists('application_logs', 'idx_logs_level_created');
        $idxSourceCreated = $this->indexExists('application_logs', 'idx_logs_source_created');
        $idxCreatedAt = $this->indexExists('application_logs', 'idx_logs_created_at');
        $idxCleanup = $this->indexExists('application_logs', 'idx_logs_cleanup');

        Schema::table('application_logs', function (Blueprint $table) use ($idxLevelCreated, $idxSourceCreated, $idxCreatedAt, $idxCleanup) {
            // Составной индекс для быстрого поиска по уровню и дате
            if (!$idxLevelCreated) {
                $table->index(['level', 'created_at'], 'idx_logs_level_created');
            }

            // Составной индекс для поиска по источнику и дате
            if (!$idxSourceCreated) {
                $table->index(['source', 'created_at'], 'idx_logs_source_created');
            }

            // Индекс для поиска по дате (если еще не существует)
            if (!$idxCreatedAt && !$idxCleanup) {
                $table->index('created_at', 'idx_logs_created_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('application_logs', function (Blueprint $table) {
            if ($this->indexExists('application_logs', 'idx_logs_level_created')) {
                $table->dropIndex('idx_logs_level_created');
            }
            if ($this->indexExists('application_logs', 'idx_logs_source_created')) {
                $table->dropIndex('idx_logs_source_created');
            }
            if ($this->indexExists('application_logs', 'idx_logs_created_at')) {
                $table->dropIndex('idx_logs_created_at');
            }
        });
    }

    /**
     * Проверка существования индекса
     */
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

            // Обрабатываем разные форматы ответа
            $count = is_object($result[0]) ? $result[0]->count : $result[0]['count'];
            return (int)$count > 0;
        } catch (\Exception $e) {
            // В случае ошибки считаем что индекс не существует
            \Log::warning('Error checking index existence', [
                'table' => $table,
                'index' => $index,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
