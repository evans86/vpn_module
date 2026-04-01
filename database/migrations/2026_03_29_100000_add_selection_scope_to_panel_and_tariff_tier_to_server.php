<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('server', function (Blueprint $table) {
            if (! Schema::hasColumn('server', 'tariff_tier')) {
                $table->string('tariff_tier', 32)->default('full');
            }
        });

        Schema::table('panel', function (Blueprint $table) {
            if (! Schema::hasColumn('panel', 'selection_scope_score')) {
                $table->decimal('selection_scope_score', 8, 2)->default(0);
            }
            if (! Schema::hasColumn('panel', 'selection_scope_computed_at')) {
                $table->timestamp('selection_scope_computed_at')->nullable();
            }
            if (! Schema::hasColumn('panel', 'selection_scope_meta')) {
                $table->json('selection_scope_meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('panel', function (Blueprint $table) {
            if (Schema::hasColumn('panel', 'selection_scope_meta')) {
                $table->dropColumn('selection_scope_meta');
            }
            if (Schema::hasColumn('panel', 'selection_scope_computed_at')) {
                $table->dropColumn('selection_scope_computed_at');
            }
            if (Schema::hasColumn('panel', 'selection_scope_score')) {
                $table->dropColumn('selection_scope_score');
            }
        });

        Schema::table('server', function (Blueprint $table) {
            if (Schema::hasColumn('server', 'tariff_tier')) {
                $table->dropColumn('tariff_tier');
            }
        });
    }
};
