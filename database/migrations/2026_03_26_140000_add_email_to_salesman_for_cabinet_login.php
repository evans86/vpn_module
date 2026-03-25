<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('salesman')) {
            return;
        }

        Schema::table('salesman', function (Blueprint $table) {
            if (! Schema::hasColumn('salesman', 'email')) {
                $table->string('email')->nullable()->unique()->after('username');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('salesman')) {
            return;
        }

        Schema::table('salesman', function (Blueprint $table) {
            if (Schema::hasColumn('salesman', 'email')) {
                $table->dropUnique(['email']);
                $table->dropColumn('email');
            }
        });
    }
};
