<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Должна выполняться после 2026_04_24_100000_add_warp_routing_all_to_panel_table
 * (добавление колонки warp_routing_all).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->boolean('warp_routing_all')->default(true)->change();
        });

        // У кого уже был включён WARP, ставим маршрут «всё, кроме private» (весь внешний трафик панели)
        DB::table('panel')
            ->where('warp_routing_enabled', true)
            ->update(['warp_routing_all' => true]);
    }

    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->boolean('warp_routing_all')->default(false)->change();
        });
    }
};
