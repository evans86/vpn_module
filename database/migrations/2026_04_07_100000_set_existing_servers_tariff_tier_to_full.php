<?php

use App\Constants\TariffTier;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Все существующие серверы — тариф «основная выдача» (full), как ожидается для продакшена до выделения free/whitelist пулов.
     */
    public function up(): void
    {
        if (! Schema::hasTable('server') || ! Schema::hasColumn('server', 'tariff_tier')) {
            return;
        }

        $payload = ['tariff_tier' => TariffTier::FULL];
        if (Schema::hasColumn('server', 'updated_at')) {
            $payload['updated_at'] = now();
        }

        DB::table('server')->update($payload);
    }

    public function down(): void
    {
        // Не откатываем массовое обновление данных
    }
};
