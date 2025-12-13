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
            $table->string('config_type')->nullable()->default('stable')->after('reality_keys_generated_at');
            $table->timestamp('config_updated_at')->nullable()->after('config_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            $table->dropColumn(['config_type', 'config_updated_at']);
        });
    }
};

