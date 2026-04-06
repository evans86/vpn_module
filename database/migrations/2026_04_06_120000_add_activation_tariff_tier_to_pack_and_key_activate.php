<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pack', function (Blueprint $table) {
            if (! Schema::hasColumn('pack', 'activation_tariff_tier')) {
                $table->string('activation_tariff_tier', 32)->nullable();
            }
        });

        Schema::table('key_activate', function (Blueprint $table) {
            if (! Schema::hasColumn('key_activate', 'activation_tariff_tier')) {
                $table->string('activation_tariff_tier', 32)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('key_activate', function (Blueprint $table) {
            if (Schema::hasColumn('key_activate', 'activation_tariff_tier')) {
                $table->dropColumn('activation_tariff_tier');
            }
        });

        Schema::table('pack', function (Blueprint $table) {
            if (Schema::hasColumn('pack', 'activation_tariff_tier')) {
                $table->dropColumn('activation_tariff_tier');
            }
        });
    }
};
