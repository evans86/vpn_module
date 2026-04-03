<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Кэш расхода трафика с панели Marzban (байты) для страницы конфига и агрегации по слотам.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('server_user') || Schema::hasColumn('server_user', 'used_traffic')) {
            return;
        }

        Schema::table('server_user', function (Blueprint $table) {
            $table->unsignedBigInteger('used_traffic')->nullable()->after('keys');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('server_user') || !Schema::hasColumn('server_user', 'used_traffic')) {
            return;
        }

        Schema::table('server_user', function (Blueprint $table) {
            $table->dropColumn('used_traffic');
        });
    }
};
