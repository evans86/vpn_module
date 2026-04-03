<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Деление квоты traffic_limit между слотами Marzban (новые ключи). Старые строки остаются false.
     */
    public function up(): void
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->boolean('split_traffic_across_slots')->default(false)->after('traffic_limit');
        });
    }

    public function down(): void
    {
        Schema::table('key_activate', function (Blueprint $table) {
            $table->dropColumn('split_traffic_across_slots');
        });
    }
};
