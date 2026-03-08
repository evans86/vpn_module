<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'key_activate_user_activate_id_idx';

    /**
     * Составной индекс для запросов вида:
     * where('key_activate_id', $id)->orderBy('id')->get()
     * Убирает filesort при выборке слотов ключа (подписка, контент конфига).
     */
    public function up(): void
    {
        if ($this->indexExists()) {
            return;
        }

        Schema::table('key_activate_user', function (Blueprint $table) {
            $table->index(['key_activate_id', 'id'], self::INDEX_NAME);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!$this->indexExists()) {
            return;
        }

        Schema::table('key_activate_user', function (Blueprint $table) {
            $table->dropIndex(self::INDEX_NAME);
        });
    }

    private function indexExists(): bool
    {
        $result = DB::selectOne(
            "SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE()
               AND table_name = 'key_activate_user'
               AND index_name = ?",
            [self::INDEX_NAME]
        );

        return $result !== null;
    }
};
