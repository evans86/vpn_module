<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ускоряет JOIN в loadActiveUsersCountByPanelIds (WHERE key_activate.status = ACTIVE).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('key_activate')) {
            return;
        }

        Schema::table('key_activate', function (Blueprint $table) {
            $table->index('status', 'key_activate_status_idx');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('key_activate')) {
            return;
        }

        Schema::table('key_activate', function (Blueprint $table) {
            $table->dropIndex('key_activate_status_idx');
        });
    }
};
