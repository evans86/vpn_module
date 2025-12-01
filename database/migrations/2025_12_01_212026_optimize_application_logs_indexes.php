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
        Schema::table('application_logs', function (Blueprint $table) {
            // Составной индекс для быстрого поиска по уровню и дате
            $table->index(['level', 'created_at'], 'idx_logs_level_created');

            // Составной индекс для поиска по источнику и дате
            $table->index(['source', 'created_at'], 'idx_logs_source_created');

            // Индекс для поиска по дате (если еще не существует)
            if (!$this->indexExists('application_logs', 'idx_logs_created_at')) {
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
            $table->dropIndex('idx_logs_level_created');
            $table->dropIndex('idx_logs_source_created');
            $table->dropIndex('idx_logs_created_at');
        });
    }

    /**
     * Проверка существования индекса
     */
    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $databaseName = $connection->getDatabaseName();

        $result = $connection->select(
            "SELECT COUNT(*) as count FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?",
            [$databaseName, $table, $index]
        );

        return $result[0]->count > 0;
    }
}
