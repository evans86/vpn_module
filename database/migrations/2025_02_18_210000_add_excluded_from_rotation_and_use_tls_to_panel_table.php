<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->boolean('excluded_from_rotation')->default(false)->after('tls_key_path');
            $table->boolean('use_tls')->default(false)->after('excluded_from_rotation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->dropColumn(['excluded_from_rotation', 'use_tls']);
        });
    }
};

